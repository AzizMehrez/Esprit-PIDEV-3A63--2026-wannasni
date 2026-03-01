<?php

namespace App\Service;

use App\Entity\Beverage;
use App\Entity\BeverageLog;
use App\Entity\RegimePrescrit;
use App\Entity\User;
use App\Repository\BeverageLogRepository;
use App\Repository\BeverageRepository;
use Doctrine\ORM\EntityManagerInterface;

class SommelierService
{
    // Base de données intégrée des boissons (catalogue par défaut)
    private const BEVERAGE_CATALOG = [
        // ─── Thés ───
        ['name' => 'Thé vert Matcha', 'category' => 'thé', 'calories' => 3, 'hydration' => 85, 'sugar_free' => true, 'caffeine_free' => false,
         'benefits' => ['Riche en antioxydants', 'Booste le métabolisme', 'Améliore la concentration'],
         'moments' => ['matin', 'après-midi'], 'pairing' => ['poisson', 'riz', 'salade'],
         'regimes' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
         'description' => 'Thé vert japonais finement moulu, riche en L-théanine et catéchines. Énergie douce et durable.',
         'origin' => 'Japon', 'temp_min' => 70, 'temp_max' => 80,
         'preparation' => 'Tamiser 1-2g de matcha. Verser 70ml d\'eau à 70-80°C. Fouetter vigoureusement en zigzag avec un chasen.'],
        ['name' => 'Thé vert Sencha', 'category' => 'thé', 'calories' => 2, 'hydration' => 88, 'sugar_free' => true, 'caffeine_free' => false,
         'benefits' => ['Antioxydant', 'Aide à la digestion', 'Favorise l\'hydratation'],
         'moments' => ['matin', 'déjeuner', 'après-midi'], 'pairing' => ['sushi', 'légumes', 'riz'],
         'regimes' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
         'description' => 'Le thé vert le plus populaire au Japon. Notes herbacées fraîches et légèrement sucrées.',
         'origin' => 'Japon', 'temp_min' => 70, 'temp_max' => 80,
         'preparation' => 'Infuser 2g dans 200ml d\'eau à 70-80°C pendant 1-2 minutes.'],
        ['name' => 'Thé noir Earl Grey', 'category' => 'thé', 'calories' => 2, 'hydration' => 82, 'sugar_free' => true, 'caffeine_free' => false,
         'benefits' => ['Stimulant naturel', 'Antioxydant', 'Favorise la digestion'],
         'moments' => ['matin', 'après-midi'], 'pairing' => ['pâtisseries', 'fromage', 'biscuits'],
         'regimes' => ['normal', 'sans_gluten', 'cardioprotecteur'],
         'description' => 'Thé noir aromatisé à la bergamote. Classique élégant, idéal pour le petit-déjeuner.',
         'origin' => 'Angleterre', 'temp_min' => 90, 'temp_max' => 95,
         'preparation' => 'Infuser 1 sachet ou 2g dans 250ml d\'eau bouillante pendant 3-5 minutes.'],
        ['name' => 'Thé blanc Bai Mu Dan', 'category' => 'thé', 'calories' => 1, 'hydration' => 90, 'sugar_free' => true, 'caffeine_free' => false,
         'benefits' => ['Très riche en antioxydants', 'Anti-âge', 'Protège la peau'],
         'moments' => ['matin', 'après-midi', 'soirée'], 'pairing' => ['fruits', 'desserts légers'],
         'regimes' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
         'description' => 'Thé blanc délicat, peu transformé. Notes florales et mielleuses avec un minimum de caféine.',
         'origin' => 'Chine (Fujian)', 'temp_min' => 75, 'temp_max' => 85,
         'preparation' => 'Infuser 3g dans 250ml d\'eau à 75-85°C pendant 4-5 minutes.'],
        ['name' => 'Rooibos', 'category' => 'thé', 'calories' => 2, 'hydration' => 92, 'sugar_free' => true, 'caffeine_free' => true,
         'benefits' => ['Sans caféine', 'Riche en minéraux', 'Anti-inflammatoire', 'Bon pour la peau'],
         'moments' => ['tout_moment'], 'pairing' => ['viandes', 'curry', 'desserts'],
         'regimes' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
         'description' => 'Tisane sud-africaine naturellement sucrée, sans caféine. Riche en antioxydants et minéraux.',
         'origin' => 'Afrique du Sud', 'temp_min' => 95, 'temp_max' => 100,
         'preparation' => 'Infuser 2g dans 250ml d\'eau bouillante pendant 5-7 minutes. Peut être infusé longtemps sans amertume.'],

        // ─── Cafés ───
        ['name' => 'Espresso', 'category' => 'café', 'calories' => 3, 'hydration' => 60, 'sugar_free' => true, 'caffeine_free' => false,
         'benefits' => ['Stimulant', 'Riche en antioxydants', 'Améliore la concentration'],
         'moments' => ['matin', 'après-midi'], 'pairing' => ['chocolat', 'pâtisseries', 'fromage'],
         'regimes' => ['normal', 'sans_gluten'],
         'description' => 'Café concentré intense. 25-30ml de pure énergie avec une crema dorée.',
         'origin' => 'Italie', 'temp_min' => 90, 'temp_max' => 96,
         'preparation' => 'Mouture fine, extraction sous pression 9 bars, 25-30 secondes.'],
        ['name' => 'Café filtre Arabica doux', 'category' => 'café', 'calories' => 2, 'hydration' => 75, 'sugar_free' => true, 'caffeine_free' => false,
         'benefits' => ['Stimulant modéré', 'Antioxydants', 'Bénéfique pour le foie'],
         'moments' => ['matin', 'après-midi'], 'pairing' => ['viennoiseries', 'fruits secs'],
         'regimes' => ['normal', 'sans_gluten', 'cardioprotecteur'],
         'description' => 'Café filtre doux aux notes fruitées et florales, moins acide que le robusta.',
         'origin' => 'Éthiopie/Colombie', 'temp_min' => 92, 'temp_max' => 96,
         'preparation' => 'Mouture moyenne, 7g pour 125ml d\'eau à 92-96°C. Extraction 4-6 minutes.'],
        ['name' => 'Décaféiné naturel', 'category' => 'café', 'calories' => 2, 'hydration' => 78, 'sugar_free' => true, 'caffeine_free' => true,
         'benefits' => ['Goût du café sans la caféine', 'Antioxydants préservés'],
         'moments' => ['tout_moment'], 'pairing' => ['desserts', 'fromage'],
         'regimes' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
         'description' => 'Décaféiné par méthode naturelle à l\'eau. Toute la saveur, sans les effets stimulants.',
         'origin' => 'Colombie', 'temp_min' => 90, 'temp_max' => 96,
         'preparation' => 'Préparer comme un café normal. Filtration ou espresso selon préférence.'],

        // ─── Infusions ───
        ['name' => 'Camomille', 'category' => 'infusion', 'calories' => 1, 'hydration' => 95, 'sugar_free' => true, 'caffeine_free' => true,
         'benefits' => ['Relaxante', 'Aide au sommeil', 'Anti-inflammatoire', 'Apaise les maux d\'estomac'],
         'moments' => ['soirée', 'après-midi'], 'pairing' => ['desserts légers', 'miel'],
         'regimes' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
         'description' => 'Infusion douce et apaisante de fleurs de camomille. Parfaite avant le coucher.',
         'origin' => 'Méditerranée', 'temp_min' => 95, 'temp_max' => 100,
         'preparation' => 'Infuser 1-2 cuillères de fleurs séchées dans 250ml d\'eau bouillante pendant 5-10 minutes.'],
        ['name' => 'Menthe poivrée', 'category' => 'infusion', 'calories' => 0, 'hydration' => 94, 'sugar_free' => true, 'caffeine_free' => true,
         'benefits' => ['Digestive', 'Rafraîchissante', 'Anti-nausées', 'Réduit les ballonnements'],
         'moments' => ['déjeuner', 'dîner', 'après-midi'], 'pairing' => ['agneau', 'plats méditerranéens', 'taboulé'],
         'regimes' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
         'description' => 'Infusion rafraîchissante, idéale après les repas. Effet digestif reconnu.',
         'origin' => 'Maroc/Méditerranée', 'temp_min' => 95, 'temp_max' => 100,
         'preparation' => 'Infuser des feuilles fraîches ou séchées dans l\'eau bouillante 5-7 minutes.'],
        ['name' => 'Verveine citronnée', 'category' => 'infusion', 'calories' => 0, 'hydration' => 93, 'sugar_free' => true, 'caffeine_free' => true,
         'benefits' => ['Relaxante', 'Digestive', 'Anti-stress', 'Améliore le sommeil'],
         'moments' => ['soirée', 'après-midi', 'dîner'], 'pairing' => ['poisson', 'salade', 'fruits'],
         'regimes' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
         'description' => 'Infusion citronnée et florale, douce et relaxante. Un classique des après-dîners.',
         'origin' => 'France/Amérique du Sud', 'temp_min' => 90, 'temp_max' => 100,
         'preparation' => 'Infuser 2-3 feuilles fraîches ou 1 cuillère de feuilles séchées dans 250ml d\'eau frémissante pendant 5 minutes.'],
        ['name' => 'Gingembre-Citron', 'category' => 'infusion', 'calories' => 5, 'hydration' => 90, 'sugar_free' => true, 'caffeine_free' => true,
         'benefits' => ['Stimule l\'immunité', 'Anti-inflammatoire', 'Aide à la digestion', 'Réchauffe le corps'],
         'moments' => ['matin', 'après-midi'], 'pairing' => ['poulet', 'soupe', 'plats asiatiques'],
         'regimes' => ['diabétique', 'normal', 'sans_gluten', 'cardioprotecteur'],
         'description' => 'Infusion tonique au gingembre frais et citron. Booste l\'immunité et la vitalité.',
         'origin' => 'Asie', 'temp_min' => 95, 'temp_max' => 100,
         'preparation' => 'Râper 2cm de gingembre frais, ajouter le jus d\'un demi-citron dans 300ml d\'eau bouillante. Infuser 10 minutes.'],
        ['name' => 'Hibiscus (Bissap)', 'category' => 'infusion', 'calories' => 3, 'hydration' => 92, 'sugar_free' => true, 'caffeine_free' => true,
         'benefits' => ['Riche en vitamine C', 'Aide à contrôler la tension', 'Antioxydant puissant'],
         'moments' => ['tout_moment'], 'pairing' => ['grillades', 'plats épicés'],
         'regimes' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
         'description' => 'Infusion rouge rubis aux fleurs d\'hibiscus. Tart et fruitée, délicieuse chaude ou froide.',
         'origin' => 'Afrique/Caraïbes', 'temp_min' => 95, 'temp_max' => 100,
         'preparation' => 'Infuser 2 cuillères de fleurs séchées dans 500ml d\'eau bouillante pendant 10-15 minutes. Filtrer.'],

        // ─── Eaux ───
        ['name' => 'Eau minérale naturelle', 'category' => 'eau', 'calories' => 0, 'hydration' => 100, 'sugar_free' => true, 'caffeine_free' => true,
         'benefits' => ['Hydratation optimale', 'Minéraux essentiels', 'Zéro calorie'],
         'moments' => ['tout_moment'], 'pairing' => ['tous les repas'],
         'regimes' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
         'description' => 'Source naturelle de minéraux. L\'hydratation essentielle pour votre santé.',
         'origin' => 'Naturelle', 'temp_min' => 8, 'temp_max' => 15,
         'preparation' => 'Servir fraîche (8-15°C). Préférer les eaux faiblement minéralisées pour une consommation quotidienne.'],
        ['name' => 'Eau pétillante citronnée', 'category' => 'eau', 'calories' => 2, 'hydration' => 95, 'sugar_free' => true, 'caffeine_free' => true,
         'benefits' => ['Aide à la digestion', 'Rafraîchissante', 'Alternative aux sodas'],
         'moments' => ['déjeuner', 'dîner', 'après-midi'], 'pairing' => ['grillades', 'poisson', 'salade'],
         'regimes' => ['diabétique', 'normal', 'sans_gluten'],
         'description' => 'Eau gazeuse naturelle avec une touche de citron. Rafraîchissante et digestive.',
         'origin' => 'Europe', 'temp_min' => 6, 'temp_max' => 10,
         'preparation' => 'Presser un quartier de citron frais dans un grand verre d\'eau pétillante bien froide.'],
        ['name' => 'Eau de coco', 'category' => 'eau', 'calories' => 19, 'hydration' => 92, 'sugar_free' => false, 'caffeine_free' => true,
         'benefits' => ['Riche en potassium', 'Électrolytes naturels', 'Réhydratation après effort'],
         'moments' => ['matin', 'après-midi'], 'pairing' => ['fruits tropicaux', 'curry'],
         'regimes' => ['normal', 'sans_gluten', 'cardioprotecteur'],
         'description' => 'Eau pure de jeune noix de coco. Électrolytes naturels pour une réhydratation efficace.',
         'origin' => 'Tropiques', 'temp_min' => 4, 'temp_max' => 10,
         'preparation' => 'Servir très fraîche. Peut être mélangée à de l\'eau gazeuse pour un effet pétillant.'],

        // ─── Jus & Smoothies ───
        ['name' => 'Jus de betterave-pomme', 'category' => 'jus', 'calories' => 45, 'hydration' => 80, 'sugar_free' => false, 'caffeine_free' => true,
         'benefits' => ['Riche en fer', 'Booste l\'endurance', 'Bon pour la circulation'],
         'moments' => ['matin'], 'pairing' => ['petit-déjeuner', 'collation'],
         'regimes' => ['normal', 'sans_gluten', 'cardioprotecteur'],
         'description' => 'Jus pressé frais alliant la douceur de la pomme et les bienfaits de la betterave.',
         'origin' => 'Europe', 'temp_min' => 4, 'temp_max' => 8,
         'preparation' => 'Presser 1 betterave et 2 pommes à l\'extracteur. Servir immédiatement.'],
        ['name' => 'Smoothie vert épinard-banane', 'category' => 'smoothie', 'calories' => 65, 'hydration' => 78, 'sugar_free' => false, 'caffeine_free' => true,
         'benefits' => ['Riche en fibres', 'Vitamines & minéraux', 'Énergie durable'],
         'moments' => ['matin', 'après-midi'], 'pairing' => ['petit-déjeuner', 'goûter'],
         'regimes' => ['normal', 'sans_gluten', 'cardioprotecteur'],
         'description' => 'Smoothie vert onctueux et nutritif. La douceur de la banane masque l\'épinard.',
         'origin' => 'International', 'temp_min' => 4, 'temp_max' => 8,
         'preparation' => 'Mixer 1 banane, 1 poignée d\'épinards frais, 200ml de lait d\'amande et de la glace.'],

        // ─── Sirops & Mocktails ───
        ['name' => 'Sirop de menthe sans sucre', 'category' => 'sirop_sans_sucre', 'calories' => 3, 'hydration' => 88, 'sugar_free' => true, 'caffeine_free' => true,
         'benefits' => ['Alternative saine aux sirops sucrés', 'Rafraîchissant', 'Favorise l\'hydratation'],
         'moments' => ['tout_moment'], 'pairing' => ['tous les repas'],
         'regimes' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
         'description' => 'Sirop de menthe édulcoré sans sucre ajouté. Pour agrémenter l\'eau et les boissons.',
         'origin' => 'France', 'temp_min' => 4, 'temp_max' => 10,
         'preparation' => 'Verser 2cl de sirop dans un grand verre d\'eau fraîche. Ajouter des glaçons.'],
        ['name' => 'Virgin Mojito', 'category' => 'mocktail', 'calories' => 25, 'hydration' => 82, 'sugar_free' => false, 'caffeine_free' => true,
         'benefits' => ['Rafraîchissant', 'Vitamine C du citron vert', 'Alternative sans alcool festive'],
         'moments' => ['déjeuner', 'dîner', 'soirée'], 'pairing' => ['tapas', 'grillades', 'poisson'],
         'regimes' => ['normal', 'sans_gluten'],
         'description' => 'Mocktail rafraîchissant au citron vert, menthe et eau pétillante. Festif sans alcool.',
         'origin' => 'Cuba (adapté)', 'temp_min' => 4, 'temp_max' => 8,
         'preparation' => 'Piler 6 feuilles de menthe avec 1 cuillère de sucre et le jus d\'un citron vert. Compléter avec de l\'eau gazeuse et des glaçons.'],
        ['name' => 'Spritz sans alcool à l\'orange', 'category' => 'mocktail', 'calories' => 20, 'hydration' => 85, 'sugar_free' => false, 'caffeine_free' => true,
         'benefits' => ['Festif', 'Vitamine C', 'Hydratant', 'Sans alcool'],
         'moments' => ['déjeuner', 'dîner', 'soirée'], 'pairing' => ['antipasti', 'tapas', 'apéritif léger'],
         'regimes' => ['normal', 'sans_gluten'],
         'description' => 'Version sans alcool du célèbre Spritz. Orange amère, pétillant et élégant.',
         'origin' => 'Italie (adapté)', 'temp_min' => 4, 'temp_max' => 8,
         'preparation' => 'Mélanger 5cl de jus d\'orange amère, 10cl d\'eau pétillante et 1 tranche d\'orange.'],
    ];

