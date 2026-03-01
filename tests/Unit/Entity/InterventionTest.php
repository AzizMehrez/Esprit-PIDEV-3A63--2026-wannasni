<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Intervention;
use App\Entity\ServiceRequest;
use PHPUnit\Framework\TestCase;

class InterventionTest extends TestCase
{
    private Intervention $entity;

    protected function setUp(): void
    {
        $this->entity = new Intervention();
    }

    // ─── Defaults ────────────────────────────────────────────────────────

    public function testDefaultStatutActuelIsEnAttente(): void
    {
        $this->assertSame('en_attente', $this->entity->getStatutActuel());
    }

    public function testDefaultPaymentStatusIsPending(): void
    {
        $this->assertSame('pending', $this->entity->getPaymentStatus());
    }

    public function testIdIsNullByDefault(): void
    {
        $this->assertNull($this->entity->getId());
    }

    // ─── ServiceRequest Relationship ─────────────────────────────────────

    public function testSetServiceRequestBidirectional(): void
    {
        $serviceRequest = new ServiceRequest();
        $this->entity->setServiceRequest($serviceRequest);

        $this->assertSame($serviceRequest, $this->entity->getServiceRequest());
    }

    public function testSetServiceRequestToNull(): void
    {
        $serviceRequest = new ServiceRequest();
        $this->entity->setServiceRequest($serviceRequest);
        $this->entity->setServiceRequest(null);

        $this->assertNull($this->entity->getServiceRequest());
    }

    // ─── Fluent Setters ──────────────────────────────────────────────────

    public function testSettersReturnSelf(): void
    {
        $this->assertSame($this->entity, $this->entity->setIdEmploye(1));
        $this->assertSame($this->entity, $this->entity->setTypesServices('plomberie'));
        $this->assertSame($this->entity, $this->entity->setCompetences('tuyauterie'));
        $this->assertSame($this->entity, $this->entity->setTarifHoraire('50.00'));
        $this->assertSame($this->entity, $this->entity->setZoneIntervention('Paris'));
        $this->assertSame($this->entity, $this->entity->setHeuresTravail(8));
        $this->assertSame($this->entity, $this->entity->setStatutActuel('en_cours'));
        $this->assertSame($this->entity, $this->entity->setServiceRequest(new ServiceRequest()));
        $this->assertSame($this->entity, $this->entity->setTechnicienNom('Jean'));
        $this->assertSame($this->entity, $this->entity->setTechnicienEmail('jean@test.com'));
        $this->assertSame($this->entity, $this->entity->setTechnicienTelephone('0612345678'));
        $this->assertSame($this->entity, $this->entity->setNotes('note'));
        $this->assertSame($this->entity, $this->entity->setDateCreation(new \DateTime()));
        $this->assertSame($this->entity, $this->entity->setDateDebut(new \DateTime()));
        $this->assertSame($this->entity, $this->entity->setDateFin(new \DateTime()));
        $this->assertSame($this->entity, $this->entity->setPaymentStatus('paid'));
        $this->assertSame($this->entity, $this->entity->setPaymentDate(new \DateTime()));
        $this->assertSame($this->entity, $this->entity->setPaymentMethod('card'));
    }

    // ─── Getters / Setters Round-Trip ────────────────────────────────────

    public function testGettersReturnSetValues(): void
    {
        $date = new \DateTime('2026-03-15');
        $serviceRequest = new ServiceRequest();

        $this->entity->setIdEmploye(10);
        $this->entity->setTypesServices('electricite');
        $this->entity->setCompetences('câblage, prises');
        $this->entity->setTarifHoraire('75.50');
        $this->entity->setZoneIntervention('Tunis Nord');
        $this->entity->setHeuresTravail(6);
        $this->entity->setStatutActuel('terminee');
        $this->entity->setServiceRequest($serviceRequest);
        $this->entity->setTechnicienNom('Ahmed');
        $this->entity->setTechnicienEmail('ahmed@domain.com');
        $this->entity->setTechnicienTelephone('0698765432');
        $this->entity->setNotes('Travail bien fait');
        $this->entity->setDateCreation($date);
        $this->entity->setDateDebut($date);
        $this->entity->setDateFin($date);
        $this->entity->setPaymentStatus('paid');
        $this->entity->setPaymentDate($date);
        $this->entity->setPaymentMethod('cash');

        $this->assertSame(10, $this->entity->getIdEmploye());
        $this->assertSame('electricite', $this->entity->getTypesServices());
        $this->assertSame('câblage, prises', $this->entity->getCompetences());
        $this->assertSame('75.50', $this->entity->getTarifHoraire());
        $this->assertSame('Tunis Nord', $this->entity->getZoneIntervention());
        $this->assertSame(6, $this->entity->getHeuresTravail());
        $this->assertSame('terminee', $this->entity->getStatutActuel());
        $this->assertSame($serviceRequest, $this->entity->getServiceRequest());
        $this->assertSame('Ahmed', $this->entity->getTechnicienNom());
        $this->assertSame('ahmed@domain.com', $this->entity->getTechnicienEmail());
        $this->assertSame('0698765432', $this->entity->getTechnicienTelephone());
        $this->assertSame('Travail bien fait', $this->entity->getNotes());
        $this->assertSame($date, $this->entity->getDateCreation());
        $this->assertSame($date, $this->entity->getDateDebut());
        $this->assertSame($date, $this->entity->getDateFin());
        $this->assertSame('paid', $this->entity->getPaymentStatus());
        $this->assertSame($date, $this->entity->getPaymentDate());
        $this->assertSame('cash', $this->entity->getPaymentMethod());
    }
}
