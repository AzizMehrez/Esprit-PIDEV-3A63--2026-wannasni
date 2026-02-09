<?php

namespace App\Service;

use App\Exception\ValidationException;

class InterventionValidatorService
{
    public function validateInterventionData(array $data, bool $isCreation = true): void
    {
        $errors = [];

        // Validation: Zone d'intervention
        $zone = trim($data['zone_intervention'] ?? '');
        if (empty($zone)) {
            $errors['zone_intervention'] = 'La zone d\'intervention est obligatoire.';
        } elseif (strlen($zone) < 2) {
            $errors['zone_intervention'] = 'La zone d\'intervention doit contenir au moins 2 caractères.';
        } elseif (strlen($zone) > 255) {
            $errors['zone_intervention'] = 'La zone d\'intervention ne doit pas dépasser 255 caractères.';
        }

        // Validation: Compétences (obligatoire uniquement à la création)
        $competences = trim($data['competences'] ?? '');
        if ($isCreation && empty($competences)) {
            $errors['competences'] = 'Les compétences sont obligatoires.';
        } elseif (!empty($competences) && strlen($competences) > 255) {
            $errors['competences'] = 'Les compétences ne doivent pas dépasser 255 caractères.';
        }

        // Validation: Heures de travail
        $heures = $data['heures_travail'] ?? null;
        if (empty($heures) || !is_numeric($heures)) {
            $errors['heures_travail'] = 'Les heures de travail doivent être un nombre valide.';
        } elseif ((int)$heures < 1) {
            $errors['heures_travail'] = 'Les heures de travail doivent être au moins 1.';
        } elseif ((int)$heures > 24) {
            $errors['heures_travail'] = 'Les heures de travail ne doivent pas dépasser 24.';
        }

        // Validation: Tarif horaire
        $tarif = $data['tarif_horaire'] ?? null;
        if (empty($tarif) || !is_numeric($tarif)) {
            $errors['tarif_horaire'] = 'Le tarif horaire doit être un nombre valide.';
        } elseif ((float)$tarif < 0) {
            $errors['tarif_horaire'] = 'Le tarif horaire ne peut pas être négatif.';
        } elseif ((float)$tarif > 10000) {
            $errors['tarif_horaire'] = 'Le tarif horaire semble aberrant (max 10000€).';
        }

        // Validation: Email du technicien si fourni
        $email = trim($data['technicien_email'] ?? '');
        if (!empty($email)) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['technicien_email'] = 'L\'adresse email du technicien n\'est pas valide.';
            }
        }

        // Validation: Statut
        $statut = $data['statut'] ?? '';
        $validStatuts = ['en_attente', 'assignee', 'en_cours', 'terminee'];
        if (!in_array($statut, $validStatuts)) {
            $errors['statut'] = 'Le statut sélectionné est invalide.';
        }

        // Validation: Technicien (optionnel mais recommandé pour certains statuts)
        $technicienId = $data['technicien_id'] ?? null;
        if ($statut !== 'en_attente' && empty($technicienId)) {
            $errors['technicien_id'] = 'Un technicien doit être assigné pour ce statut.';
        }

        // Validation: Notes
        $notes = $data['notes'] ?? '';
        if (strlen($notes) > 2000) {
            $errors['notes'] = 'Les notes ne doivent pas dépasser 2000 caractères.';
        }

        // Validation: Date de début (format)
        if (!empty($data['date_debut'])) {
            try {
                new \DateTime($data['date_debut']);
            } catch (\Exception $e) {
                $errors['date_debut'] = 'La date de début est invalide.';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Erreurs de validation détectées', $errors);
        }
    }

    public function validateServiceAssignment(array $data): void
    {
        $errors = [];

        // Validation: Technicien (obligatoire)
        $technicienId = $data['technicien_id'] ?? null;
        if (empty($technicienId)) {
            $errors['technicien_id'] = 'Veuillez sélectionner un technicien.';
        }

        // Validation: Zone d'intervention (obligatoire)
        $zone = trim($data['zone_intervention'] ?? '');
        if (empty($zone)) {
            $errors['zone_intervention'] = 'La zone d\'intervention est obligatoire.';
        }

        // Validation: Heures de travail (obligatoire et valide)
        $heures = $data['heures_travail'] ?? null;
        if (empty($heures) || !is_numeric($heures)) {
            $errors['heures_travail'] = 'Les heures de travail doivent être un nombre valide.';
        } elseif ((int)$heures < 1 || (int)$heures > 24) {
            $errors['heures_travail'] = 'Les heures de travail doivent être entre 1 et 24.';
        }

        // Validation: Tarif horaire (obligatoire et valide)
        $tarif = $data['tarif_horaire'] ?? null;
        if (empty($tarif) || !is_numeric($tarif)) {
            $errors['tarif_horaire'] = 'Le tarif horaire doit être un nombre valide.';
        } elseif ((float)$tarif < 0) {
            $errors['tarif_horaire'] = 'Le tarif horaire ne peut pas être négatif.';
        }

        // Validation: Notes (optionnel)
        $notes = trim($data['notes'] ?? '');
        if (strlen($notes) > 2000) {
            $errors['notes'] = 'Les notes ne doivent pas dépasser 2000 caractères.';
        }

        // Validation: Date de début (optionnelle mais si fournie, doit être valide)
        if (!empty($data['date_debut'])) {
            try {
                new \DateTime($data['date_debut']);
            } catch (\Exception $e) {
                $errors['date_debut'] = 'La date de début est invalide.';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Erreurs de validation - Assignation', $errors);
        }
    }
}
