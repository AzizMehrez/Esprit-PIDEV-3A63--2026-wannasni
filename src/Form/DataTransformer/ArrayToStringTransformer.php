<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

class ArrayToStringTransformer implements DataTransformerInterface
{
    /**
     * Transforme un array en string (pour afficher dans le formulaire)
     */
    public function transform($value): string
    {
        if (null === $value || empty($value)) {
            return '';
        }
        
        // Si c'est déjà une string, la retourner
        if (is_string($value)) {
            return $value;
        }
        
        // Convertir l'array en string séparée par des virgules
        return implode(', ', $value);
    }

    /**
     * Transforme une string en array (pour sauvegarder en BDD)
     */
    public function reverseTransform($value): array
    {
        if (null === $value || '' === $value) {
            return [];
        }
        
        // Séparer par virgules et nettoyer
        $items = explode(',', $value);
        $items = array_map('trim', $items);
        $items = array_filter($items); // Retirer les éléments vides
        
        return array_values($items);
    }
}
