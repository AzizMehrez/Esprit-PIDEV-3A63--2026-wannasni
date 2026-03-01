<?php

namespace App\Service;

class NutritionAnalysisService
{
    /**
     * Analyse si un produit est compatible avec TOUS les régimes
     * 
     * @param array $product Données du produit OpenFoodFacts
     * @param string $requestedDietType Le régime actuellement demandé par l'utilisateur
     * @return array Résultat global
     */
    public function analyze(array $product, string $requestedDietType): array
    {
        $nutriments = $product['nutriments'] ?? [];
        $categories = $product['categories_tags'] ?? [];
        $ingredients = $product['ingredients_text'] ?? '';
        $allergens = $product['allergens_tags'] ?? [];
        
        $dietsToCheck = [
            'diabétique' => 'Diabétique',
            'hypo_sodé' => 'Hypo-sodé',
            'sans_gluten' => 'Sans Gluten',
            'cardioprotecteur' => 'Cardioprotecteur',
            'perte_poids' => 'Perte de Poids',
            'prise_masse' => 'Prise de Masse'
        ];

        $globalResult = [
            'compatible' => true, // Global status for the REQUESTED diet
            'raison' => '',
            'details' => [], // Detailed points for the requested diet
            'all_diets' => [] // Status for ALL diets
        ];

        foreach ($dietsToCheck as $key => $label) {
            $dietResult = ['compatible' => true, 'raison' => 'Compatible', 'nom' => $label];
            
            switch ($key) {
                case 'diabétique': $this->checkDiabetic($nutriments, $dietResult); break;
                case 'hypo_sodé': $this->checkHypoSode($nutriments, $dietResult); break;
                case 'sans_gluten': $this->checkGlutenFree($categories, $allergens, $ingredients, $dietResult); break;
                case 'cardioprotecteur': $this->checkCardio($nutriments, $dietResult); break;
                case 'perte_poids': $this->checkWeightLoss($nutriments, $dietResult); break;
                case 'prise_masse': $this->checkMassGain($nutriments, $dietResult); break;
            }

            $globalResult['all_diets'][$key] = $dietResult;

            // Update main status if this is the requested diet
            // Normalize strings: strip accents, underscores, spaces, hyphens
            $normKey = strtolower(str_replace(['_', ' ', '-'], '', $this->stripAccents($key)));
            $normReq = strtolower(str_replace(['_', ' ', '-'], '', $this->stripAccents($requestedDietType)));
            
            if ($normKey === $normReq || str_contains($normReq, $normKey)) {
                 if (!$dietResult['compatible']) {
                     $globalResult['compatible'] = false;
                     $globalResult['raison'] = $dietResult['raison'];
                 } else {
                     $globalResult['raison'] = "Ce produit convient à votre régime $label.";
                 }
                 // Add to details for the main view
                 $globalResult['details'][] = [
                     'nom' => $label,
                     'compatible' => $dietResult['compatible'],
                     'raison' => $dietResult['raison']
                 ];
            }
        }

        // Handle 'normal' diet type: provide a general nutritional assessment
        if (empty($globalResult['raison'])) {
            $globalResult['raison'] = $this->buildNormalDietAnalysis($nutriments, $product, $globalResult);
        }
        
        // Nutri-Score check (General)
        if (isset($product['nutriscore_grade']) && strtolower($product['nutriscore_grade']) === 'e') {
            $globalResult['details'][] = [
                'nom' => 'Qualité Globale',
                'compatible' => false,
                'raison' => 'Nutri-Score E : Produit très riche.'
            ];
             if ($globalResult['compatible']) {
                 $globalResult['raison'] .= " (Attention: Nutri-Score E)";
             }
        }

        return $globalResult;
    }

    private function checkDiabetic(array $nutriments, array &$result): void
    {
        $sugar = isset($nutriments['sugars_100g']) ? (float)$nutriments['sugars_100g'] : 0;
        if ($sugar > 20) {
            $result['compatible'] = false;
            $result['raison'] = "Trop de sucre ({$sugar}g/100g)";
        } elseif ($sugar > 10) {
            $result['compatible'] = false;
            $result['raison'] = "Sucre élevé ({$sugar}g/100g)";
        }
    }

    private function checkWeightLoss(array $nutriments, array &$result): void
    {
        $calories = isset($nutriments['energy-kcal_100g']) ? (float)$nutriments['energy-kcal_100g'] : 0;
        if ($calories > 300) {
            $result['compatible'] = false;
            $result['raison'] = "Trop calorique ({$calories} kcal)";
        }
    }

    private function checkMassGain(array $nutriments, array &$result): void
    {
        $protein = isset($nutriments['proteins_100g']) ? (float)$nutriments['proteins_100g'] : 0;
        if ($protein < 5) {
            $result['compatible'] = false;
            $result['raison'] = "Peu de protéines ({$protein}g)";
        }
    }

