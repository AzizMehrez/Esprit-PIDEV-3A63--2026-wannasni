<?php

namespace App\Controller\Admin;

use App\Repository\ParticipationRepository;
use App\Repository\ActivityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class DashboardController extends AbstractController
{
    public function __construct(
        private ParticipationRepository $participationRepository,
        private ActivityRepository $activityRepository,
        private EntityManagerInterface $em
    ) {
    }

    #[Route('/', name: 'admin_dashboard')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        // Get real participation statistics
        $totalParticipations = $this->participationRepository->count([]);
        $presentParticipations = $this->participationRepository->count(['status' => 'présent']);
        $pendingParticipations = $this->participationRepository->count(['status' => 'inscrit']);
        $withFeedback = $this->participationRepository->countWithFeedback();

        // Mock statistics data (you can replace with real data later)
        $stats = [
            'total_users' => 1247,
            'active_users' => 892,
            'total_participations' => $totalParticipations,
            'present_participations' => $presentParticipations,
            'pending_participations' => $pendingParticipations,
            'participations_with_feedback' => $withFeedback,
            'services_pending' => 23,
            'activities_today' => 45,
            'health_records' => 3456,
            'nutrition_plans' => 178,
        ];

        // Get real recent activities from participations
        $recentParticipations = $this->participationRepository->findRecentChanges(15);
        $recentActivities = [];
        
        // Get connection for raw user queries
        $conn = $this->em->getConnection();
        
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
                    $action = 'Joined activity';
                    $type = 'activity';
                } else {
                    $action = 'Cancelled activity';
                    $type = 'activity-cancel';
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
                    'user' => $userName,
                    'action' => $action . ' "' . $activityName . '"',
                    'time' => $timeAgo,
                    'type' => $type,
                ];
            } catch (\Exception $e) {
                // Skip this entry if there's an error
                continue;
            }
        }
        
        // Keep only the 10 most recent
        $recentActivities = array_slice($recentActivities, 0, 10);

        // Get current user's joined activities
        $userActivities = [];
        if ($user instanceof \App\Entity\User) {
            $participations = $this->participationRepository->findBy(
                ['seniorId' => $user->getId()],
                ['registeredAt' => 'DESC']
            );
            
            foreach ($participations as $participation) {
                $status = $participation->getStatus();
                if (in_array($status, ['présent', 'registered', 'inscrit'])) {
                    $activity = $this->activityRepository->find($participation->getActivityId());
                    if ($activity) {
                        $userActivities[] = [
                            'id' => $activity->getId(),
                            'title' => $activity->getTitle(),
                            'type' => $activity->getType(),
                            'startTime' => $activity->getStartTime()?->format('d/m/Y H:i') ?? 'TBD',
                            'location' => $activity->getLocation() ?? 'TBD',
                        ];
                    }
                }
            }
        }

        // Get all activities with real participant counts
        $allActivities = $this->activityRepository->findBy(['isActive' => true], ['startTime' => 'DESC']);
        $activitiesData = [];
        foreach ($allActivities as $activity) {
            $participantCount = $this->participationRepository->countActiveByActivity($activity->getId());
            $activitiesData[] = [
                'id' => $activity->getId(),
                'title' => $activity->getTitle(),
                'type' => $activity->getType(),
                'participants' => $participantCount,
                'maxParticipants' => $activity->getMaxParticipants(),
                'startTime' => $activity->getStartTime()?->format('d/m/Y H:i') ?? 'TBD',
                'location' => $activity->getLocation() ?? 'TBD',
            ];
        }

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'recent_activities' => $recentActivities,
            'user_activities' => $userActivities,
            'all_activities' => $activitiesData,
        ]);
    }
}