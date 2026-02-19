<?php

namespace App\Service;

use App\Entity\HealthJournal;
use App\Entity\Treatment;
use App\Entity\User;
use App\Exception\UnauthorizedException;
use App\Exception\ValidationException;

/**
 * HealthService - Business logic for health data management
 * Enforces strict access control for sensitive health data
 */
class HealthService
{
    /**
     * Add health data with validation and anomaly detection
     */
    public function addHealthData(int $seniorId, array $data, int $accessorId, array $accessorRoles): HealthJournal
    {
        // Security: Check if accessor can add health data
        if (!$this->canManageHealthData($accessorId, $accessorRoles, $seniorId)) {
            throw new UnauthorizedException('Cannot manage health data for this user');
        }

        // Validate health values
        $this->validateHealthData($data);

        $journal = new HealthJournal();
        $journal->setId(rand(1, 10000));
        $journal->setSeniorId($seniorId);
        $journal->setDate(new \DateTime($data['date'] ?? 'now'));

        // Blood pressure
        if (isset($data['blood_pressure'])) {
            [$sys, $dia] = explode('/', $data['blood_pressure']);
            $journal->setBloodPressureSystolic((float) $sys);
            $journal->setBloodPressureDiastolic((float) $dia);

            // Anomaly detection
            if ($this->isAbnormalBloodPressure((float) $sys, (float) $dia)) {
                $this->triggerHealthAlert($seniorId, 'blood_pressure', $data['blood_pressure']);
            }
        }

        // Temperature
        if (isset($data['temperature'])) {
            $journal->setTemperature((float) $data['temperature']);

            if ($this->isAbnormalTemperature((float) $data['temperature'])) {
                $this->triggerHealthAlert($seniorId, 'temperature', $data['temperature']);
            }
        }

        // Heart rate
        if (isset($data['heart_rate'])) {
            $journal->setHeartRate((float) $data['heart_rate']);

            if ($this->isAbnormalHeartRate((float) $data['heart_rate'])) {
                $this->triggerHealthAlert($seniorId, 'heart_rate', $data['heart_rate']);
            }
        }

        // Weight
        if (isset($data['weight'])) {
            $journal->setWeight((float) $data['weight']);
        }

        // Blood sugar
        if (isset($data['blood_sugar'])) {
            $journal->setBloodSugar((float) $data['blood_sugar']);
        }

        // Notes
        if (isset($data['notes'])) {
            $journal->setNotes($data['notes']);
        }

        return $journal;
    }

    /**
     * Get health history with access control
     */
    public function getHealthHistory(int $seniorId, int $accessorId, array $accessorRoles): array
    {
        // Security check
        if (!$this->canViewHealthData($accessorId, $accessorRoles, $seniorId)) {
            throw new UnauthorizedException('Cannot view health data for this user');
        }

        // Mock: Return sample health entries
        $journal = new HealthJournal();
        $journal->setId(1);
        $journal->setSeniorId($seniorId);
        $journal->setBloodPressureSystolic(120);
        $journal->setBloodPressureDiastolic(80);
        $journal->setTemperature(36.8);
        $journal->setHeartRate(72);
        $journal->setWeight(72.5);

        return [$this->journalToArray($journal)];
    }

    /**
     * Prescribe treatment (doctors only)
     */
    public function prescribeTreatment(int $seniorId, array $data, int $doctorId, array $doctorRoles): Treatment
    {
        // Security: Only doctors can prescribe
        if (!in_array('ROLE_DOCTOR', $doctorRoles)) {
            throw new UnauthorizedException('Only doctors can prescribe treatments');
        }

        // Validation
        if (empty($data['medication'])) {
            throw new ValidationException('Medication name is required');
        }

        if (empty($data['dosage'])) {
            throw new ValidationException('Dosage is required');
        }

        $treatment = new Treatment();
        $treatment->setId(rand(1, 10000));
        $treatment->setSeniorId($seniorId);
        $treatment->setPrescribedByDoctorId($doctorId);
        $treatment->setMedication($data['medication']);
        $treatment->setDosage($data['dosage']);
        $treatment->setFrequency($data['frequency'] ?? 'daily');
        $treatment->setInstructions($data['instructions'] ?? null);
        $treatment->setStartDate(new \DateTime($data['start_date'] ?? 'now'));
        
        if (isset($data['end_date'])) {
            $treatment->setEndDate(new \DateTime($data['end_date']));
        }
        
        $treatment->setIsActive(true);

        return $treatment;
    }