    private function checkHypoSode(array $nutriments, array &$result): void
    {
        $salt = isset($nutriments['salt_100g']) ? (float)$nutriments['salt_100g'] : 0;
        if ($salt > 1) {
            $result['compatible'] = false;
            $result['raison'] = "Trop de sel ({$salt}g)";
        }
    }

    private function checkCardio(array $nutriments, array &$result): void
    {
        $satFat = isset($nutriments['saturated-fat_100g']) ? (float)$nutriments['saturated-fat_100g'] : 0;
        if ($satFat > 5) {
            $result['compatible'] = false;
            $result['raison'] = "Trop de graisses saturées ({$satFat}g)";
        }
    }

    private function checkGlutenFree(array $categories, array $allergens, string $ingredients, array &$result): void
    {
        $found = false;
        foreach ($allergens as $alo) {
            if (str_contains($alo, 'gluten')) $found = true;
        }
        if ($found) {
            $result['compatible'] = false;
            $result['raison'] = "Contient du gluten";
        }
    }

    /**
     * Build a general nutritional assessment for users with 'normal' diet type
     */
    private function buildNormalDietAnalysis(array $nutriments, array $product, array &$globalResult): string
    {
        $issues = [];
        $positives = [];

        $calories = isset($nutriments['energy-kcal_100g']) ? (float)$nutriments['energy-kcal_100g'] : 0;
        $sugar = isset($nutriments['sugars_100g']) ? (float)$nutriments['sugars_100g'] : 0;
        $salt = isset($nutriments['salt_100g']) ? (float)$nutriments['salt_100g'] : 0;
        $fat = isset($nutriments['fat_100g']) ? (float)$nutriments['fat_100g'] : 0;
        $satFat = isset($nutriments['saturated-fat_100g']) ? (float)$nutriments['saturated-fat_100g'] : 0;
        $protein = isset($nutriments['proteins_100g']) ? (float)$nutriments['proteins_100g'] : 0;
        $fiber = isset($nutriments['fiber_100g']) ? (float)$nutriments['fiber_100g'] : 0;

        // Check calories
        if ($calories > 400) {
            $issues[] = "Très calorique ({$calories} kcal/100g)";
        } elseif ($calories > 250) {
            $issues[] = "Assez calorique ({$calories} kcal/100g)";
        } elseif ($calories > 0 && $calories <= 150) {
            $positives[] = "Faible en calories ({$calories} kcal/100g)";
        }

        // Check sugar
        if ($sugar > 20) {
            $issues[] = "Riche en sucres ({$sugar}g/100g)";
        } elseif ($sugar <= 5 && $sugar > 0) {
            $positives[] = "Faible en sucres ({$sugar}g/100g)";
        }

        // Check salt
        if ($salt > 1.5) {
            $issues[] = "Très salé ({$salt}g/100g)";
        } elseif ($salt <= 0.3 && $salt > 0) {
            $positives[] = "Faible en sel ({$salt}g/100g)";
        }

        // Check saturated fat
        if ($satFat > 5) {
            $issues[] = "Riche en graisses saturées ({$satFat}g/100g)";
        }

        // Check protein
        if ($protein >= 10) {
            $positives[] = "Bonne source de protéines ({$protein}g/100g)";
        }

        // Check fiber
        if ($fiber >= 5) {
            $positives[] = "Riche en fibres ({$fiber}g/100g)";
        }

        // Build details
        foreach ($positives as $p) {
            $globalResult['details'][] = ['nom' => 'Atout nutritionnel', 'compatible' => true, 'raison' => $p];
        }
        foreach ($issues as $i) {
            $globalResult['details'][] = ['nom' => 'Point d\'attention', 'compatible' => false, 'raison' => $i];
        }

        // Determine overall compatibility and message
        if (count($issues) === 0) {
            $globalResult['compatible'] = true;
            return "Produit équilibré — aucun point d'attention particulier.";
        } elseif (count($issues) <= 1) {
            $globalResult['compatible'] = true;
            return "Produit acceptable avec modération. " . implode('. ', $issues) . ".";
        } else {
            $globalResult['compatible'] = false;
            return "Plusieurs points d'attention : " . implode(', ', $issues) . ".";
        }
    }

    /**
     * Strip accents from a string for reliable comparison
     */
    private function stripAccents(string $str): string
    {
        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
            if ($transliterator) {
                return $transliterator->transliterate($str);
            }
        }
        // Fallback: manual replacement (when intl extension is not available)
        return strtr(mb_strtolower($str), [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
            'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Ë' => 'e',
            'À' => 'a', 'Â' => 'a', 'Ä' => 'a',
            'Î' => 'i', 'Ï' => 'i',
            'Ô' => 'o', 'Ö' => 'o',
            'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u',
            'Ç' => 'c',
        ]);
    }
}
