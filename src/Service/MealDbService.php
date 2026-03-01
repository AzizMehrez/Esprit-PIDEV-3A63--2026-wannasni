<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class MealDbService
{
    private $client;
    private $cache;
    private const BASE_URL = 'https://www.themealdb.com/api/json/v1/1/';

    public function __construct(HttpClientInterface $client, CacheInterface $cache)
    {
        $this->client = $client;
        $this->cache = $cache;
    }

    public function searchMeals(string $query): array
    {
        return $this->cache->get('mealdb_search_' . md5($query), function (ItemInterface $item) use ($query) {
            $item->expiresAfter(3600); // Cache for 1 hour

            $response = $this->client->request('GET', self::BASE_URL . 'search.php', [
                'query' => ['s' => $query]
            ]);

            $data = $response->toArray();
            return $data['meals'] ?? [];
        });
    }

    public function getMealDetails(string $id): ?array
    {
        return $this->cache->get('mealdb_meal_' . $id, function (ItemInterface $item) use ($id) {
            $item->expiresAfter(86400); // Cache for 24 hours (recipes don't change often)

            $response = $this->client->request('GET', self::BASE_URL . 'lookup.php', [
                'query' => ['i' => $id]
            ]);

            $data = $response->toArray();
            return $data['meals'][0] ?? null;
        });
    }

    public function getRandomMeal(): ?array
    {
        // Don't cache random meal to ensure freshness
        $response = $this->client->request('GET', self::BASE_URL . 'random.php');
        $data = $response->toArray();
        
        return $data['meals'][0] ?? null;
    }

    public function getCategories(): array
    {
        return $this->cache->get('mealdb_categories', function (ItemInterface $item) {
            $item->expiresAfter(604800); // Cache for 1 week

            $response = $this->client->request('GET', self::BASE_URL . 'categories.php');
            $data = $response->toArray();
            
            return $data['categories'] ?? [];
        });
    }

    public function filterByCategory(string $category): array
    {
        return $this->cache->get('mealdb_filter_cat_' . $category, function (ItemInterface $item) use ($category) {
            $item->expiresAfter(3600);
            
            $response = $this->client->request('GET', self::BASE_URL . 'filter.php', [
                'query' => ['c' => $category]
            ]);

            $data = $response->toArray();
            return $data['meals'] ?? [];
        });
    }

    /**
     * Filter meals by main ingredient (TheMealDB filter API)
     */
    public function filterByIngredient(string $ingredient): array
    {
        $key = 'mealdb_ingredient_' . md5($ingredient);
        return $this->cache->get($key, function (ItemInterface $item) use ($ingredient) {
            $item->expiresAfter(3600);
            $response = $this->client->request('GET', self::BASE_URL . 'filter.php', [
                'query' => ['i' => $ingredient]
            ]);
            $data = $response->toArray();
            return $data['meals'] ?? [];
        });
    }

    /**
     * Get multiple random meals
     */
    public function getRandomMeals(int $count = 5): array
    {
        $meals = [];
        for ($i = 0; $i < $count; $i++) {
            $meal = $this->getRandomMeal();
            if ($meal) {
                $meals[] = $meal;
            }
        }
        return $meals;
    }

    /**
     * Smart recipe suggestions based on regime constraints:
     *  - Uses recommended foods as search ingredients
     *  - Filters out recipes containing forbidden foods
     *  - Falls back to category-based or random if no results
     */
    public function getRegimeSuggestions(
        array $alimentsRecommandes,
        array $alimentsInterdits,
        int $maxResults = 6
    ): array {
        $allMeals = [];

        // Map common French food names to English for MealDB API
        $frToEn = [
            'poulet' => 'chicken', 'poisson' => 'fish', 'légumes' => 'vegetable',
            'riz' => 'rice', 'pâtes' => 'pasta', 'boeuf' => 'beef', 'agneau' => 'lamb',
            'tomate' => 'tomato', 'pomme de terre' => 'potato', 'carotte' => 'carrot',
            'oignon' => 'onion', 'ail' => 'garlic', 'épinard' => 'spinach',
            'saumon' => 'salmon', 'thon' => 'tuna', 'crevette' => 'shrimp',
            'lentilles' => 'lentil', 'haricots' => 'beans', 'avocat' => 'avocado',
            'citron' => 'lemon', 'orange' => 'orange', 'banane' => 'banana',
            'oeuf' => 'egg', 'lait' => 'milk', 'fromage' => 'cheese',
            'brocoli' => 'broccoli', 'courgette' => 'courgette', 'aubergine' => 'aubergine',
            'champignon' => 'mushroom', 'poivron' => 'pepper', 'concombre' => 'cucumber',
            'dinde' => 'turkey', 'veau' => 'veal', 'porc' => 'pork',
        ];

        // Search by recommended ingredients
        foreach ($alimentsRecommandes as $aliment) {
            $searchTerm = $frToEn[mb_strtolower(trim($aliment))] ?? mb_strtolower(trim($aliment));
            try {
                $results = $this->filterByIngredient($searchTerm);
                foreach ($results as $meal) {
                    $allMeals[$meal['idMeal']] = $meal;
                }
            } catch (\Exception $e) {
                // Silently skip if ingredient not found
            }
            if (count($allMeals) >= $maxResults * 3) {
                break; // Enough candidates
            }
        }

        // If not enough results, try healthy categories
        if (count($allMeals) < $maxResults) {
            $healthyCategories = ['Chicken', 'Seafood', 'Vegetarian', 'Lamb', 'Beef'];
            foreach ($healthyCategories as $cat) {
                try {
                    $results = $this->filterByCategory($cat);
                    foreach ($results as $meal) {
                        $allMeals[$meal['idMeal']] = $meal;
                    }
                } catch (\Exception $e) {}
                if (count($allMeals) >= $maxResults * 3) break;
            }
        }

        // Filter out meals whose names contain forbidden ingredients
        $forbiddenTerms = [];
        foreach ($alimentsInterdits as $interdit) {
            $forbiddenTerms[] = mb_strtolower(trim($interdit));
            if (isset($frToEn[mb_strtolower(trim($interdit))])) {
                $forbiddenTerms[] = $frToEn[mb_strtolower(trim($interdit))];
            }
        }

        $filtered = [];
        foreach ($allMeals as $meal) {
            $mealName = mb_strtolower($meal['strMeal'] ?? '');
            $dominated = false;
            foreach ($forbiddenTerms as $term) {
                if ($term && str_contains($mealName, $term)) {
                    $dominated = true;
                    break;
                }
            }
            if (!$dominated) {
                $filtered[] = $meal;
            }
        }

        // Shuffle and limit
        shuffle($filtered);
        return array_slice($filtered, 0, $maxResults);
    }
}