    /**
     * Get active treatments for a senior
     */
    public function getActiveTreatments(int $seniorId, int $accessorId, array $accessorRoles): array
    {
        if (!$this->canViewHealthData($accessorId, $accessorRoles, $seniorId)) {
            throw new UnauthorizedException('Cannot view treatments for this user');
        }

        // Mock: Return sample treatments
        $treatment = new Treatment();
        $treatment->setId(1);
        $treatment->setSeniorId($seniorId);
        $treatment->setMedication('Aspirine 100mg');
        $treatment->setDosage('1 comprimé');
        $treatment->setFrequency('daily');
        $treatment->setIsActive(true);

        return [$this->treatmentToArray($treatment)];
    }

    /**
     * Validate health data values
     */
    private function validateHealthData(array $data): void
    {
        if (isset($data['temperature'])) {
            $temp = (float) $data['temperature'];
            if ($temp < 30 || $temp > 45) {
                throw new ValidationException('Invalid temperature value (must be 30-45°C)');
            }
        }

        if (isset($data['heart_rate'])) {
            $hr = (float) $data['heart_rate'];
            if ($hr < 40 || $hr > 200) {
                throw new ValidationException('Invalid heart rate (must be 40-200 bpm)');
            }
        }

        if (isset($data['blood_pressure'])) {
            if (!preg_match('/^\d+\/\d+$/', $data['blood_pressure'])) {
                throw new ValidationException('Blood pressure format must be systolic/diastolic (e.g., 120/80)');
            }
        }
    }

    /**
     * Check for abnormal blood pressure
     */
    private function isAbnormalBloodPressure(float $systolic, float $diastolic): bool
    {
        return $systolic > 140 || $systolic < 90 ||
               $diastolic > 90 || $diastolic < 60;
    }

    /**
     * Check for abnormal temperature
     */
    private function isAbnormalTemperature(float $temp): bool
    {
        return $temp > 38 || $temp < 35.5;
    }

    /**
     * Check for abnormal heart rate
     */
    private function isAbnormalHeartRate(float $hr): bool
    {
        return $hr > 100 || $hr < 60;
    }

    /**
     * Trigger health alert (notify family, doctor, etc.)
     */
    private function triggerHealthAlert(int $seniorId, string $type, $value): void
    {
        // Business logic: Log alert, notify family/doctor
        // Mock implementation - would integrate with notification service
    }

    /**
     * Check if user can manage (add/edit) health data
     */
    private function canManageHealthData(int $accessorId, array $roles, int $seniorId): bool
    {
        // Own data
        if ($accessorId === $seniorId) {
            return true;
        }

        // Doctors can manage patient data
        if (in_array('ROLE_DOCTOR', $roles)) {
            return true;
        }

        // Admin can manage all
        if (in_array('ROLE_ADMIN', $roles)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can view health data
     */
    private function canViewHealthData(int $accessorId, array $roles, int $seniorId): bool
    {
        // Own data
        if ($accessorId === $seniorId) {
            return true;
        }

        // Doctors
        if (in_array('ROLE_DOCTOR', $roles)) {
            return true;
        }

        // Family (read-only)
        if (in_array('ROLE_FAMILY', $roles)) {
            return true;
        }

        // Admin
        if (in_array('ROLE_ADMIN', $roles)) {
            return true;
        }

        return false;
    }

    /**
     * Convert journal to array
     */
    private function journalToArray(HealthJournal $journal): array
    {
        return [
            'id' => $journal->getId(),
            'date' => $journal->getDate()?->format('Y-m-d'),
            'blood_pressure' => $journal->getBloodPressure(),
            'heart_rate' => $journal->getHeartRate(),
            'temperature' => $journal->getTemperature(),
            'weight' => $journal->getWeight(),
            'blood_sugar' => $journal->getBloodSugar(),
            'notes' => $journal->getNotes(),
        ];
    }

    /**
     * Convert treatment to array
     */
    private function treatmentToArray(Treatment $treatment): array
    {
        return [
            'id' => $treatment->getId(),
            'medication' => $treatment->getMedication(),
            'dosage' => $treatment->getDosage(),
            'frequency' => $treatment->getFrequency(),
            'instructions' => $treatment->getInstructions(),
            'is_active' => $treatment->isActive(),
        ];
    }
}
