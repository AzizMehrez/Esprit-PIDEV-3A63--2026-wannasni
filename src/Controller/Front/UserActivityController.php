<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\ParticipationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/my-activities', requirements: ['_locale' => 'fr|en|ar'])]
class UserActivityController extends AbstractController
{
    public function __construct(
        private RequestStack $requestStack,
        private ActivityRepository $activityRepository,
        private ParticipationRepository $participationRepository
    ) {
    }

    #[Route('/', name: 'app_my_activities')]
    public function index(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        // Get all active activities from database
        $allActivities = $this->activityRepository->findActive();
        
        // Get enrolled activity IDs from participations (excluding cancelled ones)
        $enrolledIds = [];
        if ($user instanceof User) {
            $participations = $this->participationRepository->findBy(['seniorId' => $user->getId()]);
            // Only include active participations (not cancelled)
            $enrolledIds = array_map(
                fn($p) => $p->getActivityId(),
                array_filter($participations, fn($p) => !in_array($p->getStatus(), ['annulé', 'cancelled']))
            );
        }

        $enrolledActivities = [];
        $availableActivities = [];

        foreach ($allActivities as $activity) {
            $activityData = [
                'id' => $activity->getId(),
                'name' => $activity->getTitle(),
                'type' => $activity->getType(),
                'schedule' => $activity->getStartTime() ? $activity->getStartTime()->format('d/m/Y H:i') : 'N/A',
                'location' => $activity->getLocation(),
                'description' => $activity->getDescription(),
                'nextSession' => $activity->getStartTime(),
                'participants' => $activity->getCurrentParticipants(),
                'maxParticipants' => $activity->getMaxParticipants(),
                'isFull' => $activity->isFull(),
            ];

            if (in_array($activity->getId(), $enrolledIds)) {
                $enrolledActivities[] = $activityData;
            } else {
                $availableActivities[] = $activityData;
            }
        }

        return $this->render('front/activities/index.html.twig', [
            'enrolled_activities' => $enrolledActivities,
            'available_activities' => $availableActivities,
        ]);
    }

    #[Route('/enroll/{id}', name: 'app_my_activities_enroll', methods: ['POST'])]
    public function enroll(int $id, Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            $this->addFlash('error', 'Vous devez être connecté pour vous inscrire.');
            return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
        }

        // Validate CSRF token
        if (!$this->isCsrfTokenValid('enroll_activity_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_my_activities', ['_locale' => $request->getLocale()]);
        }

        $activity = $this->activityRepository->find($id);
        if (!$activity) {
            $this->addFlash('error', 'Activité introuvable.');
            return $this->redirectToRoute('app_my_activities', ['_locale' => $request->getLocale()]);
        }

        // Check if already enrolled
        $existingParticipation = $this->participationRepository->findOneBy([
            'seniorId' => $user->getId(),
            'activity' => $id
        ]);

        if ($existingParticipation) {
            // Check if the participation is cancelled, if so, reactivate it
            if (in_array($existingParticipation->getStatus(), ['annulé', 'cancelled'])) {
                $existingParticipation->setStatus('inscrit');
                $existingParticipation->setRegisteredAt(new \DateTime());
                $this->participationRepository->getEntityManager()->flush();
                $this->addFlash('success', 'Vous êtes à nouveau inscrit à "' . $activity->getTitle() . '" !');
            } else {
                $this->addFlash('warning', 'Vous êtes déjà inscrit à cette activité.');
            }
        } else {
            // Create new participation
            $participation = new \App\Entity\Participation();
            $participation->setActivity($activity);
            $participation->setSeniorId($user->getId());
            $participation->setStatus('inscrit');
            $participation->setTitle($activity->getTitle());
            $participation->setRegistrationMethod('web');
            $participation->setRegisteredAt(new \DateTime());

            $this->participationRepository->getEntityManager()->persist($participation);
            $this->participationRepository->getEntityManager()->flush();
            
            $this->addFlash('success', 'Vous êtes inscrit à "' . $activity->getTitle() . '" avec succès !');
        }

