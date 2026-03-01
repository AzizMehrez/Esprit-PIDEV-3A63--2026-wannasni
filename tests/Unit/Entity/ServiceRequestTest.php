<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Intervention;
use App\Entity\ServiceRequest;
use App\Entity\User;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

class ServiceRequestTest extends TestCase
{
    private ServiceRequest $entity;

    protected function setUp(): void
    {
        $this->entity = new ServiceRequest();
    }

    // ─── Defaults ────────────────────────────────────────────────────────

    public function testDefaultStatutIsPending(): void
    {
        $this->assertSame('pending', $this->entity->getStatut());
    }

    public function testDefaultNiveauUrgenceIsNormale(): void
    {
        $this->assertSame('normale', $this->entity->getNiveauUrgence());
    }

    public function testDefaultNotifierProchesIsFalse(): void
    {
        $this->assertFalse($this->entity->isNotifierProches());
    }

    public function testCreatedAtIsSetAutomatically(): void
    {
        $now = new \DateTime();
        $createdAt = $this->entity->getCreatedAt();

        $this->assertInstanceOf(\DateTimeInterface::class, $createdAt);
        // Allow 2-second tolerance
        $this->assertEqualsWithDelta($now->getTimestamp(), $createdAt->getTimestamp(), 2);
    }

    public function testIdIsNullByDefault(): void
    {
        $this->assertNull($this->entity->getId());
    }

    // ─── Interventions Collection ────────────────────────────────────────

    public function testInterventionsCollectionIsEmptyOnCreation(): void
    {
        $interventions = $this->entity->getInterventions();

        $this->assertInstanceOf(Collection::class, $interventions);
        $this->assertCount(0, $interventions);
    }

    public function testAddInterventionSetsBackReference(): void
    {
        $intervention = new Intervention();
        $this->entity->addIntervention($intervention);

        $this->assertCount(1, $this->entity->getInterventions());
        $this->assertSame($this->entity, $intervention->getServiceRequest());
    }

    public function testAddSameInterventionTwiceDoesNotDuplicate(): void
    {
        $intervention = new Intervention();
        $this->entity->addIntervention($intervention);
        $this->entity->addIntervention($intervention);

        $this->assertCount(1, $this->entity->getInterventions());
    }

    public function testRemoveInterventionNullsBackReference(): void
    {
        $intervention = new Intervention();
        $this->entity->addIntervention($intervention);
        $this->entity->removeIntervention($intervention);

        $this->assertCount(0, $this->entity->getInterventions());
        $this->assertNull($intervention->getServiceRequest());
    }

    // ─── Fluent Setters ──────────────────────────────────────────────────

    public function testSettersReturnSelf(): void
    {
        $this->assertSame($this->entity, $this->entity->setSeniorTelephone('12345678'));
        $this->assertSame($this->entity, $this->entity->setSeniorEmail('test@gmail.com'));
        $this->assertSame($this->entity, $this->entity->setTypeService('plomberie'));
        $this->assertSame($this->entity, $this->entity->setDescription('desc'));
        $this->assertSame($this->entity, $this->entity->setAdresse('123 rue'));
        $this->assertSame($this->entity, $this->entity->setVille('Paris'));
        $this->assertSame($this->entity, $this->entity->setCodePostal('75001'));
        $this->assertSame($this->entity, $this->entity->setNiveauUrgence('haute'));
        $this->assertSame($this->entity, $this->entity->setDateSouhaitee(new \DateTime('+1 day')));
        $this->assertSame($this->entity, $this->entity->setBudgetMinimum('100'));
        $this->assertSame($this->entity, $this->entity->setBudgetMaximum('500'));
        $this->assertSame($this->entity, $this->entity->setNotifierProches(true));
        $this->assertSame($this->entity, $this->entity->setStatut('assigned'));
        $this->assertSame($this->entity, $this->entity->setTechnicienId(5));
        $this->assertSame($this->entity, $this->entity->setTechnicienNom('Jean'));
        $this->assertSame($this->entity, $this->entity->setNotesAdmin('note'));
        $this->assertSame($this->entity, $this->entity->setDateAssignation(new \DateTime()));
        $this->assertSame($this->entity, $this->entity->setDateDebut(new \DateTime()));
        $this->assertSame($this->entity, $this->entity->setDateFin(new \DateTime()));
        $this->assertSame($this->entity, $this->entity->setUser(new User()));
    }

    // ─── Getters / Setters Round-Trip ────────────────────────────────────

    public function testGettersReturnSetValues(): void
    {
        $date = new \DateTime('2026-06-01');
        $user = new User();

        $this->entity->setSeniorTelephone('98765432');
        $this->entity->setSeniorEmail('hello@gmail.com');
        $this->entity->setTypeService('electricite');
        $this->entity->setDescription('Un problème électrique');
        $this->entity->setAdresse('10 avenue');
        $this->entity->setVille('Lyon');
        $this->entity->setCodePostal('69001');
        $this->entity->setNiveauUrgence('haute');
        $this->entity->setDateSouhaitee($date);
        $this->entity->setBudgetMinimum('50.00');
        $this->entity->setBudgetMaximum('200.00');
        $this->entity->setNotifierProches(true);
        $this->entity->setStatut('completed');
        $this->entity->setTechnicienId(42);
        $this->entity->setTechnicienNom('Dupont');
        $this->entity->setNotesAdmin('RAS');
        $this->entity->setDateAssignation($date);
        $this->entity->setDateDebut($date);
        $this->entity->setDateFin($date);
        $this->entity->setUser($user);

        $this->assertSame('98765432', $this->entity->getSeniorTelephone());
        $this->assertSame('hello@gmail.com', $this->entity->getSeniorEmail());
        $this->assertSame('electricite', $this->entity->getTypeService());
        $this->assertSame('Un problème électrique', $this->entity->getDescription());
        $this->assertSame('10 avenue', $this->entity->getAdresse());
        $this->assertSame('Lyon', $this->entity->getVille());
        $this->assertSame('69001', $this->entity->getCodePostal());
        $this->assertSame('haute', $this->entity->getNiveauUrgence());
        $this->assertSame($date, $this->entity->getDateSouhaitee());
        $this->assertSame('50.00', $this->entity->getBudgetMinimum());
        $this->assertSame('200.00', $this->entity->getBudgetMaximum());
        $this->assertTrue($this->entity->isNotifierProches());
        $this->assertSame('completed', $this->entity->getStatut());
        $this->assertSame(42, $this->entity->getTechnicienId());
        $this->assertSame('Dupont', $this->entity->getTechnicienNom());
        $this->assertSame('RAS', $this->entity->getNotesAdmin());
        $this->assertSame($date, $this->entity->getDateAssignation());
        $this->assertSame($date, $this->entity->getDateDebut());
        $this->assertSame($date, $this->entity->getDateFin());
        $this->assertSame($user, $this->entity->getUser());
    }
}
