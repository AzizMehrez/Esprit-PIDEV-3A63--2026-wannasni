<?php

namespace App\Controller\Admin;

use App\Entity\ServiceRequest;
use App\Entity\Intervention;
use App\Repository\NotificationRepository;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Service\InterventionValidatorService;
use App\Service\EmailService;
use App\Service\InterventionPdfGeneratorService;
use App\Service\InterventionEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Dompdf\Dompdf;
use Dompdf\Options;

#[Route("/admin/services")]
class ServiceAdminController extends AbstractController
{
    #[Route("/export/pdf", name: "admin_services_export_pdf")]
    public function exportPdf(Request $request, EntityManagerInterface $em): Response
    {
        $searchQuery = $request->query->get('q');
        $sortBy = $request->query->get('sort', 'createdAt');
        $sortOrder = $request->query->get('order', 'DESC');
        $statutFilter = $request->query->get('statut');
        
        $repository = $em->getRepository(ServiceRequest::class);
        $qb = $repository->createQueryBuilder('s');

        // Apply search filter
        if ($searchQuery) {
            $qb->where('s.typeService LIKE :query')
               ->orWhere('s.description LIKE :query')
               ->orWhere('s.seniorEmail LIKE :query')
               ->orWhere('s.seniorTelephone LIKE :query')
               ->orWhere('s.ville LIKE :query')
               ->setParameter('query', '%' . $searchQuery . '%');
        }

        // Apply status filter
        if ($statutFilter) {
            $qb->andWhere('s.statut = :statut')
               ->setParameter('statut', $statutFilter);
        }

        // Apply sorting
        $allowedSortFields = ['id', 'typeService', 'ville', 'niveauUrgence', 'statut', 'createdAt'];
        if (in_array($sortBy, $allowedSortFields)) {
            $qb->orderBy('s.' . $sortBy, strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC');
        } else {
            $qb->orderBy('s.createdAt', 'DESC');
        }

        $services = $qb->getQuery()->getResult();

        // Configure Dompdf
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($pdfOptions);

        $html = $this->renderView('admin/services/pdf.html.twig', [
            'services' => $services,
            'searchQuery' => $searchQuery
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="services_' . date('Y-m-d_His') . '.pdf"',
        ]);
    }
    #[Route("/", name: "admin_services")]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $searchQuery = $request->query->get('q');
        $sortBy = $request->query->get('sort', 'createdAt');
        $sortOrder = $request->query->get('order', 'DESC');
        $statutFilter = $request->query->get('statut');
        
        $repository = $em->getRepository(ServiceRequest::class);
        $qb = $repository->createQueryBuilder('s');

        // Apply search filter
        if ($searchQuery) {
            $qb->where('s.typeService LIKE :query')
               ->orWhere('s.description LIKE :query')
               ->orWhere('s.seniorEmail LIKE :query')
               ->orWhere('s.seniorTelephone LIKE :query')
               ->orWhere('s.ville LIKE :query')
               ->setParameter('query', '%' . $searchQuery . '%');
        }

        // Apply status filter
        if ($statutFilter) {
            $qb->andWhere('s.statut = :statut')
               ->setParameter('statut', $statutFilter);
        }

        // Apply sorting
        $allowedSortFields = ['id', 'typeService', 'ville', 'niveauUrgence', 'statut', 'createdAt'];
        if (in_array($sortBy, $allowedSortFields)) {
            $qb->orderBy('s.' . $sortBy, strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC');
        } else {
            $qb->orderBy('s.createdAt', 'DESC');
        }

        $services = $qb->getQuery()->getResult();

        return $this->render("admin/services/index.html.twig", [
            "services" => $services,
            "searchQuery" => $searchQuery,
            "sortBy" => $sortBy,
            "sortOrder" => $sortOrder,
            "statutFilter" => $statutFilter
        ]);
    }

    #[Route("/{id}", name: "admin_services_show", requirements: ["id" => "\d+"])]
    public function show(ServiceRequest $service, EntityManagerInterface $em, NotificationRepository $notificationRepo): Response
    {
        $intervention = $em->getRepository(Intervention::class)->findOneBy(["serviceRequest" => $service]);

        // Mark related notifications as read (or remove them)
        $notifications = $notificationRepo->findBy(['relatedId' => $service->getId(), 'type' => 'service_request', 'isRead' => false]);
        if (!empty($notifications)) {
            foreach ($notifications as $n) {
                $n->setIsRead(true);
            }
            $em->flush();
        }
        return $this->render("admin/services/show.html.twig", [
            "service" => $service,
            "intervention" => $intervention,
        ]);
    }

    #[Route("/{id}/assign", name: "admin_services_assign", requirements: ["id" => "\d+"], methods: ["GET", "POST"])]
    public function assign(
        ServiceRequest $service,
        Request $request,
        EntityManagerInterface $em,
        InterventionValidatorService $validatorService,
        EmailService $emailService,
        InterventionPdfGeneratorService $pdfGenerator,
        InterventionEmailService $interventionEmailService
    ): Response {
        $existingIntervention = $em->getRepository(Intervention::class)->findOneBy([
            "serviceRequest" => $service
        ]);

        if ($existingIntervention) {
            $this->addFlash("warning", "Ce service a deja ete assigne.");
            return $this->redirectToRoute("admin_interventions_edit", ["id" => $existingIntervention->getId()]);
        }

        $techniciens = $this->getTechniciensFromDatabase($em);

        if ($request->isMethod("POST")) {
            try {
                $technicienId = $request->request->get("technicien_id");
                if (empty($technicienId)) {
                    throw new ValidationException("Erreur de validation - Assignation", [
                        "technicien_id" => "Veuillez selectionner un technicien."
                    ]);
                }

                $technicien = null;
                foreach ($techniciens as $tech) {
                    if ($tech->getId() == $technicienId) {
                        $technicien = $tech;
                        break;
                    }
                }

                if (!$technicien) {
                    throw new ValidationException("Erreur de validation - Assignation", [
                        "technicien_id" => "Technicien selectionne introuvable."
                    ]);
                }

                $notes = $request->request->get("notes") ?? "";
                if (strlen($notes) > 2000) {
                    throw new ValidationException("Erreur de validation - Assignation", [
                        "notes" => "Les notes ne doivent pas depasser 2000 caracteres."
                    ]);
                }

                $dateDebut = $request->request->get("date_debut");
                if (!empty($dateDebut)) {
                    try {
                        new \DateTime($dateDebut);
                    } catch (\Exception $e) {
                        throw new ValidationException("Erreur de validation - Assignation", [
                            "date_debut" => "La date de debut est invalide."
                        ]);
                    }
                }

                $intervention = new Intervention();
                $intervention->setServiceRequest($service);
                $intervention->setIdEmploye($technicien->getId());
                $intervention->setTechnicienNom($technicien->getFullName());
                $intervention->setTechnicienEmail($technicien->getEmail());
                $intervention->setTechnicienTelephone($technicien->getPhone());
                $intervention->setCompetences($technicien->getSpecialite());
                $intervention->setTarifHoraire($technicien->getTarifHoraire() ?? 25.00);
                $intervention->setZoneIntervention($service->getVille() ?? "Non precisee");
                $intervention->setHeuresTravail(2);
                $intervention->setTypesServices($service->getTypeService());
                $intervention->setStatutActuel("assignee");
                $intervention->setNotes($notes);
                $intervention->setDateCreation(new \DateTime());

                if (!empty($dateDebut)) {
                    $intervention->setDateDebut(new \DateTime($dateDebut));
                }

                $service->setStatut("assigned");
                $service->setTechnicienId($technicien->getId());
                $service->setTechnicienNom($technicien->getFullName());
                $service->setDateAssignation(new \DateTime());

                $em->persist($intervention);
                $em->flush();

                // Send devis email to the senior (client)
                try {
                    $interventionEmailService->sendDevisToSenior($intervention);
                    
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
                    
                    $this->addFlash("success", "Technicien assigné avec succès !");
                    if (!empty($recipients)) {
                        $this->addFlash("success", "📧 Devis envoyé par email à : " . implode(", ", $recipients));
                    }
                } catch (\Throwable $e) {
                    // Log error but don't fail the assignment
                    error_log('Failed to send devis email: ' . $e->getMessage());
                    $this->addFlash("success", "Technicien assigné avec succès !");
                    $this->addFlash("warning", "⚠️ Attention : L'email n'a pas pu être envoyé. Raison : " . $e->getMessage());
                }

                return $this->redirectToRoute("admin_interventions");
            } catch (ValidationException $e) {
                foreach ($e->getErrors() as $field => $error) {
                    $this->addFlash("error", "$field: $error");
                }
            }
        }

        return $this->render("admin/services/assign.html.twig", [
            "service" => $service,
            "techniciens" => $techniciens,
        ]);
    }


    /**
     * Get technicians from database (users with @gmail.com email)
     */
    private function getTechniciensFromDatabase(EntityManagerInterface $em): array
    {
        return $em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.email LIKE :gmail')
            ->setParameter('gmail', '%@gmail.com')
            ->orderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