    private GeminiService $geminiService;
    private BeverageRepository $beverageRepository;
    private BeverageLogRepository $beverageLogRepository;
    private EntityManagerInterface $em;

    public function __construct(
        GeminiService $geminiService,
        BeverageRepository $beverageRepository,
        BeverageLogRepository $beverageLogRepository,
        EntityManagerInterface $em
    ) {
        $this->geminiService = $geminiService;
        $this->beverageRepository = $beverageRepository;
        $this->beverageLogRepository = $beverageLogRepository;
        $this->em = $em;
    }

    /**
     * Initialise le catalogue de boissons en base si vide
     */
    public function seedCatalogIfEmpty(): void
    {
        $existing = $this->beverageRepository->findAllActive();
        if (count($existing) > 0) {
            return;
        }

        foreach (self::BEVERAGE_CATALOG as $data) {
            $beverage = new Beverage();
            $beverage->setName($data['name']);
            $beverage->setCategory($data['category']);
            $beverage->setDescription($data['description'] ?? null);
            $beverage->setCaloriesPer100ml($data['calories'] ?? null);
            $beverage->setHydrationScore($data['hydration'] ?? null);
            $beverage->setIsSugarFree($data['sugar_free'] ?? false);
            $beverage->setIsCaffeineFree($data['caffeine_free'] ?? false);
            $beverage->setHealthBenefits($data['benefits'] ?? []);
            $beverage->setIdealMoments($data['moments'] ?? []);
            $beverage->setPairingMeals($data['pairing'] ?? []);
            $beverage->setCompatibleRegimes($data['regimes'] ?? []);
            $beverage->setOrigin($data['origin'] ?? null);
            $beverage->setTemperatureMin($data['temp_min'] ?? null);
            $beverage->setTemperatureMax($data['temp_max'] ?? null);
            $beverage->setPreparationInstructions($data['preparation'] ?? null);
            $this->em->persist($beverage);
        }

        $this->em->flush();
    }