        return $this->redirectToRoute('app_my_activities', ['_locale' => $request->getLocale()]);
    }

    #[Route('/cancel/{id}', name: 'app_my_activities_cancel', methods: ['POST'])]
    public function cancel(int $id, Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            $this->addFlash('error', 'Vous devez être connecté.');
            return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
        }

        // Validate CSRF token
        if (!$this->isCsrfTokenValid('cancel_activity_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_my_activities', ['_locale' => $request->getLocale()]);
        }

        $participation = $this->participationRepository->findOneBy([
            'seniorId' => $user->getId(),
            'activity' => $id
        ]);

        if (!$participation) {
            $this->addFlash('error', 'Vous n\'êtes pas inscrit à cette activité.');
        } else {
            $activityName = $participation->getTitle();
            $participation->setStatus('annulé');
            $this->participationRepository->getEntityManager()->flush();
            $this->addFlash('success', 'Votre inscription à "' . $activityName . '" a été annulée.');
        }

        return $this->redirectToRoute('app_my_activities', ['_locale' => $request->getLocale()]);
    }

    #[Route('/history', name: 'app_participation_history')]
    public function participationHistory(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Get all participations for the user
        $participations = $this->participationRepository->findBy(
            ['seniorId' => $user->getId()],
            ['registeredAt' => 'DESC']
        );

        $participationHistory = [];
        foreach ($participations as $participation) {
            $activity = $this->activityRepository->find($participation->getActivityId());
            
            $participationHistory[] = [
                'id' => $participation->getId(),
                'activity_id' => $participation->getActivityId(),
                'activity_name' => $participation->getTitle(),
                'type' => $activity ? $activity->getType() : 'N/A',
                'date' => $participation->getRegisteredAt(),
                'duration' => $activity && $activity->getStartTime() && $activity->getEndTime() 
                    ? max(30, min(180, (int)(($activity->getEndTime()->getTimestamp() - $activity->getStartTime()->getTimestamp()) / 60)))
                    : 60,
                'status' => $participation->getStatus(),
                'has_feedback' => $participation->getFeedbackRating() !== null,
            ];
        }

        return $this->render('front/activities/history.html.twig', [
            'participation_history' => $participationHistory,
        ]);
    }

    #[Route('/history/feedback/{id}', name: 'app_activity_feedback', methods: ['POST'])]
    public function submitFeedback(int $id, Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            $this->addFlash('error', 'Vous devez être connecté.');
            return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
        }

        $activity = $this->activityRepository->find($id);
        
        if (!$activity) {
            $this->addFlash('error', 'Activité introuvable.');
            return $this->redirectToRoute('app_participation_history', ['_locale' => $request->getLocale()]);
        }

        // Find the participation record for this user and activity
        $participation = $this->participationRepository->findOneBy([
            'seniorId' => $user->getId(),
            'activity' => $id
        ]);

        if (!$participation) {
            $this->addFlash('error', 'Participation introuvable.');
            return $this->redirectToRoute('app_participation_history', ['_locale' => $request->getLocale()]);
        }

        // Save feedback to database
        $participation->setFeedbackRating((int) $request->request->get('rating', 0));
        $participation->setFeedbackComment($request->request->get('comment', ''));
        $participation->setMoodBefore((int) $request->request->get('mood_before', 0));
        $participation->setMoodAfter((int) $request->request->get('mood_after', 0));
        $participation->setProblemsEncountered($request->request->get('problems', ''));
        $participation->setRecommendToFriends($request->request->getBoolean('recommend'));
        $participation->setShareWithFamily($request->request->get('share', 'non'));

        $this->participationRepository->getEntityManager()->flush();

        $this->addFlash('success', 'Merci pour votre avis sur "' . $activity->getTitle() . '" !');

        return $this->redirectToRoute('app_participation_history', ['_locale' => $request->getLocale()]);
    }

    #[Route('/history/mark-attended/{id}', name: 'app_mark_attended', methods: ['POST'])]
    public function markAttended(int $id, Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            $this->addFlash('error', 'Vous devez être connecté.');
            return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
        }

        // Validate CSRF token
        if (!$this->isCsrfTokenValid('mark_attended_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_participation_history', ['_locale' => $request->getLocale()]);
        }

        // Find the participation record
        $participation = $this->participationRepository->find($id);

        if (!$participation || $participation->getSeniorId() !== $user->getId()) {
            $this->addFlash('error', 'Participation introuvable.');
            return $this->redirectToRoute('app_participation_history', ['_locale' => $request->getLocale()]);
        }

        // Update status to présent
        $participation->setStatus('présent');
        $participation->setPresenceConfirmationDate(new \DateTime());
        $this->participationRepository->getEntityManager()->flush();

        $this->addFlash('success', 'Présence confirmée ! Vous pouvez maintenant donner votre avis.');

        return $this->redirectToRoute('app_participation_history', ['_locale' => $request->getLocale()]);
    }

    #[Route('/{id}', name: 'app_activity_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $activity = $this->activityRepository->find($id);
        if (!$activity) {
            throw $this->createNotFoundException('Activity not found');
        }

        $participantCount = $this->participationRepository->countActiveByActivity($id);

        $activityData = [
            'id' => $activity->getId(),
            'name' => $activity->getTitle(),
            'description' => $activity->getDescription() ?? '',
            'type' => $activity->getType(),
            'schedule' => $activity->getStartTime()?->format('d/m/Y H:i') ?? 'N/A',
            'location' => $activity->getLocation() ?? '',
            'participants' => $participantCount,
            'maxParticipants' => $activity->getMaxParticipants(),
            'isFull' => $activity->isFull(),
            'locationData' => null,
        ];

        // Resolve location data from locations.json
        if ($activity->getLocation()) {
            $locationsFile = $this->getParameter('kernel.project_dir') . '/public/data/locations.json';
            if (file_exists($locationsFile)) {
                $data = json_decode(file_get_contents($locationsFile), true);
                $locations = $data['locations'] ?? [];
                foreach ($locations as $loc) {
                    if (strtolower($loc['name']) === strtolower($activity->getLocation())) {
                        $activityData['locationData'] = $loc;
                        break;
                    }
                }
            }
        }

        // Check if user is enrolled
        $isEnrolled = false;
        /** @var User|null $user */
        $user = $this->getUser();
        if ($user instanceof User) {
            $participation = $this->participationRepository->findOneBy([
                'seniorId' => $user->getId(),
                'activity' => $id
            ]);
            if ($participation && !in_array($participation->getStatus(), ['annulé', 'cancelled'])) {
                $isEnrolled = true;
            }
        }

        return $this->render('front/activities/show.html.twig', [
            'activity' => $activityData,
            'isEnrolled' => $isEnrolled,
        ]);
    }
}
