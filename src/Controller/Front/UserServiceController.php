<?php

namespace App\Controller\Front;

use App\Entity\ServiceRequest;
use App\Entity\Intervention;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserServiceController extends AbstractController
{
    #[Route('/{_locale}/my-services', name: 'app_my_services')]
    public function myServices(EntityManagerInterface $em): Response
    {
        $services = $em->getRepository(ServiceRequest::class)->findBy(
            [],
            ['createdAt' => 'DESC']
        );

        $myServices = [];
        foreach ($services as $service) {
            $myServices[] = [
                'id' => $service->getId(),
                'type' => $service->getTypeService(),
                'description' => $service->getDescription(),
                'requestedAt' => $service->getCreatedAt(),
                'scheduledFor' => $service->getDateSouhaitee(),
                'status' => $service->getStatut(),
                'adresse' => $service->getAdresse(),
                'ville' => $service->getVille(),
                'telephone' => $service->getSeniorTelephone(),
            ];
        }

        return $this->render('front/services/index.html.twig', [
            'my_services' => $myServices,
        ]);
    }

    #[Route('/{_locale}/services/request', name: 'app_services_request', methods: ['GET', 'POST'])]
    public function requestService(Request $request, EntityManagerInterface $em, NotificationService $notificationService, \Symfony\Component\Validator\Validator\ValidatorInterface $validator): Response
    {
        $serviceTypes = [
            ['id' => 'transport', 'name' => 'Transport', 'icon' => '🚗', 'description' => 'Courses, rendez-vous'],
            ['id' => 'menage', 'name' => 'Ménage', 'icon' => '🏠', 'description' => 'Nettoyage, rangement'],
            ['id' => 'courses', 'name' => 'Courses', 'icon' => '🛒', 'description' => 'Achats alimentaires'],
            ['id' => 'accompagnement', 'name' => 'Accompagnement', 'icon' => '🤝', 'description' => 'Sorties, visites'],
        ];

        if ($request->isMethod('POST')) {
            $serviceRequest = new ServiceRequest();

            // Informations de contact
            $serviceRequest->setSeniorTelephone($request->request->get('senior_telephone'));
            $serviceRequest->setSeniorEmail($request->request->get('senior_email'));

            // Service
            $serviceRequest->setTypeService($request->request->get('type_service') ?? ''); // Ensure string for validation
            $serviceRequest->setDescription($request->request->get('description') ?? '');

            // Adresse
            $serviceRequest->setAdresse($request->request->get('adresse'));
            $serviceRequest->setVille($request->request->get('ville'));
            $serviceRequest->setCodePostal($request->request->get('code_postal'));

            // Urgence et date
            $serviceRequest->setNiveauUrgence($request->request->get('niveau_urgence') ?? 'normale');

            if ($request->request->get('date_souhaitee')) {
                try {
                    $serviceRequest->setDateSouhaitee(
                        new \DateTime($request->request->get('date_souhaitee'))
                    );
                } catch (\Exception $e) {
                    // Ignore, validation will catch if logic requires valid date, though type error might occur before
                }
            }

            // Budget (cast to float or null)
            $budgetMin = $request->request->get('budget_minimum');
            $budgetMax = $request->request->get('budget_maximum');
            $serviceRequest->setBudgetMinimum($budgetMin !== null && $budgetMin !== '' ? (float) $budgetMin : null);
            $serviceRequest->setBudgetMaximum($budgetMax !== null && $budgetMax !== '' ? (float) $budgetMax : null);

            // Notification
            $serviceRequest->setNotifierProches($request->request->get('notifier_proches') === 'on');

            // VALIDATION
            $errors = $validator->validate($serviceRequest);

            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                // Return to form with inputs
                return $this->render('front/services/request.html.twig', [
                    'service_types' => $serviceTypes,
                    // Pass submitted values back to template
                    'last_inputs' => $request->request->all()
                ]);
            }

            $em->persist($serviceRequest);
            $em->flush();

            // Create an admin notification so the back-office can see new requests
            try {
                $notificationService->create('service_request', sprintf('Nouvelle demande de service #%d', $serviceRequest->getId()), $serviceRequest->getId());
            } catch (\Throwable $e) {
                // don't break user flow if notification fails
            }

            $this->addFlash('success', 'Votre demande a été envoyée avec succès !');
            return $this->redirectToRoute('app_my_services', ['_locale' => $request->getLocale()]);
        }

        return $this->render('front/services/request.html.twig', [
            'service_types' => $serviceTypes
        ]);
    }

    // ✅ NOUVELLE MÉTHODE : Voir les détails d'un service
    #[Route('/{_locale}/services/{id}/show', name: 'app_services_show', requirements: ['id' => '\d+'])]
    public function show(int $id, EntityManagerInterface $em): Response
    {
        $service = $em->getRepository(ServiceRequest::class)->find($id);

        if (!$service) {
            throw $this->createNotFoundException('Service non trouvé');
        }

        return $this->render('front/services/show.html.twig', [
            'service' => $service,
        ]);
    }

    // ✅ NOUVELLE MÉTHODE : Modifier un service
    #[Route('/{_locale}/services/{id}/edit', name: 'app_services_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, \Symfony\Component\Validator\Validator\ValidatorInterface $validator): Response
    {
        $service = $em->getRepository(ServiceRequest::class)->find($id);

        if (!$service) {
            throw $this->createNotFoundException('Service non trouvé');
        }

        $serviceTypes = [
            ['id' => 'transport', 'name' => 'Transport', 'icon' => '🚗', 'description' => 'Courses, rendez-vous'],
            ['id' => 'menage', 'name' => 'Ménage', 'icon' => '🏠', 'description' => 'Nettoyage, rangement'],
            ['id' => 'courses', 'name' => 'Courses', 'icon' => '🛒', 'description' => 'Achats alimentaires'],
            ['id' => 'accompagnement', 'name' => 'Accompagnement', 'icon' => '🤝', 'description' => 'Sorties, visites'],
        ];

        if ($request->isMethod('POST')) {
            // Mise à jour des informations
            $service->setSeniorTelephone($request->request->get('senior_telephone'));
            $service->setSeniorEmail($request->request->get('senior_email'));
            $service->setTypeService($request->request->get('type_service') ?? '');
            $service->setDescription($request->request->get('description') ?? '');
            $service->setAdresse($request->request->get('adresse'));
            $service->setVille($request->request->get('ville'));
            $service->setCodePostal($request->request->get('code_postal'));
            $service->setNiveauUrgence($request->request->get('niveau_urgence') ?? 'normale');

            if ($request->request->get('date_souhaitee')) {
                try {
                    $service->setDateSouhaitee(new \DateTime($request->request->get('date_souhaitee')));
                } catch (\Exception $e) {
                }
            }

            $budgetMin = $request->request->get('budget_minimum');
            $budgetMax = $request->request->get('budget_maximum');
            $service->setBudgetMinimum($budgetMin !== null && $budgetMin !== '' ? (float) $budgetMin : null);
            $service->setBudgetMaximum($budgetMax !== null && $budgetMax !== '' ? (float) $budgetMax : null);
            $service->setNotifierProches($request->request->get('notifier_proches') === 'on');

            // VALIDATION
            $errors = $validator->validate($service);
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                return $this->render('front/services/edit.html.twig', [
                    'service' => $service,
                    'service_types' => $serviceTypes,
                ]);
            }

            $em->flush();

            $this->addFlash('success', 'Service modifié avec succès !');
            return $this->redirectToRoute('app_my_services', ['_locale' => $request->getLocale()]);
        }

        return $this->render('front/services/edit.html.twig', [
            'service' => $service,
            'service_types' => $serviceTypes,
        ]);
    }

    // ✅ NOUVELLE MÉTHODE : Supprimer un service
    #[Route('/{_locale}/services/{id}/delete', name: 'app_services_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $service = $em->getRepository(ServiceRequest::class)->find($id);

        if (!$service) {
            throw $this->createNotFoundException('Service non trouvé');
        }

        // Vérification CSRF (optionnel mais recommandé)
        $submittedToken = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete' . $service->getId(), $submittedToken)) {
            // Supprimer d'abord les interventions associées (pour éviter les violation de contrainte étrangère)
            $interventions = $em->getRepository(Intervention::class)->findBy(['serviceRequest' => $service]);
            foreach ($interventions as $intervention) {
                $em->remove($intervention);
            }

            // Puis supprimer le service
            $em->remove($service);
            $em->flush();

            $this->addFlash('success', 'Service supprimé avec succès !');
        }

        return $this->redirectToRoute('app_my_services', ['_locale' => $request->getLocale()]);
    }
}