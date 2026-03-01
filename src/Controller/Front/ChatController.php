<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Entity\Participation;
use App\Repository\UserRepository;
use App\Repository\ActivityRepository;
use App\Repository\ParticipationRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/api/chat', requirements: ['_locale' => 'fr|en|ar'])]
class ChatController extends AbstractController
{
    /** Python chat service (Ollama) — primary backend */
    private string $pythonChatUrl = 'http://localhost:8002/v1/chat/completions';

    /** Gemini API — fallback when Python service is down */
    private string $geminiApiKey;
    private string $geminiModel = 'gemini-2.0-flash';

    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private ActivityRepository $activityRepository,
        private ParticipationRepository $participationRepository,
        #[Autowire('%env(resolve:GEMINI_API_KEY)%')] string $geminiApiKey = ''
    ) {
        $this->geminiApiKey = $geminiApiKey;
    }

    #[Route('/proxy', name: 'app_chat_proxy', methods: ['POST'])]
    public function proxy(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON input'], 400);
        }

        // ── Extract latest user message for intent detection ───────────
        $lastUserMsg = '';
        foreach (array_reverse($data['messages'] ?? []) as $msg) {
            if (($msg['role'] ?? '') === 'user') {
                $lastUserMsg = is_array($msg['content']) ? ($msg['content'][0]['text'] ?? '') : ($msg['content'] ?? '');
                break;
            }
        }
        $detectedLang = $this->detectMessageLanguage($lastUserMsg);

        // ── Activity intent detection — handle before LLM ──────────────
        $intent = $this->detectActivityIntent($lastUserMsg);
        if ($intent['action'] !== 'chat') {
            $activityResult = $this->handleActivityIntent($intent, $detectedLang);
            if ($activityResult !== null) {
                return $activityResult;
            }
        }

        // Inject server-side language hint into messages
        $this->injectLanguageHint($data);

        // ── Strategy 1: Python Chat Service (Ollama) ───────────────────
        $result = $this->callPythonChat($data);
        if ($result !== null) {
            return $result;
        }

        // ── Strategy 2: Gemini API fallback ────────────────────────────
        if (!empty($this->geminiApiKey)) {
            $result = $this->callGemini($data);
            if ($result !== null) {
                return $result;
            }
        }

        // ── All backends failed ────────────────────────────────────────
        return new JsonResponse([
            'error' => [
                'message' => 'All chat backends are unavailable. Please ensure the Python chat service is running (python python/chat_service.py).',
                'type' => 'server_error',
                'code' => 503
            ]
        ], 503);
    }

    // =====================================================================
    //  Backend: Python Chat Service (Ollama)
    // =====================================================================

    private function callPythonChat(array $data): ?JsonResponse
    {
        $ch = curl_init($this->pythonChatUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);     // LLMs can be slow
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // fast fail if service is down

        $responseData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch) || $httpCode === 0) {
            curl_close($ch);
            return null; // service unreachable — fall through to next backend
        }
        curl_close($ch);

        $decoded = json_decode($responseData, true);

        // If the Python service returned an error (Ollama not running, etc.), fall through
        if ($httpCode >= 500 || isset($decoded['error'])) {
            return null;
        }

        return new JsonResponse($decoded, $httpCode);
    }

    // =====================================================================
    //  Backend: Gemini API (fallback)
    // =====================================================================

    private function callGemini(array $data): ?JsonResponse
    {
        // Convert OpenAI-format to Gemini format
        $systemText = '';
        $conversationMessages = [];

        foreach ($data['messages'] ?? [] as $msg) {
            $role = $msg['role'] ?? '';
            $content = is_array($msg['content'] ?? null)
                ? ($msg['content'][0]['text'] ?? json_encode($msg['content']))
                : ($msg['content'] ?? '');

            if ($role === 'system') {
                $systemText .= $content;
            } else {
                $geminiRole = ($role === 'assistant') ? 'model' : 'user';
                $conversationMessages[] = [
                    'role' => $geminiRole,
                    'parts' => [['text' => $content]]
                ];
            }
        }

        // Ensure starts with "user"
        if (!empty($conversationMessages) && $conversationMessages[0]['role'] === 'model') {
            array_shift($conversationMessages);
        }

        // Merge consecutive same-role messages
        $merged = [];
        foreach ($conversationMessages as $msg) {
            if (!empty($merged) && end($merged)['role'] === $msg['role']) {
                $i = count($merged) - 1;
                $merged[$i]['parts'][0]['text'] .= "\n" . $msg['parts'][0]['text'];
            } else {
                $merged[] = $msg;
            }
        }

        $payload = [
            'contents' => $merged,
            'generationConfig' => [
                'temperature' => (float)($data['temperature'] ?? 0.7),
                'maxOutputTokens' => (int)($data['max_tokens'] ?? 2048),
            ]
        ];
        if (!empty($systemText)) {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemText]]];
        }

        // Try primary model, then fallbacks
        $models = [$this->geminiModel, 'gemini-2.0-flash-lite', 'gemini-1.5-flash'];
        foreach ($models as $model) {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $this->geminiApiKey;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $respData = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 200) {
                $gemini = json_decode($respData, true);
                if (isset($gemini['candidates'][0]['content']['parts'][0]['text'])) {
                    return new JsonResponse([
                        'id' => 'gemini-' . uniqid(),
                        'object' => 'chat.completion',
                        'created' => time(),
                        'model' => $model,
                        'choices' => [[
                            'index' => 0,
                            'message' => [
                                'role' => 'assistant',
                                'content' => $gemini['candidates'][0]['content']['parts'][0]['text']
                            ],
                            'finish_reason' => 'stop'
                        ]],
                        'usage' => [
                            'prompt_tokens' => $gemini['usageMetadata']['promptTokenCount'] ?? 0,
                            'completion_tokens' => $gemini['usageMetadata']['candidatesTokenCount'] ?? 0,
                            'total_tokens' => $gemini['usageMetadata']['totalTokenCount'] ?? 0,
                        ]
                    ], 200);
                }
            }
        }
        return null; // all Gemini models failed
    }

    // =====================================================================
    //  Helpers
    // =====================================================================

    private function injectLanguageHint(array &$data): void
    {
        if (!isset($data['messages']) || !is_array($data['messages'])) {
            return;
        }

        // Find latest user message for language detection
        $lastUserMsg = '';
        foreach (array_reverse($data['messages']) as $msg) {
            if (($msg['role'] ?? '') === 'user') {
                $lastUserMsg = is_array($msg['content']) ? ($msg['content'][0]['text'] ?? '') : ($msg['content'] ?? '');
                break;
            }
        }
        $detectedLang = $this->detectMessageLanguage($lastUserMsg);
        $langHint = "\n\n=== SERVER LANGUAGE HINT ===\nDetected language: {$detectedLang}. Reply in this language/dialect.";

        // Append to existing system message or create one
        $found = false;
        foreach ($data['messages'] as &$msg) {
            if (($msg['role'] ?? '') === 'system') {
                $msg['content'] .= $langHint;
                $found = true;
                break;
            }
        }
        unset($msg);

        if (!$found) {
            array_unshift($data['messages'], [
                'role' => 'system',
                'content' => "You are Nexus, the WANNASNI AI assistant. Understand English, French, Arabic, and Tunisian dialect. Always reply in the same language the user writes in." . $langHint
            ]);
        }
    }

    // =====================================================================
    //  Activity Intent Detection & Handling (Alexa/Siri-style commands)
    // =====================================================================

    private function detectActivityIntent(string $text): array
    {
        $t = mb_strtolower(trim($text));
        if (empty($t)) return ['action' => 'chat', 'activityName' => ''];

        // ── My activities / schedule ──
        $myPatterns = [
            '/\b(my activit|my schedule|what am i (in|registered|enrolled|doing)|i\'?m (in|registered|signed)|my events|what (do i have|have i joined)|which activities)/iu',
            '/(mes activit|mon (emploi|calendrier|planning)|je suis inscri|mes inscri|auxquelles|mes événements|qu.?est.?ce que j.?ai|quelles? activit.* inscri|mes cours)/iu',
            '/(أنشطتي|مسجل في|جدولي|ما هي أنشطتي|فيم أنا مسجل)/u',
            '/(نشاطاتي|شو اشتركت فيه|مسجل فيهم|famma achniya|chny3mlu|chno 3andi inscrit)/iu',
        ];
        foreach ($myPatterns as $pat) {
            if (preg_match($pat, $t)) return ['action' => 'list_my_activities', 'activityName' => ''];
        }

        // ── Join / enroll ──
        $joinPatterns = [
            '/\b(join|enroll|register|sign.?(?:me\s*)?up|participate|book|add me|i want to (?:join|do|try))/iu',
            '/(inscri[st]?|rejoind|particip|réserv|ajout.?\s*moi|je (?:veux|voudrais) (?:m.?inscrire|participer|rejoindre|faire))/iu',
            '/(انضم|سجل|اشترك|احجز|أريد الانضمام|سجلني)/u',
            '/(نحب نسجل|باهي نشارك|حب يشارك|inscri-ni|nheb nsajel|bahi nchark)/iu',
        ];
        foreach ($joinPatterns as $pat) {
            if (preg_match($pat, $t)) {
                return ['action' => 'join_activity', 'activityName' => $this->extractActivityNameFromChat($t, 'join')];
            }
        }

        // ── Cancel ──
        $cancelPatterns = [
            '/\b(cancel|unsubscribe|leave|quit|drop|remove|unregister|i (?:don.?t|no longer) want)/iu',
            '/(annul|désinscrire|quitt|retire|supprim|je (?:ne )?(?:veux|voudrais) plus)/iu',
            '/(الغ|حذف|غادر|اسحب|ألغِ|لا أريد)/u',
            '/(الغيلي|ماحبتش|حذفها|7thef|alghili|mech 7abi)/iu',
        ];
        foreach ($cancelPatterns as $pat) {
            if (preg_match($pat, $t)) {
                return ['action' => 'cancel_activity', 'activityName' => $this->extractActivityNameFromChat($t, 'cancel')];
            }
        }

        // ── List available ──
        $listPatterns = [
            '/(show|list|see|view|available|what.?s (?:available|there)|find|browse|which).{0,15}(activit|event|class|session|cours)/iu',
            '/(activit|event|class|session|cours).{0,15}(available|show|list|see|view|find|browse)/iu',
            '/(affich|montr|voir|list|disponible).{0,15}(activit|événement|cours|séance)/iu',
            '/(activit|événement|cours|séance).{0,15}(disponible|affich|montr|voir|list)/iu',
            '/(اعرض|قائمة|ماذا يوجد|متاح).{0,10}(نشاط|أنشط|فعالي)/u',
            '/(نشاط|أنشط|فعالي).{0,10}(اعرض|قائمة|متاح|أظهر)/u',
            '/(عرضلي|شو فامة|3radli|chno famma).{0,15}(activit|نشاط)/iu',
            '/(show me activities|list activities|available activities|what activities)/iu',
            '/(montre.?moi les activités|activités disponibles|quelles activités)/iu',
        ];
        foreach ($listPatterns as $pat) {
            if (preg_match($pat, $t)) return ['action' => 'list_available', 'activityName' => ''];
        }

        return ['action' => 'chat', 'activityName' => ''];
    }

    private function extractActivityNameFromChat(string $text, string $type): string
    {
        $cleaned = mb_strtolower(trim($text));

        // Remove filler
        $cleaned = preg_replace('/\b(please|can you|could you|i want to|i\'?d like to|for me|me for|me to|me in|s\'?il (te|vous) (plait|plaît))\b/iu', '', $cleaned);

        if ($type === 'join') {
            $cleaned = preg_replace('/\b(join|enroll|register|sign\s*(?:me\s*)?up|participate\s*in|book|add me to)\b/iu', '', $cleaned);
            $cleaned = preg_replace('/\b(inscri[st]?.?\s*moi\s*(?:au|à|pour|dans)?|rejoindre|participer\s*(?:à|au|dans)?|réserver|ajoute.?\s*moi\s*(?:au|à|dans)?|je\s*(?:veux|voudrais)\s*(?:m.?inscrire|participer|rejoindre)\s*(?:au|à|pour)?)\b/iu', '', $cleaned);
            $cleaned = preg_replace('/(انضم\s*(?:إلى|ل|في)?|سجلني\s*(?:في|ل)?|اشترك\s*(?:في|ل)?|احجز)/u', '', $cleaned);
            $cleaned = preg_replace('/(نحب نسجل\s*(?:في|ل)?|باهي نشارك\s*(?:في)?|inscri-ni\s*(?:au|à|dans)?|nheb nsajel\s*(?:fi|l)?)/iu', '', $cleaned);
        }
        if ($type === 'cancel') {
            $cleaned = preg_replace('/\b(cancel|unsubscribe\s*from|leave|quit|drop|remove\s*me\s*from|unregister\s*from)\b/iu', '', $cleaned);
            $cleaned = preg_replace('/\b(annule?r?|désinscrire?\s*(?:de|du)?|quitter?|retire.?\s*moi\s*(?:de|du)?|supprime?r?|je\s*(?:ne\s*)?(?:veux|voudrais)\s*plus)\b/iu', '', $cleaned);
            $cleaned = preg_replace('/(الغ|حذف|غادر|اسحب|ألغِ)\s*(?:من|في)?/u', '', $cleaned);
        }

        // Remove leftover articles/prepositions
        $cleaned = preg_replace('/\b(le|la|les|l\'|du|de|des|au|aux|un|une|mon|ma|mes|my|the|a|an|for|from|in|to|i|me)\b/iu', '', $cleaned);
        return trim(preg_replace('/\s+/', ' ', $cleaned));
    }

    private function handleActivityIntent(array $intent, string $lang): ?JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        $locale = match($lang) { 'ar', 'tn-ar' => 'ar', 'en' => 'en', default => 'fr' };

        switch ($intent['action']) {
            case 'list_available':
                return $this->chatListAvailable($user, $lang, $locale);
            case 'list_my_activities':
                if (!$user instanceof User) {
                    return $this->activityTextResponse($this->t($lang, 'Please log in to see your activities.', 'Connectez-vous pour voir vos activités.', 'يرجى تسجيل الدخول لرؤية أنشطتك.'));
                }
                return $this->chatListMine($user, $lang, $locale);
            case 'join_activity':
                if (!$user instanceof User) {
                    return $this->activityTextResponse($this->t($lang, 'Please log in to join an activity.', 'Connectez-vous pour rejoindre une activité.', 'يرجى تسجيل الدخول للانضمام إلى نشاط.'));
                }
                return $this->chatJoinActivity($user, $intent['activityName'], $lang, $locale);
            case 'cancel_activity':
                if (!$user instanceof User) {
                    return $this->activityTextResponse($this->t($lang, 'Please log in to cancel an activity.', 'Connectez-vous pour annuler une activité.', 'يرجى تسجيل الدخول لإلغاء نشاط.'));
                }
                return $this->chatCancelActivity($user, $intent['activityName'], $lang, $locale);
            default:
                return null;
        }
    }

    private function chatListAvailable(?User $user, string $lang, string $locale): JsonResponse
    {
        $activities = $this->activityRepository->findBy(['isActive' => true], ['startTime' => 'ASC']);

        if (empty($activities)) {
            return $this->activityTextResponse($this->t($lang, 'There are no activities available right now.', 'Il n\'y a pas d\'activités disponibles pour le moment.', 'لا توجد أنشطة متاحة حالياً.'));
        }

        $enrolledIds = [];
        if ($user instanceof User) {
            $participations = $this->participationRepository->findBy(['seniorId' => $user->getId()]);
            $enrolledIds = array_map(
                fn($p) => $p->getActivityId(),
                array_filter($participations, fn($p) => !in_array($p->getStatus(), ['annulé', 'cancelled']))
            );
        }

        $actList = [];
        foreach ($activities as $a) {
            $actList[] = [
                'id' => $a->getId(),
                'title' => $a->getTitle(),
                'type' => $a->getType(),
                'startTime' => $a->getStartTime()?->format('Y-m-d H:i'),
                'location' => $a->getLocation(),
                'description' => $a->getDescription(),
                'spots' => max(0, ($a->getMaxParticipants() ?? 999) - $a->getCurrentParticipants()),
                'isFull' => $a->isFull(),
                'enrolled' => in_array($a->getId(), $enrolledIds),
            ];
        }

        $text = $this->t($lang,
            'Here are the available activities (' . count($actList) . '):',
            'Voici les activités disponibles (' . count($actList) . ') :',
            'إليك الأنشطة المتاحة (' . count($actList) . '):'
        );

        return new JsonResponse([
            'id' => 'activity-' . uniqid(),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'activity-engine',
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => $text],
                'finish_reason' => 'stop'
            ]],
            'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            'activity_data' => [
                'type' => 'list_available',
                'activities' => $actList,
                'locale' => $locale,
            ]
        ]);
    }

    private function chatListMine(User $user, string $lang, string $locale): JsonResponse
    {
        $participations = $this->participationRepository->findBy(['seniorId' => $user->getId()]);
        $active = array_filter($participations, fn($p) => !in_array($p->getStatus(), ['annulé', 'cancelled']));

        if (empty($active)) {
            return $this->activityTextResponse($this->t($lang,
                'You are not enrolled in any activities. Say "show activities" to see what\'s available!',
                'Vous n\'êtes inscrit(e) à aucune activité. Dites « montre les activités » pour voir ce qui est disponible !',
                'لست مسجلاً في أي نشاط. قل "اعرض الأنشطة" لرؤية ما هو متاح!'
            ));
        }

        $actList = [];
        foreach ($active as $p) {
            $activity = $this->activityRepository->find($p->getActivityId());
            if (!$activity) continue;
            $actList[] = [
                'id' => $activity->getId(),
                'title' => $activity->getTitle(),
                'type' => $activity->getType(),
                'startTime' => $activity->getStartTime()?->format('Y-m-d H:i'),
                'location' => $activity->getLocation(),
                'description' => $activity->getDescription(),
                'enrolled' => true,
                'participationStatus' => $p->getStatus(),
            ];
        }

        $text = $this->t($lang,
            'You are enrolled in ' . count($actList) . ' activit' . (count($actList) > 1 ? 'ies' : 'y') . ':',
            'Vous êtes inscrit(e) à ' . count($actList) . ' activité' . (count($actList) > 1 ? 's' : '') . ' :',
            'أنت مسجل في ' . count($actList) . ' نشاط' . (count($actList) > 1 ? 'ات' : '') . ':'
        );

        return new JsonResponse([
            'id' => 'activity-' . uniqid(),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'activity-engine',
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => $text],
                'finish_reason' => 'stop'
            ]],
            'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            'activity_data' => [
                'type' => 'list_mine',
                'activities' => $actList,
                'locale' => $locale,
            ]
        ]);
    }

    private function chatJoinActivity(User $user, string $activityName, string $lang, string $locale): JsonResponse
    {
        // If no activity name provided, show available activities to pick from
        if (empty(trim($activityName))) {
            $activities = $this->activityRepository->findBy(['isActive' => true], ['startTime' => 'ASC']);
            $available = [];
            $enrolledIds = array_map(
                fn($p) => $p->getActivityId(),
                array_filter(
                    $this->participationRepository->findBy(['seniorId' => $user->getId()]),
                    fn($p) => !in_array($p->getStatus(), ['annulé', 'cancelled'])
                )
            );
            foreach ($activities as $a) {
                if (!in_array($a->getId(), $enrolledIds) && !$a->isFull()) {
                    $available[] = [
                        'id' => $a->getId(),
                        'title' => $a->getTitle(),
                        'type' => $a->getType(),
                        'startTime' => $a->getStartTime()?->format('Y-m-d H:i'),
                        'location' => $a->getLocation(),
                        'spots' => max(0, ($a->getMaxParticipants() ?? 999) - $a->getCurrentParticipants()),
                        'enrolled' => false,
                    ];
                }
            }
            $text = $this->t($lang,
                'Which activity would you like to join? Tap one below or say its name:',
                'Quelle activité voulez-vous rejoindre ? Cliquez ci-dessous ou dites son nom :',
                'أي نشاط تريد الانضمام إليه؟ اضغط أدناه أو قل اسمه:'
            );
            return new JsonResponse([
                'id' => 'activity-' . uniqid(),
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'activity-engine',
                'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $text], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                'activity_data' => ['type' => 'pick_to_join', 'activities' => $available, 'locale' => $locale],
            ]);
        }

        // Find matching activity
        $activities = $this->activityRepository->findBy(['isActive' => true]);
        $matched = $this->findBestMatch($activityName, $activities);

        if (!$matched) {
            return $this->activityTextResponse($this->t($lang,
                "I couldn't find an activity matching \"$activityName\". Say \"show activities\" to see what's available.",
                "Je n'ai pas trouvé d'activité correspondant à \"$activityName\". Dites « montre les activités » pour voir les disponibles.",
                "لم أجد نشاطاً يطابق \"$activityName\". قل \"اعرض الأنشطة\" لرؤية المتاح."
            ));
        }

        if ($matched->isFull()) {
            return $this->activityTextResponse($this->t($lang,
                "Sorry, \"{$matched->getTitle()}\" is full.",
                "Désolé, \"{$matched->getTitle()}\" est complet.",
                "عذراً، نشاط \"{$matched->getTitle()}\" ممتلئ."
            ));
        }

        // Check existing participation
        $existing = $this->participationRepository->findOneBy([
            'seniorId' => $user->getId(),
            'activity' => $matched->getId()
        ]);

        if ($existing && !in_array($existing->getStatus(), ['annulé', 'cancelled'])) {
            return $this->activityTextResponse($this->t($lang,
                "You're already enrolled in \"{$matched->getTitle()}\".",
                "Vous êtes déjà inscrit(e) à \"{$matched->getTitle()}\".",
                "أنت مسجل بالفعل في \"{$matched->getTitle()}\"."
            ));
        }

        // Reactivate cancelled or create new participation
        if ($existing) {
            $existing->setStatus('inscrit');
            $existing->setRegisteredAt(new \DateTime());
        } else {
            $participation = new Participation();
            $participation->setActivity($matched);
            $participation->setSeniorId($user->getId());
            $participation->setStatus('inscrit');
            $participation->setTitle($matched->getTitle());
            $participation->setRegistrationMethod('chat_assistant');
            $participation->setRegisteredAt(new \DateTime());
            $this->entityManager->persist($participation);
        }
        $this->entityManager->flush();

        $dateStr = $matched->getStartTime()?->format('d/m/Y H:i') ?? '';
        $text = $this->t($lang,
            "Done! You're now enrolled in **{$matched->getTitle()}** ({$dateStr}). 🎉",
            "C'est fait ! Vous êtes inscrit(e) à **{$matched->getTitle()}** ({$dateStr}). 🎉",
            "تم! أنت الآن مسجل في **{$matched->getTitle()}** ({$dateStr}). 🎉"
        );

        return new JsonResponse([
            'id' => 'activity-' . uniqid(),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'activity-engine',
            'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $text], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            'activity_data' => [
                'type' => 'join_success',
                'activity' => ['id' => $matched->getId(), 'title' => $matched->getTitle()],
                'locale' => $locale,
            ]
        ]);
    }

    private function chatCancelActivity(User $user, string $activityName, string $lang, string $locale): JsonResponse
    {
        // If no name, show enrolled activities to pick from
        if (empty(trim($activityName))) {
            $participations = $this->participationRepository->findBy(['seniorId' => $user->getId()]);
            $active = array_filter($participations, fn($p) => !in_array($p->getStatus(), ['annulé', 'cancelled']));
            $actList = [];
            foreach ($active as $p) {
                $act = $this->activityRepository->find($p->getActivityId());
                if (!$act) continue;
                $actList[] = [
                    'id' => $act->getId(),
                    'title' => $act->getTitle(),
                    'type' => $act->getType(),
                    'startTime' => $act->getStartTime()?->format('Y-m-d H:i'),
                    'location' => $act->getLocation(),
                    'enrolled' => true,
                ];
            }
            if (empty($actList)) {
                return $this->activityTextResponse($this->t($lang,
                    "You're not enrolled in any activities.",
                    "Vous n'êtes inscrit(e) à aucune activité.",
                    "لست مسجلاً في أي نشاط."
                ));
            }
            $text = $this->t($lang,
                'Which activity do you want to cancel? Tap one below:',
                'Quelle activité voulez-vous annuler ? Cliquez ci-dessous :',
                'أي نشاط تريد إلغاءه؟ اضغط أدناه:'
            );
            return new JsonResponse([
                'id' => 'activity-' . uniqid(),
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'activity-engine',
                'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $text], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                'activity_data' => ['type' => 'pick_to_cancel', 'activities' => $actList, 'locale' => $locale],
            ]);
        }

        // Find the matching enrolled activity
        $participations = $this->participationRepository->findBy(['seniorId' => $user->getId()]);
        $activeP = array_filter($participations, fn($p) => !in_array($p->getStatus(), ['annulé', 'cancelled']));
        $matchedP = null;
        $matchedTitle = '';
        $actNameLower = mb_strtolower(trim($activityName));

        foreach ($activeP as $p) {
            $act = $this->activityRepository->find($p->getActivityId());
            if ($act && mb_stripos($act->getTitle(), $actNameLower) !== false) {
                $matchedP = $p;
                $matchedTitle = $act->getTitle();
                break;
            }
        }

        // Fuzzy match fallback
        if (!$matchedP) {
            $bestScore = 0;
            foreach ($activeP as $p) {
                $act = $this->activityRepository->find($p->getActivityId());
                if (!$act) continue;
                $score = 0;
                similar_text(mb_strtolower($act->getTitle()), $actNameLower, $score);
                if ($score > $bestScore && $score > 40) {
                    $bestScore = $score;
                    $matchedP = $p;
                    $matchedTitle = $act->getTitle();
                }
            }
        }

        if (!$matchedP) {
            return $this->activityTextResponse($this->t($lang,
                "I couldn't find \"$activityName\" in your enrolled activities.",
                "Je n'ai pas trouvé \"$activityName\" dans vos inscriptions.",
                "لم أجد \"$activityName\" في أنشطتك المسجلة."
            ));
        }

        $matchedP->setStatus('annulé');
        $this->entityManager->flush();

        $text = $this->t($lang,
            "Your enrollment in **{$matchedTitle}** has been cancelled. ✅",
            "Votre inscription à **{$matchedTitle}** a été annulée. ✅",
            "تم إلغاء تسجيلك في **{$matchedTitle}**. ✅"
        );

        return new JsonResponse([
            'id' => 'activity-' . uniqid(),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'activity-engine',
            'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $text], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            'activity_data' => [
                'type' => 'cancel_success',
                'activity' => ['title' => $matchedTitle],
                'locale' => $locale,
            ]
        ]);
    }

    // ── Activity API endpoints (for chat action buttons) ───────────────

    #[Route('/activities/join/{id}', name: 'app_chat_join_activity', methods: ['POST'])]
    public function apiJoinActivity(int $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
        }

        $activity = $this->activityRepository->find($id);
        if (!$activity || !$activity->isActive()) {
            return new JsonResponse(['success' => false, 'error' => 'Activity not found'], 404);
        }
        if ($activity->isFull()) {
            return new JsonResponse(['success' => false, 'error' => 'Activity is full'], 400);
        }

        $existing = $this->participationRepository->findOneBy([
            'seniorId' => $user->getId(),
            'activity' => $id
        ]);

        if ($existing && !in_array($existing->getStatus(), ['annulé', 'cancelled'])) {
            return new JsonResponse(['success' => false, 'error' => 'Already enrolled'], 400);
        }

        if ($existing) {
            $existing->setStatus('inscrit');
            $existing->setRegisteredAt(new \DateTime());
        } else {
            $p = new Participation();
            $p->setActivity($activity);
            $p->setSeniorId($user->getId());
            $p->setStatus('inscrit');
            $p->setTitle($activity->getTitle());
            $p->setRegistrationMethod('chat_assistant');
            $p->setRegisteredAt(new \DateTime());
            $this->entityManager->persist($p);
        }
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Enrolled in ' . $activity->getTitle(),
            'activity' => ['id' => $activity->getId(), 'title' => $activity->getTitle()]
        ]);
    }

    #[Route('/activities/cancel/{id}', name: 'app_chat_cancel_activity', methods: ['POST'])]
    public function apiCancelActivity(int $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
        }

        $participation = $this->participationRepository->findOneBy([
            'seniorId' => $user->getId(),
            'activity' => $id,
        ]);

        if (!$participation || in_array($participation->getStatus(), ['annulé', 'cancelled'])) {
            return new JsonResponse(['success' => false, 'error' => 'Not enrolled'], 404);
        }

        $participation->setStatus('annulé');
        $this->entityManager->flush();

        $activity = $this->activityRepository->find($id);
        return new JsonResponse([
            'success' => true,
            'message' => 'Cancelled ' . ($activity ? $activity->getTitle() : 'activity'),
        ]);
    }

    // ── Helpers for activity responses ──────────────────────────────────

    private function findBestMatch(string $search, array $activities): ?object
    {
        $search = mb_strtolower(trim($search));
        // Exact substring first
        foreach ($activities as $a) {
            if (mb_stripos($a->getTitle(), $search) !== false) return $a;
        }
        // Fuzzy
        $best = null;
        $bestScore = 0;
        foreach ($activities as $a) {
            $score = 0;
            similar_text(mb_strtolower($a->getTitle()), $search, $score);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $a;
            }
        }
        return $bestScore > 40 ? $best : null;
    }

    private function t(string $lang, string $en, string $fr, string $ar): string
    {
        return match(true) {
            str_starts_with($lang, 'ar'), str_starts_with($lang, 'tn') => $ar,
            $lang === 'en' => $en,
            default => $fr,
        };
    }

    private function activityTextResponse(string $text): JsonResponse
    {
        return new JsonResponse([
            'id' => 'activity-' . uniqid(),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'activity-engine',
            'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $text], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
        ]);
    }

    /** Fields that must never be returned to the chat client */
    private const SENSITIVE_FIELDS = [
        'password', 'reset_token', 'reset_token_expires_at',
        'verification_code', 'face_encoding', 'face_image_path', 'face_consent_at',
    ];

    private function sanitizeRows(array $rows): array
    {
        return array_map(function (array $row) {
            foreach (self::SENSITIVE_FIELDS as $field) {
                unset($row[$field]);
            }
            return $row;
        }, $rows);
    }

    #[Route('/db-query', name: 'app_chat_db_query', methods: ['POST'])]
    public function databaseQuery(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? '';
        
        try {
            switch ($action) {
                case 'get_tables':
                    $result = $this->connection->executeQuery(
                        "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()"
                    )->fetchAllAssociative();
                    $tables = array_column($result, 'table_name');
                    return new JsonResponse(['success' => true, 'data' => $tables]);
                
                case 'get_schema':
                    $tables = $this->connection->executeQuery(
                        "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()"
                    )->fetchAllAssociative();
                    $schema = [];
                    foreach ($tables as $table) {
                        $columns = $this->connection->executeQuery(
                            "SELECT column_name as name, column_type as type, column_key as `key` 
                             FROM information_schema.columns 
                             WHERE table_schema = DATABASE() AND table_name = ?",
                            [$table['table_name']]
                        )->fetchAllAssociative();
                        $schema[$table['table_name']] = $columns;
                    }
                    return new JsonResponse(['success' => true, 'data' => $schema]);
                
                case 'get_table_data':
                    $table = $data['table'] ?? '';
                    if (!preg_match('/^[a-z_]+$/', $table)) {
                        throw new \Exception('Invalid table name');
                    }
                    $result = $this->connection->executeQuery("SELECT * FROM `{$table}` LIMIT 100")->fetchAllAssociative();
                    return new JsonResponse(['success' => true, 'data' => $this->sanitizeRows($result)]);
                
                case 'query':
                case 'execute':
                    $sql = $data['sql'] ?? '';
                    $sqlLower = strtolower(trim($sql));
                    
                    // Security: Only allow SELECT queries
                    if (!str_contains($sqlLower, 'select')) {
                        throw new \Exception('Only SELECT queries are allowed');
                    }
                    if (str_contains($sqlLower, 'drop') || str_contains($sqlLower, 'delete') || 
                        str_contains($sqlLower, 'insert') || str_contains($sqlLower, 'update') ||
                        str_contains($sqlLower, 'truncate')) {
                        throw new \Exception('Destructive queries are not allowed');
                    }
                    
                    $result = $this->connection->executeQuery($sql)->fetchAllAssociative();
                    return new JsonResponse(['success' => true, 'data' => $this->sanitizeRows($result)]);
                
                default:
                    throw new \Exception('Unknown action: ' . $action);
            }
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/profile-update', name: 'app_chat_profile_update', methods: ['POST'])]
    public function profileUpdate(Request $request): JsonResponse
    {
        $userInterface = $this->getUser();
        if (!$userInterface) {
            return new JsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
        }

        /** @var User $user */
        $user = $this->userRepository->findOneBy(['email' => $userInterface->getUserIdentifier()]);
        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'User not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['fields']) || !is_array($data['fields'])) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid payload'], 400);
        }

        $fields = $data['fields'];
        $updated = [];

        $allowedFields = [
            'firstName', 'lastName', 'phone', 'dateNaissance',
            'adresse', 'ville', 'codePostal', 'pays', 'location'
        ];

        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $fields)) continue;
            $value = trim((string) $fields[$field]);
            if ($value === '') continue;

            switch ($field) {
                case 'firstName':    $user->setFirstName($value);    break;
                case 'lastName':     $user->setLastName($value);     break;
                case 'phone':        $user->setPhone($value);        break;
                case 'dateNaissance':
                    try {
                        $user->setDateNaissance(new \DateTime($value));
                    } catch (\Exception $e) { continue 2; }
                    break;
                case 'adresse':      $user->setAdresse($value);      break;
                case 'ville':        $user->setVille($value);        break;
                case 'codePostal':   $user->setCodePostal($value);   break;
                case 'pays':         $user->setPays($value);         break;
                case 'location':     $user->setLocation($value);     break;
            }
            $updated[] = $field;
        }

        if (empty($updated)) {
            return new JsonResponse(['success' => false, 'error' => 'No valid fields provided']);
        }

        $this->entityManager->flush();

        // Compute remaining missing fields after update
        $profileFields = [
            'firstName'    => $user->getFirstName(),
            'lastName'     => $user->getLastName(),
            'phone'        => $user->getPhone(),
            'dateNaissance'=> $user->getDateNaissance()?->format('Y-m-d'),
            'adresse'      => $user->getAdresse(),
            'ville'        => $user->getVille(),
            'codePostal'   => $user->getCodePostal(),
            'pays'         => $user->getPays(),
            'location'     => $user->getLocation(),
        ];
        $missing = array_keys(array_filter($profileFields, fn($v) => empty($v)));
        $complete = empty($missing);

        return new JsonResponse([
            'success'  => true,
            'updated'  => $updated,
            'missing'  => $missing,
            'complete' => $complete,
        ]);
    }

    /**
     * Lightweight language/dialect detection for the latest user message.
     * Returns one of: en, fr, ar, tn-ar (Tunisian Arabic script), tn-latn (Tunisian Latin/Franco)
     */
    private function detectMessageLanguage(string $text): string
    {
        if (empty($text)) return 'fr';

        $t = mb_strtolower(trim($text));

        // Tunisian Latin/Franco keywords (check before Arabic)
        $tunLatinWords = ['chnowa', 'kifech', 'nheb', 'wqtesh', 'bahi', 'barcha', 'moch', '3andi',
                          'ya kho', 'roh l', 'b3id', 'hna ', 'hedha', 'famma', 'yezzi', 'chwaya',
                          'n7eb', 'w9tesh', 'ki7alou', 'taw', 'ahna', 'ena', 'inti'];
        foreach ($tunLatinWords as $word) {
            if (str_contains($t, $word)) return 'tn-latn';
        }

        // Arabic script detection (Tunisian darija or MSA)
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            // Tunisian-specific Arabic words
            if (preg_match('/(\u0634\u0646\u0648\u0627|\u0643\u064a\u0641\u0627\u0634|\u0646\u062d\u0628|\u0628\u0627\u0647\u064a|\u0628\u0631\u0634\u0627|\u0645\u0648\u0634|\u064a\u0632\u064a|\u062e\u0630\u0646\u064a|\u0631\u0648\u062d|\u062c\u064a|\u0647\u0630\u0627\u0643\u0627|\u0641\u0645\u0629|\u0634\u0648\u064a\u0629|\u062a\u0627\u0648)/u', $text)) {
                return 'tn-ar';
            }
            return 'ar';
        }

        // French keywords
        $frWords = ['je', 'le', 'la', 'les', 'des', 'mon', 'mes', 'bonjour', 'salut', 'comment',
                    'quoi', 'aller', 'voudrais', 'pouvez', 'activit', 'sant', 'merci', 'oui', 'non'];
        $frScore = 0;
        foreach ($frWords as $w) { if (str_contains($t, $w)) $frScore++; }

        // English keywords
        $enWords = ['i', 'the', 'my', 'me', 'you', 'what', 'how', 'can', 'show', 'help',
                    'take me', 'go to', 'open', 'navigate', 'please', 'hello', 'thank'];
        $enScore = 0;
        foreach ($enWords as $w) { if (str_contains($t, $w)) $enScore++; }

        if ($frScore > $enScore) return 'fr';
        if ($enScore > 0) return 'en';
        return 'fr'; // default to French (app default locale)
    }

    #[Route('/user-context', name: 'app_chat_user_context', methods: ['GET'])]
    public function getUserContext(): JsonResponse
    {
        $userInterface = $this->getUser();
        
        if (!$userInterface) {
            return new JsonResponse([
                'success' => false,
                'error' => 'User not logged in'
            ]);
        }

        // Get actual User entity from database
        $user = $this->connection->executeQuery(
            'SELECT * FROM user WHERE email = ?',
            [$userInterface->getUserIdentifier()]
        )->fetchAssociative();

        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'User not found']);
        }

        // Get user activity summary
        $userId = $user['id'];
        
        $healthEntries = $this->connection->executeQuery(
            'SELECT COUNT(*) as count FROM health_journal WHERE senior_id = ?',
            [$userId]
        )->fetchOne() ?? 0;
        
        $participations = $this->connection->executeQuery(
            'SELECT COUNT(*) as count FROM participations WHERE senior_id = ?',
            [$userId]
        )->fetchOne() ?? 0;
        
        $dietRequests = $this->connection->executeQuery(
            'SELECT COUNT(*) as count FROM demande_regime WHERE user_id = ?',
            [$userId]
        )->fetchOne() ?? 0;
        
        $prescribedDiets = $this->connection->executeQuery(
            'SELECT COUNT(*) as count FROM regime_prescrit WHERE user_id = ?',
            [$userId]
        )->fetchOne() ?? 0;
        
        $serviceRequests = $this->connection->executeQuery(
            'SELECT COUNT(*) as count FROM service_request WHERE user_id = ?',
            [$userId]
        )->fetchOne() ?? 0;
        
        $treatments = $this->connection->executeQuery(
            'SELECT COUNT(*) as count FROM treatment WHERE senior_id = ?',
            [$userId]
        )->fetchOne() ?? 0;
        
        $unreadNotifications = $this->connection->executeQuery(
            'SELECT COUNT(*) as count FROM notification WHERE is_read = 0'
        )->fetchOne() ?? 0;

        return new JsonResponse([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'roles' => json_decode($user['roles'], true),
                'status' => $user['status'],
                'ville' => $user['ville'],
                'pays' => $user['pays'],
                'adresse' => $user['adresse'],
                'code_postal' => $user['code_postal'],
                'location' => $user['location'],
                'date_naissance' => $user['date_naissance'],
                'user_domain' => $user['user_domain'],
                'created_at' => $user['created_at'],
            ],
            'summary' => [
                'health_entries' => (int)$healthEntries,
                'participations' => (int)$participations,
                'diet_requests' => (int)$dietRequests,
                'prescribed_diets' => (int)$prescribedDiets,
                'service_requests' => (int)$serviceRequests,
                'treatments' => (int)$treatments,
                'unread_notifications' => (int)$unreadNotifications,
            ]
        ]);
    }
}