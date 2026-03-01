<?php

namespace App\Tests\Unit\Service;

use App\Exception\ValidationException;
use App\Service\InterventionValidatorService;
use PHPUnit\Framework\TestCase;

class InterventionValidatorServiceTest extends TestCase
{
    private InterventionValidatorService $validator;

    protected function setUp(): void
    {
        $this->validator = new InterventionValidatorService();
    }

    // ─── Helper ──────────────────────────────────────────────────────────

    /**
     * Returns a fully valid data array for creation.
     */
    private function validData(): array
    {
        return [
            'zone_intervention' => 'Tunis Centre',
            'competences'       => 'Plomberie générale',
            'heures_travail'    => 8,
            'tarif_horaire'     => 50,
            'statut'            => 'en_attente',
            'technicien_email'  => 'tech@domain.com',
            'notes'             => '',
        ];
    }

    // =====================================================================
    //  validateInterventionData — Happy path
    // =====================================================================

    public function testValidDataDoesNotThrow(): void
    {
        $this->validator->validateInterventionData($this->validData());
        $this->addToAssertionCount(1); // reached = no exception
    }

    // ─── Zone ────────────────────────────────────────────────────────────

    public function testEmptyZoneThrows(): void
    {
        $data = $this->validData();
        $data['zone_intervention'] = '';

        $this->expectException(ValidationException::class);
        $this->validator->validateInterventionData($data);
    }

    public function testZoneTooShortThrows(): void
    {
        $data = $this->validData();
        $data['zone_intervention'] = 'A';

        try {
            $this->validator->validateInterventionData($data);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('zone_intervention', $e->getErrors());
            $this->assertStringContainsString('2 caractères', $e->getErrors()['zone_intervention']);
        }
    }

    public function testZoneTooLongThrows(): void
    {
        $data = $this->validData();
        $data['zone_intervention'] = str_repeat('Z', 256);

        try {
            $this->validator->validateInterventionData($data);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('zone_intervention', $e->getErrors());
        }
    }

    // ─── Compétences ─────────────────────────────────────────────────────

    public function testEmptyCompetencesOnCreationThrows(): void
    {
        $data = $this->validData();
        $data['competences'] = '';

        $this->expectException(ValidationException::class);
        $this->validator->validateInterventionData($data, true);
    }

    public function testEmptyCompetencesOnUpdateDoesNotThrow(): void
    {
        $data = $this->validData();
        $data['competences'] = '';

        $this->validator->validateInterventionData($data, false);
        $this->addToAssertionCount(1);
    }

    public function testCompetencesTooLongThrows(): void
    {
        $data = $this->validData();
        $data['competences'] = str_repeat('X', 256);

        try {
            $this->validator->validateInterventionData($data);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('competences', $e->getErrors());
        }
    }

    // ─── Heures de travail ───────────────────────────────────────────────

    public function testHeuresNonNumericThrows(): void
    {
        $data = $this->validData();
        $data['heures_travail'] = 'abc';

        $this->expectException(ValidationException::class);
        $this->validator->validateInterventionData($data);
    }

    public function testHeuresZeroThrows(): void
    {
        $data = $this->validData();
        $data['heures_travail'] = 0;

        $this->expectException(ValidationException::class);
        $this->validator->validateInterventionData($data);
    }

    public function testHeuresAbove24Throws(): void
    {
        $data = $this->validData();
        $data['heures_travail'] = 25;

        try {
            $this->validator->validateInterventionData($data);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('heures_travail', $e->getErrors());
            $this->assertStringContainsString('24', $e->getErrors()['heures_travail']);
        }
    }

    public function testHeuresBoundariesAccepted(): void
    {
        // 1 hour
        $data = $this->validData();
        $data['heures_travail'] = 1;
        $this->validator->validateInterventionData($data);

        // 24 hours
        $data['heures_travail'] = 24;
        $this->validator->validateInterventionData($data);

        $this->addToAssertionCount(1);
    }

    // ─── Tarif horaire ───────────────────────────────────────────────────

    public function testTarifNonNumericThrows(): void
    {
        $data = $this->validData();
        $data['tarif_horaire'] = 'abc';

        $this->expectException(ValidationException::class);
        $this->validator->validateInterventionData($data);
    }

    public function testTarifAbove10000Throws(): void
    {
        $data = $this->validData();
        $data['tarif_horaire'] = 10001;

        try {
            $this->validator->validateInterventionData($data);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tarif_horaire', $e->getErrors());
        }
    }

    public function testTarifBoundariesAccepted(): void
    {
        // Tarif = 1 (just above empty/falsy)
        $data = $this->validData();
        $data['tarif_horaire'] = 1;
        $this->validator->validateInterventionData($data);

        // Tarif = 10000
        $data['tarif_horaire'] = 10000;
        $this->validator->validateInterventionData($data);

        $this->addToAssertionCount(1);
    }

    // ─── Email ───────────────────────────────────────────────────────────