    /**
     * Suggestions de boissons selon un repas et le régime
     */
    public function suggestForMeal(string $mealType, ?RegimePrescrit $regime): array
    {
        $moment = $this->mealToMoment($mealType);
        $beverages = $this->beverageRepository->findAllActive();

        if (empty($beverages)) {
            return $this->suggestFromCatalog($moment, $regime);
        }

        $scored = [];
        foreach ($beverages as $bev) {
            $score = $this->scoreBeverage($bev, $moment, $regime);
            if ($score > 0) {
                $scored[] = ['beverage' => $bev, 'score' => $score];
            }
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, 6);
    }

    /**
     * Suggestions depuis le catalogue intégré (fallback)
     */
    private function suggestFromCatalog(string $moment, ?RegimePrescrit $regime): array
    {
        $regimeType = $regime ? $regime->getTypeRegime() : 'normal';
        $results = [];

        foreach (self::BEVERAGE_CATALOG as $data) {
            $score = 0;
            // Moment matching
            if (in_array($moment, $data['moments'] ?? []) || in_array('tout_moment', $data['moments'] ?? [])) {
                $score += 30;
            }
            // Regime matching
            if (in_array($regimeType, $data['regimes'] ?? [])) {
                $score += 25;
            }
            // Hydration
            $score += ($data['hydration'] ?? 0) / 5;
            // Sugar-free bonus for diabetic
            if ($regimeType === 'diabétique' && ($data['sugar_free'] ?? false)) {
                $score += 15;
            }
            // Cardio: prefer caffeine-free
            if ($regimeType === 'cardioprotecteur' && ($data['caffeine_free'] ?? false)) {
                $score += 10;
            }

            if ($score > 20) {
                $results[] = [
                    'name' => $data['name'],
                    'category' => $data['category'],
                    'description' => $data['description'] ?? '',
                    'calories' => $data['calories'] ?? 0,
                    'hydration_score' => $data['hydration'] ?? 0,
                    'benefits' => $data['benefits'] ?? [],
                    'preparation' => $data['preparation'] ?? '',
                    'origin' => $data['origin'] ?? '',
                    'score' => $score,
                    'sugar_free' => $data['sugar_free'] ?? false,
                    'caffeine_free' => $data['caffeine_free'] ?? false,
                    'temp_min' => $data['temp_min'] ?? null,
                    'temp_max' => $data['temp_max'] ?? null,
                    'pairing' => $data['pairing'] ?? [],
                ];
            }
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($results, 0, 6);
    }

    /**
     * Score une boisson entity par rapport au contexte
     */
    private function scoreBeverage(Beverage $bev, string $moment, ?RegimePrescrit $regime): int
    {
        $score = 0;
        $regimeType = $regime ? $regime->getTypeRegime() : 'normal';

        // Moment matching
        if (in_array($moment, $bev->getIdealMoments()) || in_array('tout_moment', $bev->getIdealMoments())) {
            $score += 30;
        }

        // Regime compatibility
        if (in_array($regimeType, $bev->getCompatibleRegimes())) {
            $score += 25;
        }

        // Hydration score
        $score += ($bev->getHydrationScore() ?? 0) / 5;

        // Diet-specific bonuses
        if ($regimeType === 'diabétique' && $bev->isSugarFree()) {
            $score += 15;
        }
        if ($regimeType === 'cardioprotecteur' && $bev->isCaffeineFree()) {
            $score += 10;
        }
        if ($regimeType === 'hypo_sodé') {
            $score += 5; // All drinks are generally low sodium
        }

        return $score;
    }

    private function mealToMoment(string $mealType): string
    {
        return match (strtolower($mealType)) {
            'petit-déjeuner', 'petit_dejeuner', 'breakfast' => 'matin',
            'déjeuner', 'lunch' => 'déjeuner',
            'goûter', 'collation', 'snack', 'collation matin' => 'après-midi',
            'dîner', 'dinner', 'collation soir' => 'dîner',
            default => 'tout_moment',
        };
    }

    /**
     * Conseils d'hydratation personnalisés via IA
     */
    public function getPersonalizedHydrationAdvice(User $user, ?RegimePrescrit $regime): array
    {
        $todayMl = $this->beverageLogRepository->getTodayHydration($user);
        $todayCount = $this->beverageLogRepository->getTodayCount($user);
        $targetMl = $regime ? $regime->getHydratationQuotidienne() : 2000;
        $remaining = max(0, $targetMl - $todayMl);
        $progress = $targetMl > 0 ? min(100, round($todayMl / $targetMl * 100)) : 0;

        $hour = (int) date('G');
        $hoursLeft = max(1, 22 - $hour); // Assume sleeping at 22h
        $mlPerHour = $remaining > 0 ? round($remaining / $hoursLeft) : 0;

        // Smart reminders
        $reminders = [];
        if ($todayMl === 0 && $hour >= 9) {
            $reminders[] = ['type' => 'warning', 'icon' => 'tint-slash', 'message' => 'Vous n\'avez encore rien bu aujourd\'hui ! Commencez par un grand verre d\'eau.'];
        }
        if ($progress < 30 && $hour >= 14) {
            $reminders[] = ['type' => 'warning', 'icon' => 'exclamation-triangle', 'message' => "Seulement {$progress}% de votre objectif d'hydratation atteint. Buvez environ {$mlPerHour}ml par heure."];
        }
        if ($progress >= 100) {
            $reminders[] = ['type' => 'success', 'icon' => 'check-circle', 'message' => 'Félicitations ! Vous avez atteint votre objectif d\'hydratation du jour ! 🎉'];
        }
        if ($hour >= 20 && $remaining > 500) {
            $reminders[] = ['type' => 'danger', 'icon' => 'times-circle', 'message' => "Il reste {$remaining}ml à boire et la soirée avance. Privilégiez l'eau et les infusions."];
        }

        // Schedule suggestions
        $schedule = [];
        $intervals = [
            ['hour' => 7, 'label' => 'Au réveil', 'drink' => 'Grand verre d\'eau tiède citronnée', 'ml' => 300],
            ['hour' => 9, 'label' => 'Milieu de matinée', 'drink' => 'Thé vert ou café', 'ml' => 250],
            ['hour' => 12, 'label' => 'Déjeuner', 'drink' => 'Eau pendant le repas', 'ml' => 300],
            ['hour' => 15, 'label' => 'Après-midi', 'drink' => 'Infusion ou eau de fruits', 'ml' => 250],
            ['hour' => 17, 'label' => 'Goûter', 'drink' => 'Smoothie ou jus frais', 'ml' => 200],
            ['hour' => 19, 'label' => 'Dîner', 'drink' => 'Eau ou infusion digestive', 'ml' => 300],
            ['hour' => 21, 'label' => 'Soirée', 'drink' => 'Camomille ou verveine', 'ml' => 200],
        ];

        foreach ($intervals as $interval) {
            $status = $hour >= $interval['hour'] + 2 ? 'past' : ($hour >= $interval['hour'] ? 'now' : 'upcoming');
            $schedule[] = array_merge($interval, ['status' => $status]);
        }

        return [
            'today_ml' => $todayMl,
            'target_ml' => $targetMl,
            'remaining_ml' => $remaining,
            'progress' => $progress,
            'drinks_count' => $todayCount,
            'ml_per_hour' => $mlPerHour,
            'reminders' => $reminders,
            'schedule' => $schedule,
        ];
    }

    /**
     * Dégustation virtuelle - recommandation IA
     */
    public function getVirtualTasting(string $beverageType, ?RegimePrescrit $regime): array
    {
        $regimeInfo = $regime ? "régime {$regime->getTypeRegime()}, {$regime->getCaloriesJournalieres()} kcal/jour" : 'aucun régime spécifique';

        $prompt = "Tu es un sommelier expert en boissons non-alcoolisées et un spécialiste de l'hydratation santé.
Propose une dégustation virtuelle de {$beverageType} adaptée à un senior avec {$regimeInfo}.

Réponds UNIQUEMENT en JSON valide avec cette structure :
{
  \"titre\": \"Titre de la dégustation\",
  \"introduction\": \"Brève introduction à la dégustation (2-3 phrases)\",
  \"etapes\": [
    {
      \"numero\": 1,
      \"nom\": \"Nom de l'étape\",
      \"description\": \"Description détaillée\",
      \"conseil\": \"Conseil du sommelier\",
      \"duree_minutes\": 5
    }
  ],
  \"boissons_recommandees\": [
    {
      \"nom\": \"Nom de la boisson\",
      \"origine\": \"Origine\",
      \"notes_degustation\": \"Notes de dégustation\",
      \"temperature_ideale\": \"70-80°C\",
      \"bienfaits\": [\"bienfait1\", \"bienfait2\"]
    }
  ],
  \"accords_mets\": [\"suggestion1\", \"suggestion2\"],
  \"conseil_final\": \"Conseil final du sommelier\"
}";

