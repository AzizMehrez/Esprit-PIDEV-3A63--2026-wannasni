<?php

namespace App\Controller\Api;

use App\Repository\ActivityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/locations')]
class LocationController extends AbstractController
{
    public function __construct(
        private ActivityRepository $activityRepository,
    ) {
    }

    /**
     * Get all available locations by activity type
     */
    #[Route('/by-type/{type}', name: 'api_locations_by_type', methods: ['GET'])]
    public function getLocationsByType(string $type): JsonResponse
    {
        $locationsFile = $this->getParameter('kernel.project_dir') . '/public/data/locations.json';
        
        if (!file_exists($locationsFile)) {
            return new JsonResponse(['error' => 'Locations file not found'], 404);
        }

        $data = json_decode(file_get_contents($locationsFile), true);
        $locations = $data['locations'] ?? [];
        
        // Filter by activity type
        $filtered = array_filter($locations, function($loc) use ($type) {
            return in_array($type, $loc['activityTypes']);
        });

        return new JsonResponse([
            'success' => true,
            'count' => count($filtered),
            'locations' => array_values($filtered)
        ]);
    }

    /**
     * Check if location is available for a specific date/time
     */
    #[Route('/check-availability', name: 'api_check_availability', methods: ['POST'])]
    public function checkAvailability(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $locationName = $data['location'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $activityId = $data['activity_id'] ?? null;

        $locationsFile = $this->getParameter('kernel.project_dir') . '/public/data/locations.json';
        $locationsData = json_decode(file_get_contents($locationsFile), true);
        
        // Find location
        $location = null;
        foreach ($locationsData['locations'] as $loc) {
            if ($loc['name'] === $locationName) {
                $location = $loc;
                break;
            }
        }

        if (!$location) {
            return new JsonResponse(['success' => false, 'message' => 'Location not found']);
        }

        // Check if any activities are using this location at the same time
        $conflictingActivities = [];
        $activities = $this->activityRepository->findBy(['location' => $locationName]);
        
        foreach ($activities as $activity) {
            // Skip the current activity if provided
            if ($activityId && $activity->getId() === $activityId) {
                continue;
            }

            $actStart = $activity->getStartTime();
            $actEnd = $activity->getEndTime();
            
            // Parse request times
            $reqStart = new \DateTime($startTime);
            $reqEnd = $endTime ? new \DateTime($endTime) : $reqStart;

            // Check for conflict (overlapping times)
            if ($actStart && $actEnd && 
                $actStart < $reqEnd && $actEnd > $reqStart) {
                $conflictingActivities[] = [
                    'title' => $activity->getTitle(),
                    'start' => $actStart->format('d/m/Y H:i'),
                    'end' => $actEnd->format('d/m/Y H:i'),
                ];
            }
        }

        return new JsonResponse([
            'success' => true,
            'available' => count($conflictingActivities) === 0,
            'location' => $location,
            'conflicts' => $conflictingActivities,
            'message' => count($conflictingActivities) === 0 
                ? 'Location is available' 
                : 'Location has conflicts with other activities'
        ]);
    }

    /**
     * Check availability for all locations on a specific date
     */
    #[Route('/check-date-availability', name: 'api_check_date_availability', methods: ['POST'])]
    public function checkDateAvailability(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $activityId = $data['activity_id'] ?? null;

        $locationsFile = $this->getParameter('kernel.project_dir') . '/public/data/locations.json';
        $locationsData = json_decode(file_get_contents($locationsFile), true);
        
        $result = [];
        
        foreach ($locationsData['locations'] as $location) {
            $conflictingActivities = [];
            $activities = $this->activityRepository->findBy(['location' => $location['name']]);
            
            foreach ($activities as $activity) {
                if ($activityId && $activity->getId() === $activityId) {
                    continue;
                }

                $actStart = $activity->getStartTime();
                $actEnd = $activity->getEndTime();
                
                try {
                    $reqStart = new \DateTime($startTime);
                    $reqEnd = $endTime ? new \DateTime($endTime) : $reqStart;

                    if ($actStart && $actEnd && 
                        $actStart < $reqEnd && $actEnd > $reqStart) {
                        $conflictingActivities[] = [
                            'title' => $activity->getTitle(),
                            'start' => $actStart->format('d/m/Y H:i'),
                            'end' => $actEnd->format('d/m/Y H:i'),
                        ];
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            $result[$location['id']] = [
                'available' => count($conflictingActivities) === 0,
                'conflicts' => $conflictingActivities,
            ];
        }

        return new JsonResponse([
            'success' => true,
            'availability' => $result
        ]);
    }

    /**
     * Get location coordinates by name
     */
    #[Route('/by-name/{name}', name: 'api_location_by_name', methods: ['GET'])]
    public function getLocationByName(string $name): JsonResponse
    {
        $locationsFile = $this->getParameter('kernel.project_dir') . '/public/data/locations.json';
        $data = json_decode(file_get_contents($locationsFile), true);
        
        foreach ($data['locations'] as $location) {
            if (strtolower($location['name']) === strtolower($name)) {
                return new JsonResponse([
                    'success' => true, 
                    'location' => $location
                ]);
            }
        }

        return new JsonResponse(['success' => false, 'message' => 'Location not found'], 404);
    }

    /**
     * Get location details
     */
    #[Route('/{id}', name: 'api_location_detail', methods: ['GET'])]
    public function getLocationDetail(string $id): JsonResponse
    {
        $locationsFile = $this->getParameter('kernel.project_dir') . '/public/data/locations.json';
        $data = json_decode(file_get_contents($locationsFile), true);
        
        foreach ($data['locations'] as $location) {
            if ($location['id'] === $id) {
                return new JsonResponse(['success' => true, 'location' => $location]);
            }
        }

        return new JsonResponse(['success' => false, 'message' => 'Location not found'], 404);
    }
}
