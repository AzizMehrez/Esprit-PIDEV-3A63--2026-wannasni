<?php

namespace App\Controller\Front;

use App\Entity\ServiceRequest;
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
    public function request(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $user = $this->getUser();
            if (!$user) {
                $this->addFlash('error', 'Vous devez être connecté pour demander un service.');
                return $this->redirectToRoute('app_login');
            }

            $serviceRequest = new ServiceRequest();
            $serviceRequest->setUser($user);
            $serviceRequest->setTypeService($request->request->get('type_service', ''));
            $serviceRequest->setDescription($request->request->get('description', ''));
            $serviceRequest->setSeniorTelephone($request->request->get('senior_telephone'));
            $serviceRequest->setSeniorEmail($request->request->get('senior_email'));
            $serviceRequest->setAdresse($request->request->get('adresse'));
            $serviceRequest->setVille($request->request->get('ville'));
            $serviceRequest->setCodePostal($request->request->get('code_postal'));
            $serviceRequest->setNiveauUrgence($request->request->get('niveau_urgence', 'normale'));
            
            $dateSouhaitee = $request->request->get('date_souhaitee');
            if ($dateSouhaitee) {
                $serviceRequest->setDateSouhaitee(new \DateTime($dateSouhaitee));
            }
            
            $budgetMin = $request->request->get('budget_minimum');
            $budgetMax = $request->request->get('budget_maximum');
            if ($budgetMin) $serviceRequest->setBudgetMinimum($budgetMin);
            if ($budgetMax) $serviceRequest->setBudgetMaximum($budgetMax);
            
            $serviceRequest->setNotifierProches($request->request->has('notifier_proches'));
            $serviceRequest->setStatut('en_attente');

            $em->persist($serviceRequest);
            $em->flush();

            $this->addFlash('success', 'Votre demande de service a été envoyée avec succès !');
            return $this->redirectToRoute('app_my_services', ['_locale' => $request->getLocale()]);
        }

        return $this->render('front/services/request.html.twig', [
            'service_types' => $this->serviceTypes,
            'last_inputs' => [],
        ]);
    }

    #[Route('/{id}', name: 'app_services_show', methods: ['GET'])]
    public function show(ServiceRequest $service): Response
    {
        // Ensure user can only view their own services
        if ($service->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à ce service.');
        }

        return $this->render('front/services/show.html.twig', [
            'service' => $service,
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
