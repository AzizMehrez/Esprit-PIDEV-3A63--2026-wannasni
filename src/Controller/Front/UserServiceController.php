<?php

namespace App\Controller\Front;

use App\Entity\ServiceRequest;
use App\Entity\LoyaltyPoint;
use App\Service\NotificationService;
use App\Service\SubscriptionService;
use App\Service\LoyaltyService;
use App\Entity\User;

use App\Repository\ServiceRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/{_locale}/my-services', requirements: ['_locale' => 'fr|en|ar'])]
class UserServiceController extends AbstractController
{
    private array $serviceTypes = [
        ['id' => 'electricite', 'name' => 'Électricité', 'icon' => '⚡', 'description' => 'Réparations et installations électriques'],
        ['id' => 'plomberie', 'name' => 'Plomberie', 'icon' => '🔧', 'description' => 'Réparations fuites, canalisations, sanitaires'],
        ['id' => 'transport', 'name' => 'Transport Médical', 'icon' => '🚗', 'description' => 'Transport vers rendez-vous médicaux'],
        ['id' => 'menage', 'name' => 'Ménage', 'icon' => '🏠', 'description' => 'Aide au ménage quotidien'],
        ['id' => 'courses', 'name' => 'Courses', 'icon' => '🛒', 'description' => 'Aide aux courses alimentaires'],
        ['id' => 'compagnie', 'name' => 'Compagnie', 'icon' => '👋', 'description' => 'Visites amicales et compagnie'],
    ];

    #[Route('/', name: 'app_my_services')]
    public function index(ServiceRequestRepository $serviceRequestRepository): Response
    {
        $user = $this->getUser();
        
        if ($user) {
            // Fetch real ServiceRequest entities for the logged-in user
            $myServices = $serviceRequestRepository->findBy(
                ['user' => $user],
                ['createdAt' => 'DESC']
            );
        } else {
            $myServices = [];
        }

        return $this->render('front/services/index.html.twig', [
            'my_services' => $myServices,
        ]);
    }

