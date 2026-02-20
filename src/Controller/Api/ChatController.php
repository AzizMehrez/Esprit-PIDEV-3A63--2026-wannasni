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
        $action = $data['action'] ?? 'chat'; // chat, join, cancel, list_my_activities

        if (empty($message)) {
            return $this->json(['error' => 'Empty message'], 400);
        }

        try {
            switch ($action) {
                case 'join_activity':
                    return $this->joinActivity($user, $message, $lang);
                case 'cancel_activity':
                    return $this->cancelActivity($user, $message, $lang);
                case 'list_my_activities':
                    return $this->listUserActivities($user, $lang);
                case 'list_available':
                    return $this->listAvailableActivities($user, $lang);
                default:
                    return $this->processVoiceCommand($user, $message, $lang);
            }
        } catch (\Exception $e) {
            $this->logger->error('Voice assistant error', ['error' => $e->getMessage()]);
            return $this->json(['error' => 'Processing failed'], 500);
        }
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

            // Ensure proper response format
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
            
            return $this->json(['error' => 'Assistant processing failed', 'text' => 'I am having trouble right now, please try again', 'success' => false], 500);
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

        // Check if already joined
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

        // Create participation
        $participation = new Participation();
        $participation->setActivityId($matchedActivity->getId());
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
            $activity = $this->entityManager->getRepository('App:Activity')->find($participation->getActivityId());
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
            $activity = $this->entityManager->getRepository('App:Activity')->find($participation->getActivityId());
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
                'startTime' => $activity->getStartTime()->format('Y-m-d H:i'),
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

        // Fuzzy match
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
            $time = \DateTime::createFromFormat('Y-m-d H:i', $activity['startTime'])->format('M d at g:i A');
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
        // Check if user is admin
        $user = $this->getUser();
        if (!$user instanceof User || !in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $recentParticipations = $this->participationRepository->findRecentChanges(10);
        $conn = $this->entityManager->getConnection();
        $recentActivities = [];
        
        foreach ($recentParticipations as $participation) {
            try {
                // Get user name
                $userName = 'Unknown User';
                $userResult = $conn->executeQuery(
                    'SELECT first_name, last_name FROM user WHERE id = ?',
                    [$participation->getSeniorId()]
                )->fetchAssociative();
                if ($userResult) {
                    $userName = trim(($userResult['first_name'] ?? '') . ' ' . ($userResult['last_name'] ?? ''));
                }
                
                // Get activity name
                $activityName = $participation->getTitle() ?? 'Activity';
                
                // Determine action based on status
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
                
                // Calculate time ago
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
