<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\Participation;
use App\Repository\ActivityRepository;
use App\Repository\ParticipationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/api', name: 'api_')]
class ChatController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private ActivityRepository $activityRepository,
        private ParticipationRepository $participationRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/voice-assistant', name: 'voice_assistant', methods: ['POST'])]
    public function voiceAssistant(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'User not authenticated'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $message = $data['message'] ?? '';
        $lang = $data['lang'] ?? $request->getLocale();
        $action = $data['action'] ?? 'chat';
        $activityName = $data['activityName'] ?? '';

        if (empty($message)) {
            return $this->json(['error' => 'Empty message'], 400);
        }

        // If client sent 'chat' (fallback), do server-side intent detection
        if ($action === 'chat') {
            $detected = $this->detectIntent($message);
            $action = $detected['action'];
            if (empty($activityName)) {
                $activityName = $detected['activityName'];
            }
        }

        try {
            switch ($action) {
                case 'join_activity':
                    return $this->joinActivity($user, !empty($activityName) ? $activityName : $message, $lang);
                case 'cancel_activity':
                    return $this->cancelActivity($user, !empty($activityName) ? $activityName : $message, $lang);
                case 'list_my_activities':
                    return $this->listUserActivities($user, $lang);
                case 'list_available':
                    return $this->listAvailableActivities($user, $lang);
                case 'navigate':
                    return $this->navigateResponse($message, $lang);
                default:
                    return $this->processVoiceCommand($user, $message, $lang);
            }
        } catch (\Exception $e) {
            $this->logger->error('Voice assistant error', ['error' => $e->getMessage()]);
            return $this->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Detect desired app page from message and return a navigate response.
     */
    private function navigateResponse(string $message, string $lang): JsonResponse
    {
        $t = mb_strtolower(trim($message));
        $locale = match($lang) { 'ar', 'tn-ar' => 'ar', 'en' => 'en', default => 'fr' };

        $urlMap = [
            '/dashboard|tableau de bord|main page|page principale|لوحة|الرئيسية|dash|d7b/i' => ['url' => "/{$locale}/dashboard", 'label_en' => 'Dashboard', 'label_fr' => 'Tableau de bord', 'label_ar' => 'لوحة التحكم', 'label_tn' => 'الداشبورد'],
            '/profile|ملف|بروفيل|profil/i' => ['url' => "/{$locale}/profile", 'label_en' => 'Profile', 'label_fr' => 'Profil', 'label_ar' => 'الملف الشخصي', 'label_tn' => 'البروفيل'],
            '/activit|نشاط/i' => ['url' => "/{$locale}/my-activities", 'label_en' => 'Activities', 'label_fr' => 'Activités', 'label_ar' => 'الأنشطة', 'label_tn' => 'النشاطات'],
            '/health|santé|صحت|journal/i' => ['url' => "/{$locale}/health/journal", 'label_en' => 'Health Journal', 'label_fr' => 'Journal de santé', 'label_ar' => 'دفتر الصحة', 'label_tn' => 'دفتر الصحة'],
            '/nutrition|تغذية|régime/i' => ['url' => "/{$locale}/nutrition", 'label_en' => 'Nutrition', 'label_fr' => 'Nutrition', 'label_ar' => 'التغذية', 'label_tn' => 'التغذية'],
            '/service|خدمة/i' => ['url' => "/{$locale}/my-services", 'label_en' => 'Services', 'label_fr' => 'Services', 'label_ar' => 'الخدمات', 'label_tn' => 'الخدمات'],
            '/treatment|traitement|علاج|دواء/i' => ['url' => "/{$locale}/treatment", 'label_en' => 'Treatment', 'label_fr' => 'Traitement', 'label_ar' => 'العلاج', 'label_tn' => 'الدواء'],
            '/loyalty|وفاء|fidélité/i' => ['url' => "/{$locale}/loyalty", 'label_en' => 'Loyalty', 'label_fr' => 'Fidélité', 'label_ar' => 'الولاء', 'label_tn' => 'الولاء'],
            '/message|messag|رسائل/i' => ['url' => "/{$locale}/networking/messages", 'label_en' => 'Messages', 'label_fr' => 'Messages', 'label_ar' => 'الرسائل', 'label_tn' => 'الرسائل'],
        ];

        $url = "/{$locale}/dashboard";
        $label = match($lang) { 'ar' => 'لوحة التحكم', 'tn-ar' => 'الداشبورد', 'tn-latn' => 'dashboard', 'fr' => 'Tableau de bord', default => 'Dashboard' };

        foreach ($urlMap as $pattern => $info) {
            if (preg_match($pattern, $t)) {
                $url = $info['url'];
                $label = $info['label_' . (in_array($lang, ['ar', 'tn-ar']) ? 'ar' : (in_array($lang, ['tn-latn']) ? 'tn' : (($lang === 'fr') ? 'fr' : 'en')))] ?? $info['label_en'];
                break;
            }
        }

        $msgs = [
            'en' => "Taking you to {$label}...",
            'fr' => "Je vous emmène vers {$label}...",
            'ar' => "جاري التنقل إلى {$label}...",
            'tn-ar' => "راه يخذك لـ {$label}...",
            'tn-latn' => "Roh yjibek l {$label}...",
        ];
        $text = $msgs[$lang] ?? $msgs['en'];

        return $this->json([
            'success' => true,
            'text' => $text,
            'message' => $text,
            'navigate_url' => $url,
            'action' => 'navigate',
        ]);
    }

    /**
     * Server-side multilingual intent detection (fallback when client sends 'chat')
     */
    private function detectIntent(string $message): array
    {
        $t = mb_strtolower(trim($message));
        $action = 'chat';
        $activityName = '';

        // Navigation patterns (app pages) — check FIRST
        $navPatterns = [
            '/\b(take me to|go to|navigate to|open page|open the)\b/iu',
            '/\b(emmène.?moi|aller à|ouvrir|portez.?moi)\b/iu',
            '/(خذني|روح|فتحلي|امشي|أريد أن أذهب|اذهب إلى|افتح)/u',
            '/\b(roh l|khothni|fta7li|emchi|beh t3addi)\b/iu',
        ];
        foreach ($navPatterns as $pat) {
            if (preg_match($pat, $t)) {
                return ['action' => 'navigate', 'activityName' => ''];
            }
        }

        // My activities patterns
        $myPatterns = [
            '/\b(my activit|my schedule|what am i|i\'?m (in|registered)|my events|what have i joined)\b/iu',
            '/\b(mes activit|mon (emploi|calendrier)|je suis inscri|mes inscri|quelles? activit|qu.?est.?ce que j.?ai)\b/iu',
            '/(أنشطتي|مسجل في|جدولي|ما هي أنشطتي)/u',
            '/(نشاطاتي|شو اشتركت|famma achniya|chny3mlu|chno 3andi inscrit)/iu',
        ];

        foreach ($myPatterns as $pat) {
            if (preg_match($pat, $t)) {
                return ['action' => 'list_my_activities', 'activityName' => ''];
            }
        }

        // Join patterns
        $joinPatterns = [
            '/\b(join|enroll|register|sign.?up|participate|book|add me)\b/iu',
            '/\b(inscri|rejoind|particip|réserv|ajout.?\s*moi|je (veux|voudrais).*(inscrire|participer|rejoindre))\b/iu',
            '/(انضم|سجل|اشترك|احجز|سجلني)/u',
            '/(نحب نسجل|باهي نشارك|حب يشارك|inscri-ni|nheb nsajel|bahi nchark|7ab ychark)/iu',
        ];
        foreach ($joinPatterns as $pat) {
            if (preg_match($pat, $t)) {
                $activityName = $this->extractActivityName($t, 'join');
                return ['action' => 'join_activity', 'activityName' => $activityName];
            }
        }

        // Cancel patterns
        $cancelPatterns = [
            '/\b(cancel|unsubscribe|leave|quit|drop|remove)\b/iu',
            '/\b(annul|désinscrire|quitt|retire|supprim|je ne veux plus)\b/iu',
            '/(الغ|حذف|غادر|اسحب|ألغِ)/u',
            '/(الغيلي|مزبلة|ماحببتش|حذفها منهم|7thef|alghili|mech 7abi|mazbelha)/iu',
        ];
        foreach ($cancelPatterns as $pat) {
            if (preg_match($pat, $t)) {
                $activityName = $this->extractActivityName($t, 'cancel');
                return ['action' => 'cancel_activity', 'activityName' => $activityName];
            }
        }

        // List available patterns
        $listPatterns = [
            '/\b(show|list|see|view|available|what.?s (available|there)|activities|find|browse)\b/iu',
            '/\b(affich|montr|voir|liste?|disponible|cherch|activités)\b/iu',
            '/(اعرض|قائمة|متاح|أظهر|نشاط)/u',
            '/(عرضلي|شو فامة|كيفاش|شنو|3radli|chno famma|kifech|chnou)/iu',
        ];
        foreach ($listPatterns as $pat) {
            if (preg_match($pat, $t)) {
                return ['action' => 'list_available', 'activityName' => ''];
            }
        }

        return ['action' => $action, 'activityName' => $activityName];
    }

    /**
     * Extract just the activity name from a command string
     */
    private function extractActivityName(string $text, string $intentType): string
    {
        $cleaned = mb_strtolower(trim($text));

        // Remove common filler words
        $cleaned = preg_replace('/\b(please|can you|could you|i want to|i\'?d like to|for me|me for|me to|me in)\b/iu', '', $cleaned);

        if ($intentType === 'join') {
            $cleaned = preg_replace('/\b(join|enroll|register|sign\s*up|participate\s*in|book|add me to)\b/iu', '', $cleaned);
            $cleaned = preg_replace('/\b(inscri[st]?.?\s*moi\s*(au|à|pour|dans)?|rejoindre|participer\s*(à|au|dans)?|réserver|ajoute.?\s*moi\s*(au|à|dans)?|je\s*veux\s*m.?inscrire\s*(au|à|pour)?|je\s*voudrais\s*(m.?inscrire|participer)\s*(au|à|pour)?)\b/iu', '', $cleaned);
            $cleaned = preg_replace('/(انضم\s*(إلى|ل|في)?|سجلني\s*(في|ل)?|اشترك\s*(في|ل)?|احجز)/u', '', $cleaned);
        }
        if ($intentType === 'cancel') {
            $cleaned = preg_replace('/\b(cancel|unsubscribe\s*from|leave|quit|drop|remove\s*me\s*from)\b/iu', '', $cleaned);
            $cleaned = preg_replace('/\b(annule?r?|désinscrire?\s*(de|du)?|quitter?|retire.?\s*moi\s*(de|du)?|supprime?r?|je\s*ne\s*veux\s*plus)\b/iu', '', $cleaned);
            $cleaned = preg_replace('/(الغ|حذف|غادر|اسحب|ألغِ)\s*(من|في)?/u', '', $cleaned);
        }

        // Remove leftover articles/prepositions
        $cleaned = preg_replace('/\b(le|la|les|l\'|du|de|des|au|aux|un|une|mon|ma|mes|my|the|a|an|for|from|in|to)\b/iu', '', $cleaned);
        return trim(preg_replace('/\s+/', ' ', $cleaned));
    }

    #[Route('/voice-assistant/process', name: 'voice_process', methods: ['POST'])]
    public function processVoiceCommand(User $user, string $message, string $lang = 'en'): JsonResponse
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $scriptPath = $projectDir . '/scripts/activity_assistant.py';

        $process = new Process(['python', $scriptPath, $message, (string)$user->getId(), $lang]);
        $process->setWorkingDirectory($projectDir);
        $process->setTimeout(30);

        try {
            $process->mustRun();
            $output = $process->getOutput();
            $result = json_decode($output, true);

            if (!$result) {
                $this->logger->error('Invalid JSON from Python script', ['output' => $output]);
                return $this->json(['error' => 'Invalid internal response', 'text' => 'Error processing your request'], 500);
            }

            if (!isset($result['text'])) {
                $result['text'] = $result['message'] ?? 'No response';
            }
            if (!isset($result['success'])) {
                $result['success'] = true;
            }

            return $this->json($result);
        } catch (ProcessFailedException $exception) {
            $this->logger->error('Python script failed', [
                'error' => $exception->getMessage(),
                'output' => $process->getOutput(),
                'errorOutput' => $process->getErrorOutput()
            ]);

            return $this->json([
                'error' => 'Assistant processing failed',
                'text' => 'I am having trouble right now, please try again',
                'success' => false
            ], 500);
        }
    }

    private function joinActivity(User $user, string $activityName, string $lang): JsonResponse
    {
        $activities = $this->activityRepository->findUpcoming();
        $matchedActivity = $this->findBestActivityMatch($activityName, $activities);

        if (!$matchedActivity) {
            $messages = [
                'en' => 'Sorry, I could not find that activity. Please try another name.',
                'fr' => 'Désolé, je n\'ai pas trouvé cette activité. Veuillez essayer un autre nom.',
                'ar' => 'آسف، لم أتمكن من العثور على ذلك النشاط. يرجى محاولة الاسم الآخر.'
            ];
            return $this->json([
                'success' => false,
                'message' => $messages[$lang] ?? $messages['en']
            ]);
        }

        $existing = $this->participationRepository->findByUserAndActivity($user->getId(), $matchedActivity->getId());

        if ($existing) {
            $messages = [
                'en' => 'You are already registered for this activity.',
                'fr' => 'Vous êtes déjà inscrit à cette activité.',
                'ar' => 'أنت مسجل بالفعل في هذا النشاط.'
            ];
            return $this->json([
                'success' => false,
                'message' => $messages[$lang] ?? $messages['en']
            ]);
        }

        $participation = new Participation();
        $participation->setActivity($matchedActivity);
        $participation->setSeniorId($user->getId());
        $participation->setStatus('registered');
        $participation->setRegisteredAt(new \DateTime());
        $participation->setRegistrationMethod('voice_assistant');

        $this->entityManager->persist($participation);
        $this->entityManager->flush();

        $messages = [
            'en' => sprintf('Great! I\'ve registered you for %s on %s.',
                $matchedActivity->getTitle(),
                $matchedActivity->getStartTime()->format('M d at g:i A')),
            'fr' => sprintf('Super ! Je vous ai inscrit pour %s le %s.',
                $matchedActivity->getTitle(),
                $matchedActivity->getStartTime()->format('d M à H:i')),
            'ar' => sprintf('رائع! لقد سجلتك في %s في %s.',
                $matchedActivity->getTitle(),
                $matchedActivity->getStartTime()->format('d M في g:i A'))
        ];

        return $this->json([
            'success' => true,
            'message' => $messages[$lang] ?? $messages['en'],
            'should_refresh' => true,
            'activity' => ['id' => $matchedActivity->getId(), 'title' => $matchedActivity->getTitle()]
        ]);
    }

    private function cancelActivity(User $user, string $activityName, string $lang): JsonResponse
    {
        $userActivities = $this->participationRepository->findBy([
            'seniorId' => $user->getId(),
            'status' => ['registered', 'inscrit']
        ]);

        $matchedParticipation = null;
        $activityNameLower = strtolower($activityName);

        foreach ($userActivities as $participation) {
            $activity = $this->activityRepository->find($participation->getActivityId());
            if ($activity && stripos($activity->getTitle(), $activityNameLower) !== false) {
                $matchedParticipation = $participation;
                break;
            }
        }

        if (!$matchedParticipation) {
            $messages = [
                'en' => 'I could not find that activity in your schedule.',
                'fr' => 'Je n\'ai pas trouvé cette activité dans votre calendrier.',
                'ar' => 'لم أتمكن من العثور على تلك النشاط في جدولك.'
            ];
            return $this->json([
                'success' => false,
                'message' => $messages[$lang] ?? $messages['en']
            ]);
        }

        $matchedParticipation->setStatus('cancelled');
        $this->entityManager->flush();

        $messages = [
            'en' => 'I\'ve cancelled your registration for this activity.',
            'fr' => 'J\'ai annulé votre inscription pour cette activité.',
            'ar' => 'لقد ألغيت تسجيلك في هذا النشاط.'
        ];

        return $this->json([
            'success' => true,
            'should_refresh' => true,
            'message' => $messages[$lang] ?? $messages['en']
        ]);
    }

    private function listUserActivities(User $user, string $lang): JsonResponse
    {
        $participations = $this->participationRepository->findBy([
            'seniorId' => $user->getId(),
            'status' => ['registered', 'inscrit']
        ]);

        $activities = [];
        foreach ($participations as $participation) {
            $activity = $this->activityRepository->find($participation->getActivityId());
            if ($activity) {
                $activities[] = [
                    'id' => $activity->getId(),
                    'title' => $activity->getTitle(),
                    'type' => $activity->getType(),
                    'startTime' => $activity->getStartTime()->format('Y-m-d H:i'),
                    'location' => $activity->getLocation()
                ];
            }
        }

        $message = '';
        if (empty($activities)) {
            $messages = [
                'en' => 'You have no upcoming activities registered.',
                'fr' => 'Vous n\'avez pas d\'activités planifiées.',
                'ar' => 'ليس لديك أي أنشطة مخطط لها.'
            ];
            $message = $messages[$lang] ?? $messages['en'];
        } else {
            $message = $this->formatActivitiesList($activities, $lang);
        }

        return $this->json([
            'success' => true,
            'message' => $message,
            'activities' => $activities
        ]);
    }

    private function listAvailableActivities(User $user, string $lang): JsonResponse
    {
        $activities = $this->activityRepository->findUpcoming();

        $formatted = array_map(function ($activity) {
            return [
                'id' => $activity->getId(),
                'title' => $activity->getTitle(),
                'type' => $activity->getType(),
                'startTime' => $activity->getStartTime()?->format('Y-m-d H:i'),
                'location' => $activity->getLocation(),
                'spotsAvailable' => max(0, ($activity->getMaxParticipants() ?? 999) - $activity->getCurrentParticipants())
            ];
        }, $activities);

        return $this->json([
            'success' => true,
            'activities' => $formatted
        ]);
    }

    private function findBestActivityMatch($searchTerm, $activities)
    {
        $searchTerm = strtolower(trim($searchTerm));

        foreach ($activities as $activity) {
            if (stripos($activity->getTitle(), $searchTerm) !== false) {
                return $activity;
            }
        }

        $best = null;
        $bestScore = 0;

        foreach ($activities as $activity) {
            $ratio = similar_text(strtolower($activity->getTitle()), $searchTerm);
            if ($ratio > $bestScore) {
                $bestScore = $ratio;
                $best = $activity;
            }
        }

        return $bestScore > 50 ? $best : null;
    }

    private function formatActivitiesList($activities, $lang): string
    {
        if (empty($activities)) return '';

        if ($lang === 'fr') {
            $intro = "Vous avez " . count($activities) . " activités programmées:\n";
        } elseif ($lang === 'ar') {
            $intro = "لديك " . count($activities) . " أنشطة مخطط لها:\n";
        } else {
            $intro = "You have " . count($activities) . " activities scheduled:\n";
        }

        foreach ($activities as $activity) {
            $time = \DateTime::createFromFormat('Y-m-d H:i', $activity['startTime'])?->format('M d at g:i A') ?? '';
            $intro .= "\n• " . $activity['title'] . " at " . $time;
        }

        return $intro;
    }

    #[Route('/chat', name: 'chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'User not authenticated'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $message = $data['message'] ?? '';
        $lang = $request->getLocale();

        if (empty($message)) {
            return $this->json(['error' => 'Empty message'], 400);
        }

        return $this->processVoiceCommand($user, $message, $lang);
    }

    #[Route('/admin/recent-activities', name: 'admin_recent_activities', methods: ['GET'])]
    public function getRecentActivities(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || !in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $recentParticipations = $this->participationRepository->findRecentChanges(10);
        $conn = $this->entityManager->getConnection();
        $recentActivities = [];

        foreach ($recentParticipations as $participation) {
            try {
                $userName = 'Unknown User';
                $userResult = $conn->executeQuery(
                    'SELECT first_name, last_name FROM user WHERE id = ?',
                    [$participation->getSeniorId()]
                )->fetchAssociative();
                if ($userResult) {
                    $userName = trim(($userResult['first_name'] ?? '') . ' ' . ($userResult['last_name'] ?? ''));
                }

                $activityName = $participation->getTitle() ?? 'Activity';

                $status = $participation->getStatus();
                if (in_array($status, ['présent', 'registered', 'inscrit'])) {
                    $action = 'joined';
                    $type = 'activity';
                    $icon = '✅';
                } else {
                    $action = 'cancelled';
                    $type = 'activity-cancel';
                    $icon = '❌';
                }

                $time = $participation->getRegisteredAt();
                if ($time) {
                    $now = new \DateTime();
                    $interval = $now->diff($time);

                    if ($interval->days > 0) {
                        $timeAgo = $interval->days . ' day' . ($interval->days > 1 ? 's' : '') . ' ago';
                    } elseif ($interval->h > 0) {
                        $timeAgo = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
                    } elseif ($interval->i > 0) {
                        $timeAgo = $interval->i . ' min' . ($interval->i > 1 ? 's' : '') . ' ago';
                    } else {
                        $timeAgo = 'just now';
                    }
                } else {
                    $timeAgo = 'N/A';
                }

                $recentActivities[] = [
                    'id' => $participation->getId(),
                    'user' => $userName,
                    'action' => $action,
                    'activity' => $activityName,
                    'time' => $timeAgo,
                    'type' => $type,
                    'icon' => $icon,
                    'timestamp' => $participation->getRegisteredAt()?->getTimestamp() ?? 0,
                ];
            } catch (\Exception $e) {
                continue;
            }
        }

        return $this->json([
            'success' => true,
            'activities' => $recentActivities
        ]);
    }
}