        try {
            $response = $this->geminiService->generateText($prompt);
            $jsonText = str_replace(['```json', '```'], '', $response);
            $data = json_decode($jsonText, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return ['status' => 'success', 'tasting' => $data];
            }
        } catch (\Exception $e) {
            // Fallback
        }

        // Fallback: built-in tasting data
        return $this->getBuiltInTasting($beverageType);
    }

    /**
     * Fallback dégustation
     */
    private function getBuiltInTasting(string $type): array
    {
        $tastings = [
            'thé' => [
                'titre' => '🍵 Voyage au cœur des thés du monde',
                'introduction' => 'Découvrez les subtilités du thé, des montagnes du Japon aux jardins de Chine. Chaque tasse raconte une histoire millénaire.',
                'etapes' => [
                    ['numero' => 1, 'nom' => 'L\'observation', 'description' => 'Observez les feuilles sèches : leur couleur, forme et parfum vous renseignent sur la qualité.', 'conseil' => 'Sentez les feuilles sèches en approchant doucement la tasse de votre nez.', 'duree_minutes' => 3],
                    ['numero' => 2, 'nom' => 'L\'infusion', 'description' => 'Versez l\'eau à la bonne température et observez les feuilles se déployer.', 'conseil' => 'Ne jamais utiliser d\'eau bouillante pour le thé vert, 70-80°C est idéal.', 'duree_minutes' => 5],
                    ['numero' => 3, 'nom' => 'La dégustation', 'description' => 'Aspirez doucement, laissez le liquide envelopper votre palais. Identifiez les notes.', 'conseil' => 'Les notes végétales indiquent la fraîcheur, les notes douces la qualité de la récolte.', 'duree_minutes' => 5],
                ],
                'boissons_recommandees' => [
                    ['nom' => 'Matcha Premium', 'origine' => 'Uji, Japon', 'notes_degustation' => 'Umami, herbacé, légère douceur', 'temperature_ideale' => '70-80°C', 'bienfaits' => ['Antioxydants', 'Énergie', 'Concentration']],
                    ['nom' => 'Sencha Fukamushi', 'origine' => 'Shizuoka, Japon', 'notes_degustation' => 'Doux, marine, verdoyant', 'temperature_ideale' => '70°C', 'bienfaits' => ['Digestif', 'Antioxydant']],
                    ['nom' => 'Long Jing (Puits du Dragon)', 'origine' => 'Hangzhou, Chine', 'notes_degustation' => 'Noisette, châtaigne, végétal', 'temperature_ideale' => '80°C', 'bienfaits' => ['Calmant', 'Riche en vitamines']],
                ],
                'accords_mets' => ['Sushi et sashimi', 'Riz nature', 'Fruits frais', 'Pâtisseries légères'],
                'conseil_final' => 'Le thé se savoure dans le calme. Prenez 10 minutes, sans distraction, pour profiter pleinement de chaque tasse.',
            ],
            'café' => [
                'titre' => '☕ L\'art du café de spécialité',
                'introduction' => 'Du grain à la tasse, chaque étape influence le goût. Explorons les profils aromatiques des meilleurs cafés du monde.',
                'etapes' => [
                    ['numero' => 1, 'nom' => 'Le parfum du grain', 'description' => 'Respirez l\'arôme des grains fraîchement moulus.', 'conseil' => 'Les notes fruitées et chocolatées sont signes d\'un bon Arabica.', 'duree_minutes' => 2],
                    ['numero' => 2, 'nom' => 'L\'extraction', 'description' => 'Observez l\'écoulement lent et la crema qui se forme.', 'conseil' => 'Une crema dorée et persistante indique une bonne extraction.', 'duree_minutes' => 3],
                    ['numero' => 3, 'nom' => 'En bouche', 'description' => 'Prenez une petite gorgée, laissez le café recouvrir votre langue.', 'conseil' => 'Cherchez l\'équilibre entre acidité, amertume et sucré.', 'duree_minutes' => 5],
                ],
                'boissons_recommandees' => [
                    ['nom' => 'Éthiopie Yirgacheffe', 'origine' => 'Éthiopie', 'notes_degustation' => 'Floral, agrumes, jasmin', 'temperature_ideale' => '92-96°C', 'bienfaits' => ['Antioxydants', 'Concentration']],
                    ['nom' => 'Colombie Supremo', 'origine' => 'Colombie', 'notes_degustation' => 'Caramel, noix, fruits rouges', 'temperature_ideale' => '92-96°C', 'bienfaits' => ['Énergie', 'Endurance']],
                ],
                'accords_mets' => ['Chocolat noir 70%', 'Fromages affinés', 'Croissant au beurre', 'Amandes grillées'],
                'conseil_final' => 'Limitez-vous à 2-3 tasses par jour et évitez la caféine après 15h pour préserver votre sommeil.',
            ],
            'infusion' => [
                'titre' => '🌿 Les bienfaits des plantes en infusion',
                'introduction' => 'Les infusions offrent un monde de saveurs et de vertus thérapeutiques, sans caféine ni effets indésirables.',
                'etapes' => [
                    ['numero' => 1, 'nom' => 'Le choix des plantes', 'description' => 'Sélectionnez vos plantes en fonction de vos besoins : digestion, sommeil, immunité...', 'conseil' => 'Privilégiez les plantes biologiques pour plus de bienfaits.', 'duree_minutes' => 3],
                    ['numero' => 2, 'nom' => 'L\'infusion longue', 'description' => 'Les infusions nécessitent un temps plus long que le thé pour libérer leurs principes actifs.', 'conseil' => '7-10 minutes minimum pour une infusion efficace.', 'duree_minutes' => 10],
                    ['numero' => 3, 'nom' => 'Apprécier les notes', 'description' => 'Sentez, observez la couleur, puis dégustez en pleine conscience.', 'conseil' => 'Une cuillère de miel peut sublimer certaines infusions.', 'duree_minutes' => 5],
                ],
                'boissons_recommandees' => [
                    ['nom' => 'Camomille de Provence', 'origine' => 'France', 'notes_degustation' => 'Douce, miellée, légèrement fruitée', 'temperature_ideale' => '95-100°C', 'bienfaits' => ['Relaxation', 'Sommeil', 'Digestion']],
                    ['nom' => 'Verveine du Puy-en-Velay', 'origine' => 'France', 'notes_degustation' => 'Citronnée, fraîche, douce', 'temperature_ideale' => '90-100°C', 'bienfaits' => ['Anti-stress', 'Digestion']],
                    ['nom' => 'Hibiscus du Sénégal', 'origine' => 'Sénégal', 'notes_degustation' => 'Fruitée, acidulée, rubis', 'temperature_ideale' => '95-100°C', 'bienfaits' => ['Vitamine C', 'Tension artérielle']],
                ],
                'accords_mets' => ['Fruits frais de saison', 'Miel d\'acacia', 'Biscuits aux amandes', 'Salade de fruits'],
                'conseil_final' => 'Les infusions sont parfaites toute la journée. Variez les plaisirs selon les moments et vos besoins du moment.',
            ],
        ];

        $tasting = $tastings[$type] ?? $tastings['infusion'];
        return ['status' => 'success', 'tasting' => $tasting];
    }

    /**
     * Recommandations de box de boissons
     */
    public function getBoxSubscriptionSuggestions(?RegimePrescrit $regime): array
    {
        $regimeType = $regime ? $regime->getTypeRegime() : 'normal';

        $boxes = [
            [
                'name' => '🍵 Box Découverte Thés du Monde',
                'description' => '5 thés d\'exception de Chine, Japon, Inde et Sri Lanka. Feuilles entières premium.',
                'price' => '29.90 DH/mois',
                'includes' => ['5 sachets de thé premium', 'Guide de dégustation', 'Fiche terroir de chaque thé', 'Accessoire surprise'],
                'best_for' => ['normal', 'diabétique', 'sans_gluten', 'cardioprotecteur', 'hypo_sodé'],
                'image_emoji' => '🍵',
            ],
            [
                'name' => '☕ Box Café de Spécialité',
                'description' => '3 cafés mono-origine torréfiés artisanalement. Grains entiers ou moulus selon votre mouture.',
                'price' => '34.90 DH/mois',
                'includes' => ['3 paquets de 100g', 'Notes de dégustation', 'Conseil de préparation', 'Recette de dessert au café'],
                'best_for' => ['normal', 'sans_gluten'],
                'image_emoji' => '☕',
            ],
            [
                'name' => '🌿 Box Infusions Bien-être',
                'description' => 'Sélection de 6 infusions bio aux vertus thérapeutiques, adaptées à chaque moment de la journée.',
                'price' => '24.90 DH/mois',
                'includes' => ['6 infusions bio', 'Guide des bienfaits', 'Calendrier infusion', 'Filtre à thé offert'],
                'best_for' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
                'image_emoji' => '🌿',
            ],
            [
                'name' => '💧 Box Hydratation Premium',
                'description' => 'Eaux minérales rares, sirops sans sucre et eaux aromatisées naturelles. Pour rester hydraté avec plaisir.',
                'price' => '19.90 DH/mois',
                'includes' => ['3 eaux minérales premium', '2 sirops sans sucre', 'Gourde isotherme (1er mois)', 'Guide hydratation'],
                'best_for' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
                'image_emoji' => '💧',
            ],
            [
                'name' => '🍹 Box Mocktails Festifs',
                'description' => 'Tout pour créer 4 mocktails originaux et festifs chez vous. Sans alcool, avec goût !',
                'price' => '39.90 DH/mois',
                'includes' => ['4 kits mocktails complets', 'Recettes illustrées', 'Décorations cocktails', 'Shaker (1er mois)'],
                'best_for' => ['normal', 'sans_gluten'],
                'image_emoji' => '🍹',
            ],
        ];

        // Filtrer par régime
        $filtered = array_filter($boxes, fn($box) => in_array($regimeType, $box['best_for']));
        return array_values($filtered);
    }

    /**
     * Partenaires proches
     */
    public function getPartners(): array
    {
        return [
            ['name' => 'Palais des Thés', 'type' => 'Salon de thé & boutique', 'specialty' => 'Thés du monde entier', 'emoji' => '🍵', 'offer' => '-15% avec code WANNASNI'],
            ['name' => 'Café Maure Traditionnel', 'type' => 'Torréfacteur artisanal', 'specialty' => 'Cafés arabica torréfiés sur place', 'emoji' => '☕', 'offer' => 'Dégustation offerte'],
            ['name' => 'Sidi Ali / Oulmes', 'type' => 'Eaux minérales', 'specialty' => 'Eaux minérales naturelles du Maroc', 'emoji' => '💧', 'offer' => 'Pack découverte -20%'],
            ['name' => 'Bio Maroc', 'type' => 'Herboristerie bio', 'specialty' => 'Plantes médicinales & infusions bio', 'emoji' => '🌿', 'offer' => 'Livraison gratuite dès 100 DH'],
            ['name' => 'Moulin à Sirops', 'type' => 'Producteur artisanal', 'specialty' => 'Sirops sans sucre ajouté', 'emoji' => '🍯', 'offer' => '3 achetés = 1 offert'],
        ];
    }

    /**
     * Catalogue complet regroupé par catégorie
     */
    public function getCatalogByCategory(): array
    {
        $beverages = $this->beverageRepository->findAllActive();

        if (empty($beverages)) {
            // Use built-in catalog
            $grouped = [];
            foreach (self::BEVERAGE_CATALOG as $data) {
                $cat = $data['category'];
                if (!isset($grouped[$cat])) {
                    $grouped[$cat] = [];
                }
                $grouped[$cat][] = $data;
            }
            return $grouped;
        }

        $grouped = [];
        foreach ($beverages as $bev) {
            $cat = $bev->getCategory();
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $bev;
        }

        return $grouped;
    }
}