    public function testInvalidEmailThrows(): void
    {
        $data = $this->validData();
        $data['technicien_email'] = 'not-an-email';

        try {
            $this->validator->validateInterventionData($data);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('technicien_email', $e->getErrors());
        }
    }

    // ─── Statut ──────────────────────────────────────────────────────────

    public function testInvalidStatutThrows(): void
    {
        $data = $this->validData();
        $data['statut'] = 'invalid_status';

        $this->expectException(ValidationException::class);
        $this->validator->validateInterventionData($data);
    }

    /**
     * @dataProvider validStatutProvider
     */
    public function testAllValidStatutsAccepted(string $statut): void
    {
        $data = $this->validData();
        $data['statut'] = $statut;
        // If statut ≠ en_attente, a technicien_id is needed
        if ($statut !== 'en_attente') {
            $data['technicien_id'] = 42;
        }

        $this->validator->validateInterventionData($data);
        $this->addToAssertionCount(1);
    }

    public function validStatutProvider(): array
    {
        return [
            'en_attente' => ['en_attente'],
            'assignee'   => ['assignee'],
            'en_cours'   => ['en_cours'],
            'terminee'   => ['terminee'],
        ];
    }

    // ─── Technicien required ─────────────────────────────────────────────

    public function testTechnicienRequiredWhenStatutNotEnAttente(): void
    {
        $data = $this->validData();
        $data['statut'] = 'assignee';
        // Deliberately omit technicien_id

        try {
            $this->validator->validateInterventionData($data);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('technicien_id', $e->getErrors());
        }
    }

    // ─── Notes ───────────────────────────────────────────────────────────

    public function testNotesTooLongThrows(): void
    {
        $data = $this->validData();
        $data['notes'] = str_repeat('N', 2001);

        try {
            $this->validator->validateInterventionData($data);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('notes', $e->getErrors());
        }
    }

    // ─── Date format ─────────────────────────────────────────────────────

    public function testInvalidDateThrows(): void
    {
        $data = $this->validData();
        $data['date_debut'] = 'not-a-date-!!';

        try {
            $this->validator->validateInterventionData($data);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('date_debut', $e->getErrors());
        }
    }

    public function testValidDateAccepted(): void
    {
        $data = $this->validData();
        $data['date_debut'] = '2026-04-01 10:00';

        $this->validator->validateInterventionData($data);
        $this->addToAssertionCount(1);
    }

    // ─── Multiple errors accumulated ─────────────────────────────────────

    public function testMultipleErrorsAccumulated(): void
    {
        $data = [
            'zone_intervention' => '',        // error
            'competences'       => '',         // error (creation)
            'heures_travail'    => 'bad',      // error
            'tarif_horaire'     => 'bad',      // error
            'statut'            => 'en_attente',
        ];

        try {
            $this->validator->validateInterventionData($data, true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('zone_intervention', $errors);
            $this->assertArrayHasKey('competences', $errors);
            $this->assertArrayHasKey('heures_travail', $errors);
            $this->assertArrayHasKey('tarif_horaire', $errors);
            $this->assertGreaterThanOrEqual(4, count($errors));
        }
    }

    // =====================================================================
    //  validateServiceAssignment
    // =====================================================================

    public function testValidAssignmentDoesNotThrow(): void
    {
        $data = [
            'technicien_id'     => 5,
            'zone_intervention' => 'Ariana',
            'heures_travail'    => 4,
            'tarif_horaire'     => 30,
            'notes'             => '',
        ];

        $this->validator->validateServiceAssignment($data);
        $this->addToAssertionCount(1);
    }

    public function testAssignmentMissingTechnicienThrows(): void
    {
        $data = [
            'zone_intervention' => 'Ariana',
            'heures_travail'    => 4,
            'tarif_horaire'     => 30,
        ];

        try {
            $this->validator->validateServiceAssignment($data);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('technicien_id', $e->getErrors());
        }
    }

    public function testAssignmentMissingZoneThrows(): void
    {
        $data = [
            'technicien_id'  => 5,
            'heures_travail' => 4,
            'tarif_horaire'  => 30,
        ];

        try {
            $this->validator->validateServiceAssignment($data);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('zone_intervention', $e->getErrors());
        }
    }

    public function testAssignmentHeuresOutOfRangeThrows(): void
    {
        $data = [
            'technicien_id'     => 5,
            'zone_intervention' => 'Ariana',
            'heures_travail'    => 25,
            'tarif_horaire'     => 30,
        ];

        try {
            $this->validator->validateServiceAssignment($data);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('heures_travail', $e->getErrors());
        }
    }

    public function testAssignmentNegativeTarifThrows(): void
    {
        $data = [
            'technicien_id'     => 5,
            'zone_intervention' => 'Ariana',
            'heures_travail'    => 4,
            'tarif_horaire'     => -10,
        ];

        try {
            $this->validator->validateServiceAssignment($data);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tarif_horaire', $e->getErrors());
        }
    }
}
