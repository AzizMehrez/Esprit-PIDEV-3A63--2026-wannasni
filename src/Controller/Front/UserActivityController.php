<?php

namespace App\Controller\Front;

use App\Service\ActivityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/my-activities', requirements: ['_locale' => 'fr|en|ar'])]
class UserActivityController extends AbstractController
{
    private const ALL_ACTIVITIES = [
        1 => ['id' => 1, 'name' => 'Morning Walk', 'type' => 'physical', 'schedule' => 'Daily 8:00 AM'],
        2 => ['id' => 2, 'name' => 'Memory Games', 'type' => 'cognitive', 'schedule' => 'Mon/Wed/Fri 10:00 AM'],
        3 => ['id' => 3, 'name' => 'Yoga Class', 'type' => 'physical', 'schedule' => 'Tue/Thu 9:00 AM'],
        4 => ['id' => 4, 'name' => 'Art Therapy', 'type' => 'creative', 'schedule' => 'Saturday 2:00 PM'],
        5 => ['id' => 5, 'name' => 'Social Hour', 'type' => 'social', 'schedule' => 'Daily 3:00 PM'],
    ];

    // IDs enrolled by default
    private const DEFAULT_ENROLLED = [1, 2, 3];

    public function __construct(private RequestStack $requestStack)
    {
    }

    private function getEnrolledIds(): array
    {
        $session = $this->requestStack->getSession();
        if (!$session->has('enrolled_activity_ids')) {
            $session->set('enrolled_activity_ids', self::DEFAULT_ENROLLED);
        }
        return $session->get('enrolled_activity_ids');
    }

    private function setEnrolledIds(array $ids): void
    {
        $this->requestStack->getSession()->set('enrolled_activity_ids', array_values($ids));
    }

    #[Route('/', name: 'app_my_activities')]
    public function index(): Response
    {
        $enrolledIds = $this->getEnrolledIds();

        $enrolledActivities = [];
        foreach ($enrolledIds as $id) {
            if (isset(self::ALL_ACTIVITIES[$id])) {
                $activity = self::ALL_ACTIVITIES[$id];
                $activity['nextSession'] = new \DateTime('+' . $id . ' day');
                $enrolledActivities[] = $activity;
            }
        }

        $availableActivities = [];
        foreach (self::ALL_ACTIVITIES as $id => $activity) {
            if (!in_array($id, $enrolledIds)) {
                $activity['participants'] = rand(3, 20);
                $availableActivities[] = $activity;
            }
        }

        return $this->render('front/activities/index.html.twig', [
            'enrolled_activities' => $enrolledActivities,
            'available_activities' => $availableActivities,
        ]);
    }

    #[Route('/enroll/{id}', name: 'app_enroll_activity', methods: ['POST'])]
    public function enroll(int $id, Request $request): Response
    {
        if (!isset(self::ALL_ACTIVITIES[$id])) {
            $this->addFlash('error', 'Activité introuvable.');
            return $this->redirectToRoute('app_my_activities', ['_locale' => $request->getLocale()]);
        }

        $enrolledIds = $this->getEnrolledIds();
        if (in_array($id, $enrolledIds)) {
            $this->addFlash('warning', 'Vous êtes déjà inscrit à cette activité.');
        } else {
            $enrolledIds[] = $id;
            $this->setEnrolledIds($enrolledIds);
            $this->addFlash('success', 'Vous êtes inscrit à "' . self::ALL_ACTIVITIES[$id]['name'] . '" avec succès !');
        }

        return $this->redirectToRoute('app_my_activities', ['_locale' => $request->getLocale()]);
    }

    #[Route('/cancel/{id}', name: 'app_cancel_activity', methods: ['POST'])]
    public function cancel(int $id, Request $request): Response
    {
        $enrolledIds = $this->getEnrolledIds();
        $key = array_search($id, $enrolledIds);

        if ($key === false) {
            $this->addFlash('error', 'Vous n\'êtes pas inscrit à cette activité.');
        } else {
            unset($enrolledIds[$key]);
            $this->setEnrolledIds($enrolledIds);
            $activityName = self::ALL_ACTIVITIES[$id]['name'] ?? 'Activité';
            $this->addFlash('success', 'Votre inscription à "' . $activityName . '" a été annulée.');
        }

        return $this->redirectToRoute('app_my_activities', ['_locale' => $request->getLocale()]);
    }

    #[Route('/history', name: 'app_participation_history')]
    public function participationHistory(): Response
    {
        $enrolledIds = $this->getEnrolledIds();
        $session = $this->requestStack->getSession();
        $feedbacks = $session->get('activity_feedbacks', []);

        $participationHistory = [];
        $dayOffset = 1;
        foreach ($enrolledIds as $id) {
            if (isset(self::ALL_ACTIVITIES[$id])) {
                $activity = self::ALL_ACTIVITIES[$id];
                $participationHistory[] = [
                    'id' => $id,
                    'activity_name' => $activity['name'],
                    'type' => $activity['type'],
                    'date' => new \DateTime('-' . $dayOffset . ' day'),
                    'duration' => [30, 45, 60][($id - 1) % 3],
                    'status' => 'completed',
                    'has_feedback' => isset($feedbacks[$id]),
                ];
                $dayOffset++;
            }
        }

        return $this->render('front/activities/history.html.twig', [
            'participation_history' => $participationHistory,
        ]);
    }

    #[Route('/history/feedback/{id}', name: 'app_activity_feedback', methods: ['POST'])]
    public function submitFeedback(int $id, Request $request): Response
    {
        $session = $this->requestStack->getSession();
        $feedbacks = $session->get('activity_feedbacks', []);

        $feedbacks[$id] = [
            'rating' => (int) $request->request->get('rating', 0),
            'mood_before' => (int) $request->request->get('mood_before', 0),
            'mood_after' => (int) $request->request->get('mood_after', 0),
            'comment' => $request->request->get('comment', ''),
            'problems' => $request->request->get('problems', ''),
            'recommend' => $request->request->getBoolean('recommend'),
            'share' => $request->request->get('share', 'non'),
            'submitted_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];

        $session->set('activity_feedbacks', $feedbacks);

        $activityName = self::ALL_ACTIVITIES[$id]['name'] ?? 'Activité';
        $this->addFlash('success', 'Merci pour votre avis sur "' . $activityName . '" !');

        return $this->redirectToRoute('app_participation_history', ['_locale' => $request->getLocale()]);
    }
}