    #[Route('/request', name: 'app_services_request', methods: ['GET', 'POST'])]
    public function request(Request $request, EntityManagerInterface $em, NotificationService $notificationService, SubscriptionService $subscriptionService, ServiceRequestRepository $serviceRequestRepository, LoyaltyService $loyaltyService): Response
    {
        if ($request->isMethod('POST')) {
            // ... (existing code for user check)
            $user = $this->getUser();
            if (!$user) {
                $this->addFlash('error', 'Vous devez être connecté pour demander un service.');
                return $this->redirectToRoute('app_login');
            }

            // Vérifier abonnement et nombre de demandes précédentes (requêtes SQL directes pour éviter le cache Doctrine)
            $hasSubscription = false;
            $previousRequestsCount = 0;
            if ($user instanceof User) {
                $conn = $em->getConnection();
                $activeSubCount = (int) $conn->fetchOne(
                    'SELECT COUNT(*) FROM subscription WHERE senior_id = ? AND status = ?',
                    [$user->getId(), 'active']
                );
                $hasSubscription = ($activeSubCount > 0);
                $previousRequestsCount = (int) $conn->fetchOne(
                    'SELECT COUNT(*) FROM service_request WHERE user_id = ?',
                    [$user->getId()]
                );
            }
            $isFirstAttempt = ($previousRequestsCount === 0);

            // Validation inputs
            $inputs = [
                'type_service' => $request->request->get('type_service', ''),
                'description' => $request->request->get('description', ''),
                'senior_telephone' => $request->request->get('senior_telephone'),
                'senior_email' => $request->request->get('senior_email'),
                'adresse' => $request->request->get('adresse'),
                'ville' => $request->request->get('ville'),
                'code_postal' => $request->request->get('code_postal'),
                'niveau_urgence' => $request->request->get('niveau_urgence', 'normale'),
                'date_souhaitee' => $request->request->get('date_souhaitee'),
                'budget_minimum' => $request->request->get('budget_minimum'),
                'budget_maximum' => $request->request->get('budget_maximum'),
                'notifier_proches' => $request->request->has('notifier_proches')
            ];

            $errors = [];

            // Bloquer et rediriger si pas d'abonnement ET essai terminé
            if (!$hasSubscription && !$isFirstAttempt) {
                $this->addFlash('warning', 'Votre période d\'essai est terminée. Veuillez choisir une formule d\'abonnement pour accéder à nos services.');
                return $this->redirectToRoute('app_subscription_plans', ['_locale' => $request->getLocale()]);
            }

            // 1. Required fields
            if (empty($inputs['type_service'])) $errors[] = "Le type de service est requis.";
            if (empty($inputs['description'])) $errors[] = "La description est requise.";
            if (empty($inputs['adresse'])) $errors[] = "L'adresse est requise.";
            if (empty($inputs['ville'])) $errors[] = "La ville est requise.";
            if (empty($inputs['code_postal'])) $errors[] = "Le code postal est requis.";

            // 2. Email format
            if (!empty($inputs['senior_email']) && !filter_var($inputs['senior_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "L'adresse email n'est pas valide.";
            }

            // 3. Phone number (8 digits)
            if (empty($inputs['senior_telephone'])) {
                $errors[] = "Le numéro de téléphone est requis.";
            } elseif (!preg_match('/^\d{8}$/', $inputs['senior_telephone'])) {
                $errors[] = "Le numéro de téléphone doit contenir exactement 8 chiffres.";
            }

            // 4. Budget Logic
            if (!empty($inputs['budget_minimum']) && !empty($inputs['budget_maximum'])) {
                if ((float)$inputs['budget_minimum'] >= (float)$inputs['budget_maximum']) {
                    $errors[] = "Le budget minimum doit être inférieur au budget maximum.";
                }
            }

            // TEMPORARY DEBUG LOGGING
            $logData = "--- New Submission ---\n";
            $logData .= "Inputs: " . print_r($inputs, true) . "\n";
            $logData .= "Errors: " . print_r($errors, true) . "\n";
            file_put_contents('debug_form.txt', $logData, FILE_APPEND);

            // If errors exist, return to form with errors and inputs
            if (!empty($errors)) {
                return $this->render('front/services/request.html.twig', [
                    'service_types' => $this->serviceTypes,
                    'last_inputs' => $inputs,
                    'errors' => $errors,
                    'hasSubscription' => $hasSubscription,
                    'isFirstAttempt' => $isFirstAttempt,
                ], new Response('', 422));
            }

            $serviceRequest = new ServiceRequest();
            $serviceRequest->setUser($user);
            $serviceRequest->setTypeService($inputs['type_service']);
            $serviceRequest->setDescription($inputs['description']);
            $serviceRequest->setSeniorTelephone($inputs['senior_telephone']);
            $serviceRequest->setSeniorEmail($inputs['senior_email']);
            $serviceRequest->setAdresse($inputs['adresse']);
            $serviceRequest->setVille($inputs['ville']);
            $serviceRequest->setCodePostal($inputs['code_postal']);
            $serviceRequest->setNiveauUrgence($inputs['niveau_urgence']);
            
            if ($inputs['date_souhaitee']) {
                $serviceRequest->setDateSouhaitee(new \DateTime($inputs['date_souhaitee']));
            }
            
            if ($inputs['budget_minimum']) $serviceRequest->setBudgetMinimum($inputs['budget_minimum']);
            if ($inputs['budget_maximum']) $serviceRequest->setBudgetMaximum($inputs['budget_maximum']);
            
            $serviceRequest->setNotifierProches($inputs['notifier_proches']);
            $serviceRequest->setStatut('en_attente');

            $em->persist($serviceRequest);
            $em->flush();

            // Create Notification for Admin
            $notificationService->create(
                'info',
                'Nouvelle demande de service (' . $serviceRequest->getTypeService() . ') par ' . $user->getLastName() . ' ' . $user->getFirstName(),
                $serviceRequest->getId()
            );

            // ── Auto-award loyalty points for service request ──
            if ($user instanceof User) {
                try {
                    $loyaltyService->awardBonusPoints(
                        $user,
                        10,
                        LoyaltyPoint::SOURCE_BONUS,
                        $serviceRequest->getId(),
                        sprintf('Points fidélité : demande de service %s', $serviceRequest->getTypeService())
                    );
                } catch (\Exception $e) {
                    // Loyalty error should not block service request
                }
            }

            $this->addFlash('success', 'Votre demande de service a été envoyée avec succès !');
            return $this->redirectToRoute('app_my_services', ['_locale' => $request->getLocale()]);
        }

        // GET: determine subscription and first attempt status (requêtes SQL directes)
        $user = $this->getUser();
        $hasSubscription = false;
        $isFirstAttempt = true;
        if ($user instanceof User) {
            $conn = $em->getConnection();
            $activeSubCount = (int) $conn->fetchOne(
                'SELECT COUNT(*) FROM subscription WHERE senior_id = ? AND status = ?',
                [$user->getId(), 'active']
            );
            $hasSubscription = ($activeSubCount > 0);
            $requestCount = (int) $conn->fetchOne(
                'SELECT COUNT(*) FROM service_request WHERE user_id = ?',
                [$user->getId()]
            );
            $isFirstAttempt = ($requestCount === 0);
        }

        // Si pas d'abonnement et essai terminé → redirection vers les abonnements
        if (!$hasSubscription && !$isFirstAttempt) {
            $this->addFlash('warning', 'Votre période d\'essai est terminée. Veuillez choisir une formule d\'abonnement pour accéder à nos services.');
            return $this->redirectToRoute('app_subscription_plans', ['_locale' => $request->getLocale()]);
        }

        return $this->render('front/services/request.html.twig', [
            'service_types' => $this->serviceTypes,
            'last_inputs' => [],
            'hasSubscription' => $hasSubscription,
            'isFirstAttempt' => $isFirstAttempt,
        ]);
    }

    #[Route('/{id}', name: 'app_services_show', methods: ['GET'])]
    public function show(ServiceRequest $service, SubscriptionService $subscriptionService): Response
    {
        // Ensure user can only view their own services
        if ($service->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à ce service.');
        }

        // Calculer la réduction abonné si applicable (lecture seule, pas d'enregistrement)
        $discountInfo = null;
        $user = $this->getUser();
        if ($user && $service->getBudgetMaximum()) {
            $discountInfo = $subscriptionService->previewDiscount(
                $user,
                (float)$service->getBudgetMaximum()
            );
        }

        return $this->render('front/services/show.html.twig', [
            'service' => $service,
            'discountInfo' => $discountInfo,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_services_edit', methods: ['GET', 'POST'])]
    public function edit(ServiceRequest $service, Request $request, EntityManagerInterface $em): Response
    {
        // Ensure user can only edit their own services
        if ($service->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce service.');
        }

        if ($request->isMethod('POST')) {
            $service->setTypeService($request->request->get('type_service', $service->getTypeService()));
            $service->setDescription($request->request->get('description', $service->getDescription()));
            $service->setSeniorTelephone($request->request->get('senior_telephone'));
            $service->setSeniorEmail($request->request->get('senior_email'));
            $service->setAdresse($request->request->get('adresse'));
            $service->setVille($request->request->get('ville'));
            $service->setCodePostal($request->request->get('code_postal'));
            $service->setNiveauUrgence($request->request->get('niveau_urgence', 'normale'));
            
            $dateSouhaitee = $request->request->get('date_souhaitee');
            if ($dateSouhaitee) {
                $service->setDateSouhaitee(new \DateTime($dateSouhaitee));
            }
            
            $budgetMin = $request->request->get('budget_minimum');
            $budgetMax = $request->request->get('budget_maximum');
            if ($budgetMin) $service->setBudgetMinimum($budgetMin);
            if ($budgetMax) $service->setBudgetMaximum($budgetMax);
            
            $service->setNotifierProches($request->request->has('notifier_proches'));

            $em->flush();

            $this->addFlash('success', 'Votre demande de service a été modifiée avec succès !');
            return $this->redirectToRoute('app_my_services', ['_locale' => $request->getLocale()]);
        }

        return $this->render('front/services/edit.html.twig', [
            'service' => $service,
            'service_types' => $this->serviceTypes,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_services_delete', methods: ['POST'])]
    public function delete(ServiceRequest $service, Request $request, EntityManagerInterface $em): Response
    {
        // Ensure user can only delete their own services
        if ($service->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce service.');
        }

        // Verify CSRF token
        if ($this->isCsrfTokenValid('delete' . $service->getId(), $request->request->get('_token'))) {
            $em->remove($service);
            $em->flush();
            $this->addFlash('success', 'La demande de service a été supprimée.');
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('app_my_services', ['_locale' => $request->getLocale()]);
    }
}
