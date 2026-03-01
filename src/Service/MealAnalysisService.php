<?php

namespace App\Service;

use App\Entity\RegimePrescrit;
use App\Entity\SuiviRepas;
use App\Service\AIPrompts;

class MealAnalysisService
{
    private $geminiService;
    private $usdaService;

    public function __construct(GeminiService $geminiService, USDAService $usdaService)
    {
        $this->geminiService = $geminiService;
        $this->usdaService = $usdaService;
    }

    public function processMealPhoto(string $imagePath, RegimePrescrit $regime): SuiviRepas
    {
        $suivi = new SuiviRepas();
        $suivi->setPhotoUrl($imagePath); // In real app, this would be a URL after upload
        $suivi->setRegimePrescrit($regime);
        $suivi->setSenior($regime->getUser());

        // 1. Analyze with Gemini
        $analysis = $this->geminiService->analyzeImage($imagePath);
        
        $foods = $analysis; // Assuming Gemini returns the JSON array directly
        if (isset($analysis['error'])) {
            // Handle error, maybe set a flag or log it
            $foods = [];
        }
        
        $suivi->setAlimentsIdentifies($foods);

        // 2. Calculate Calories (Simplified approximation)
        $totalCalories = 0;
        foreach ($foods as $food) {
            // Logic to get calories from USDA or estimate
            // For now, let's assume Gemini might estimate or we skip precise calc
            // $usdaInfo = $this->usdaService->searchFood($food['nom']);
            // ... processing USDA data to get specific calories ...
            
            // Placeholder estimation if Gemini didn't provide it
            $totalCalories += 200; // Basic average per item
        }
        $suivi->setCaloriesCalculees($totalCalories);

        // 3. Conformity Check
        $isConforme = true;
        $feedbackParts = [];
        
        // Basic check based on forbidden foods (interdits)
        // This relies on RegimePrescrit having a way to get forbidden foods
        // Let's assume RegimePrescrit has getAlimentsInterdits() which returns a string or array
        // Adaptation based on what I see in codebase might be needed
        
        // 4. Generate AI Feedback
        $promptContext = [
            'regime' => $regime->getTypeRegime(),
            'aliments' => json_encode($foods),
            'calories' => $totalCalories,
            'caloriesMax' => $regime->getCaloriesJournalieres()
        ];
        
        $feedback = $this->geminiService->generateText(AIPrompts::FEEDBACK_REPAS, $promptContext);
        $suivi->setCommentairesIA($feedback);
        
        // Determining conformity based on AI sentiment or specific rules could be added here
        // For now, simple default
        $suivi->setEstConforme($isConforme);

        return $suivi;
    }
}
