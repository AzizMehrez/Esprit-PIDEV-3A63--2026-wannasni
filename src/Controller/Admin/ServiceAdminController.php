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

#[Route("/admin/services")]
class ServiceAdminController extends AbstractController
{
    #[Route("/", name: "admin_services")]
    public function index(EntityManagerInterface $em): Response
    {
        $services = $em->getRepository(ServiceRequest::class)->findAll();
        return $this->render("admin/services/index.html.twig", [
            "services" => $services,
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
    ): Response
    {
        $existingIntervention = $em->getRepository(Intervention::class)->findOneBy([
            "serviceRequest" => $service
        ]);

        if ($existingIntervention) {
            $this->addFlash("warning", "Ce service a deja ete assigne.");
            return $this->redirectToRoute("admin_interventions_edit", ["id" => $existingIntervention->getId()]);
        }

        $techniciens = $this->getMockTechniciens();

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
                } catch (\Throwable $e) {
                    // Log error but don't fail the assignment
                    error_log('Failed to send devis email: ' . $e->getMessage());
                }

                $this->addFlash("success", "Technicien assigne avec succes !");
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


    private function getMockTechniciens(): array
    {
        $tech1 = new User();
        $tech1->setId(1)->setFirstName("Jean")->setLastName("Dupont")->setEmail("jean.dupont@wannasni.com")->setPhone("06 12 34 56 78")->setSpecialite("Transport")->setTarifHoraire(25.00)->setDisponible(true);

        $tech2 = new User();
        $tech2->setId(2)->setFirstName("Marie")->setLastName("Martin")->setEmail("marie.martin@wannasni.com")->setPhone("06 23 45 67 89")->setSpecialite("Menage")->setTarifHoraire(22.00)->setDisponible(true);

        $tech3 = new User();
        $tech3->setId(3)->setFirstName("Pierre")->setLastName("Durand")->setEmail("pierre.durand@wannasni.com")->setPhone("06 34 56 78 90")->setSpecialite("Courses")->setTarifHoraire(20.00)->setDisponible(false);

        $tech4 = new User();
        $tech4->setId(4)->setFirstName("Sophie")->setLastName("Bernard")->setEmail("sophie.bernard@wannasni.com")->setPhone("06 45 67 89 01")->setSpecialite("Accompagnement")->setTarifHoraire(28.00)->setDisponible(true);

        return [$tech1, $tech2, $tech3, $tech4];
    }
}