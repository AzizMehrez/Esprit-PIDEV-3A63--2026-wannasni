<?php

namespace App\Controller\Admin;

use App\Entity\Intervention;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Service\InterventionValidatorService;
use App\Service\InterventionPdfGeneratorService;
use App\Service\InterventionEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/interventions')]
class InterventionAdminController extends AbstractController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    #[Route('/', name: 'admin_interventions')]
    public function index(EntityManagerInterface $em): Response
    {
        $interventions = $em->getRepository(Intervention::class)->findAllWithServices();

        $stats = [
            'total' => count($interventions),
            'en_attente' => $em->getRepository(Intervention::class)->countByStatut('en_attente'),
            'assignee' => $em->getRepository(Intervention::class)->countByStatut('assignee'),
            'en_cours' => $em->getRepository(Intervention::class)->countByStatut('en_cours'),
            'terminee' => $em->getRepository(Intervention::class)->countByStatut('terminee'),
        ];

        return $this->render('admin/interventions/index.html.twig', [
            'interventions' => $interventions,
            'stats' => $stats,
        ]);
    }

    #[Route('/create', name: 'admin_interventions_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em, InterventionValidatorService $validatorService, InterventionEmailService $emailService): Response
    {
        $techniciens = $this->getTechniciensFromDatabase($em);
        $services = $em->getRepository(ServiceRequest::class)->findAll();

        if ($request->isMethod('POST')) {
            try {
                $validatorService->validateInterventionData($request->request->all());

                $intervention = new Intervention();

                $serviceId = $request->request->get('service_request_id');
                if ($serviceId) {
                    $service = $em->getRepository(ServiceRequest::class)->find($serviceId);
                    if ($service) {
                        $intervention->setServiceRequest($service);
                        $intervention->setTypesServices($service->getTypeService());
                    }
                }

                $intervention->setZoneIntervention($request->request->get('zone_intervention'));
                $intervention->setHeuresTravail((int)$request->request->get('heures_travail'));
                $intervention->setTarifHoraire($request->request->get('tarif_horaire'));
                $intervention->setCompetences($request->request->get('competences'));
                $intervention->setStatutActuel($request->request->get('statut') ?: 'en_attente');

                $technicienId = $request->request->get('technicien_id');
                if ($technicienId) {
                    foreach ($techniciens as $tech) {
                        if ($tech->getId() == $technicienId) {
                            $intervention->setIdEmploye($tech->getId());
                            $intervention->setTechnicienNom($tech->getFullName());
                            $intervention->setTechnicienEmail($tech->getEmail());
                            $intervention->setTechnicienTelephone($tech->getPhone());
                            if (!$intervention->getCompetences()) {
                                $intervention->setCompetences($tech->getSpecialite());
                            }
                            if (!$intervention->getTarifHoraire()) {
                                $intervention->setTarifHoraire($tech->getTarifHoraire() ?? 25.00);
                            }
                            break;
                        }
                    }
                }

                $notes = $request->request->get('notes');
                if ($notes) {
                    $intervention->setNotes($notes);
                }

                if ($intervention->getStatutActuel() === 'en_cours') {
                    $intervention->setDateDebut(new \DateTime());
                }
                if ($intervention->getStatutActuel() === 'terminee') {
                    $intervention->setDateFin(new \DateTime());
                }

                if ($intervention->getServiceRequest() && $technicienId) {
                    $service = $intervention->getServiceRequest();
                    $serviceStatut = match($intervention->getStatutActuel()) {
                        'en_attente' => 'pending',
                        'assignee' => 'assigned',
                        'en_cours' => 'in_progress',
                        'terminee' => 'completed',
                        default => 'pending'
                    };
                    $service->setStatut($serviceStatut);
                    $service->setTechnicienId($technicienId);
                    $service->setTechnicienNom($intervention->getTechnicienNom());
                    $service->setDateAssignation(new \DateTime());
                }

                $intervention->setDateCreation(new \DateTime());
                $em->persist($intervention);
                $em->flush();

                // Send devis email to the senior (client)
                $emailSent = false;
                $emailError = null;
                
                // Check if service exists before attempting email
                if (!$intervention->getServiceRequest()) {
                    $this->addFlash('success', 'L\'intervention a été créée avec succès !');
                    $this->addFlash('warning', '⚠️ Aucun service associé - Email non envoyé');
                    return $this->redirectToRoute('admin_interventions');
                }
                
                $service = $intervention->getServiceRequest();
                
                try {
                    $this->logger->info('Attempting to send devis email for intervention ' . $intervention->getId());
                    $emailService->sendDevisToSenior($intervention);
                    $emailSent = true;
                    
                    // Get recipient emails for feedback
                    $recipients = [];
                    if ($service->getSeniorEmail()) {
                        $recipients[] = $service->getSeniorEmail();
                    }
                    if ($service->getUser() && $service->getUser()->getEmail()) {
                        $userEmail = $service->getUser()->getEmail();
                        if (!in_array($userEmail, $recipients)) {
                            $recipients[] = $userEmail;
                        }
                    }
                    
                    $this->addFlash('success', 'L\'intervention a été créée avec succès !');
                    if (!empty($recipients)) {
                        $this->addFlash('success', '📧 Devis envoyé par email à : ' . implode(', ', $recipients));
                    }
                } catch (\Throwable $e) {
                    $emailError = $e->getMessage();
                    $this->logger->error('Failed to send devis email: ' . $emailError, [
                        'exception' => get_class($e),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->addFlash('success', 'L\'intervention a été créée avec succès !');
                    $this->addFlash('warning', '⚠️ Email non envoyé. Raison : ' . $emailError);
                }

                return $this->redirectToRoute('admin_interventions');
            } catch (ValidationException $e) {
                foreach ($e->getErrors() as $field => $error) {
                    $this->addFlash('error', "{$field}: {$error}");
                }
            }
        }

        return $this->render('admin/interventions/create.html.twig', [
            'techniciens' => $techniciens,
            'services' => $services,
        ]);
    }

    #[Route('/{id}', name: 'admin_interventions_show', requirements: ['id' => '\d+'])]
    public function show(Intervention $intervention): Response
    {
        return $this->render('admin/interventions/show.html.twig', [
            'intervention' => $intervention,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_interventions_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Intervention $intervention, Request $request, EntityManagerInterface $em, InterventionValidatorService $validatorService): Response
    {
        $techniciens = $this->getTechniciensFromDatabase($em);

        if ($request->isMethod('POST')) {
            try {
                $validatorService->validateInterventionData($request->request->all(), false);

                $statut = $request->request->get('statut');
                if ($statut) {
                    $intervention->setStatutActuel($statut);

                    if ($statut === 'en_cours' && !$intervention->getDateDebut()) {
                        $intervention->setDateDebut(new \DateTime());
                    }

                    if ($statut === 'terminee' && !$intervention->getDateFin()) {
                        $intervention->setDateFin(new \DateTime());
                    }

                    if ($intervention->getServiceRequest()) {
                        $service = $intervention->getServiceRequest();
                        $serviceStatut = match($statut) {
                            'en_attente' => 'pending',
                            'assignee' => 'assigned',
                            'en_cours' => 'in_progress',
                            'terminee' => 'completed',
                            default => 'pending'
                        };
                        $service->setStatut($serviceStatut);
                    }
                }

                $technicienId = $request->request->get('technicien_id');
                if ($technicienId) {
                    foreach ($techniciens as $tech) {
                        if ($tech->getId() == $technicienId) {
                            $intervention->setIdEmploye($tech->getId());
                            $intervention->setTechnicienNom($tech->getFullName());
                            $intervention->setTechnicienEmail($tech->getEmail());
                            $intervention->setTechnicienTelephone($tech->getPhone());
                            $intervention->setCompetences($tech->getSpecialite());
                            $intervention->setTarifHoraire($tech->getTarifHoraire() ?? 25.00);
                            break;
                        }
                    }
                }

                $heures = $request->request->get('heures_travail');
                if ($heures) {
                    $intervention->setHeuresTravail((int)$heures);
                }

                $tarif = $request->request->get('tarif_horaire');
                if ($tarif) {
                    $intervention->setTarifHoraire($tarif);
                }

                $zone = $request->request->get('zone_intervention');
                if ($zone) {
                    $intervention->setZoneIntervention($zone);
                }

                $notes = $request->request->get('notes');
                if ($notes !== null) {
                    $intervention->setNotes($notes);
                }

                $em->flush();

                $this->addFlash('success', 'L\'intervention a ete mise a jour avec succes !');
                return $this->redirectToRoute('admin_interventions');
            } catch (ValidationException $e) {
                foreach ($e->getErrors() as $field => $error) {
                    $this->addFlash('error', "{$field}: {$error}");
                }
            }
        }

        return $this->render('admin/interventions/edit.html.twig', [
            'intervention' => $intervention,
            'techniciens' => $techniciens,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_interventions_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Intervention $intervention, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $intervention->getId(), $request->request->get('_token'))) {
            if ($intervention->getServiceRequest()) {
                $service = $intervention->getServiceRequest();
                $service->setStatut('pending');
                $service->setTechnicienId(null);
                $service->setTechnicienNom(null);
            }
            $em->remove($intervention);
            $em->flush();

            $this->addFlash('success', 'L\'intervention a ete supprimee avec succes.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('admin_interventions');
    }

    #[Route('/{id}/pdf', name: 'admin_interventions_pdf', requirements: ['id' => '\d+'])]
    public function generatePdf(
        Intervention $intervention,
        InterventionPdfGeneratorService $pdfGenerator
    ): Response
    {
        try {
            $pdfContent = $pdfGenerator->generatePdf($intervention);

            return new Response(
                $pdfContent,
                200,
                [
                    'Content-Type'        => 'application/pdf',
                    'Content-Disposition' => "attachment; filename=\"intervention_{$intervention->getId()}.pdf\"",
                ]
            );
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la generation du PDF: ' . $e->getMessage());
            return $this->redirectToRoute('admin_interventions_show', ['id' => $intervention->getId()]);
        }
    }

    /**
     * Get technicians from database (users with user_domain = 'role.technicien')
     */
    private function getTechniciensFromDatabase(EntityManagerInterface $em): array
    {
        return $em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.userDomain = :domain')
            ->setParameter('domain', 'role.technicien')
            ->orderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
