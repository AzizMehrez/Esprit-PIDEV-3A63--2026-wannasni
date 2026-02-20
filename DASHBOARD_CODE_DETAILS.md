# ADMIN DASHBOARD - CODE IMPLEMENTATION DETAILS

## Controller Implementation

### File: `src/Controller/Admin/DashboardController.php`

**Key Methods:**

#### 1. Fetching User's Activities
```php
// Get current user's joined activities
$userActivities = [];
if ($user instanceof \App\Entity\User) {
    $participations = $this->participationRepository->findBy(
        ['seniorId' => $user->getId()],
        ['registeredAt' => 'DESC']
    );
    
    foreach ($participations as $participation) {
        $status = $participation->getStatus();
        // Only show active participations
        if (in_array($status, ['présent', 'registered', 'inscrit'])) {
            $activity = $this->activityRepository->find($participation->getActivityId());
            if ($activity) {
                $userActivities[] = [
                    'id' => $activity->getId(),
                    'title' => $activity->getTitle(),
                    'type' => $activity->getType(),
                    'startTime' => $activity->getStartTime()?->format('d/m/Y H:i') ?? 'TBD',
                    'location' => $activity->getLocation() ?? 'TBD',
                ];
            }
        }
    }
}
```

#### 2. Fetching All Activities with Real Counts
```php
// Get all activities with real participant counts
$allActivities = $this->activityRepository->findBy(['isActive' => true], ['startTime' => 'DESC']);
$activitiesData = [];
foreach ($allActivities as $activity) {
    // KEY FIX: Use countActiveByActivity() instead of hard-coded value
    $participantCount = $this->participationRepository->countActiveByActivity($activity->getId());
    $activitiesData[] = [
        'id' => $activity->getId(),
        'title' => $activity->getTitle(),
        'type' => $activity->getType(),
        'participants' => $participantCount,  // Real count from DB
        'maxParticipants' => $activity->getMaxParticipants(),
        'startTime' => $activity->getStartTime()?->format('d/m/Y H:i') ?? 'TBD',
        'location' => $activity->getLocation() ?? 'TBD',
    ];
}
```

#### 3. Fetching Recent Activities
```php
// Get real recent activities from participations
$recentParticipations = $this->participationRepository->findRecentChanges(15);
$recentActivities = [];

$conn = $this->em->getConnection();

foreach ($recentParticipations as $participation) {
    try {
        // Get user name
        $userName = 'Unknown User';
        $userResult = $conn->executeQuery(
            'SELECT first_name, last_name FROM user WHERE id = ?',
            [$participation->getSeniorId()]
        )->fetchAssociative();
        if ($userResult) {
            $userName = trim(($userResult['first_name'] ?? '') . ' ' . ($userResult['last_name'] ?? ''));
        }
        
        // Get activity name
        $activityName = $participation->getTitle() ?? 'Activity';
        
        // Determine action based on status
        $status = $participation->getStatus();
        if (in_array($status, ['présent', 'registered', 'inscrit'])) {
            $action = 'Joined activity';
            $type = 'activity';
        } else {
            $action = 'Cancelled activity';
            $type = 'activity-cancel';
        }
        
        // Calculate time ago
        $time = $participation->getRegisteredAt();
        $timeAgo = 'N/A';
        if ($time) {
            $now = new \DateTime();
            $interval = $now->diff($time);
            
            if ($interval->days > 0) {
                $timeAgo = $interval->days . ' day' . ($interval->days > 1 ? 's' : '') . ' ago';
            } elseif ($interval->h > 0) {
                $timeAgo = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
            } elseif ($interval->i > 0) {
                $timeAgo = $interval->i . ' min' . ($interval->i > 1 ? 's' : '') . ' ago';
            } else {
                $timeAgo = 'just now';
            }
        }
        
        $recentActivities[] = [
            'user' => $userName,
            'action' => $action . ' "' . $activityName . '"',
            'time' => $timeAgo,
            'type' => $type,
        ];
    } catch (\Exception $e) {
        continue;
    }
}

// Keep only the 10 most recent
$recentActivities = array_slice($recentActivities, 0, 10);
```

#### 4. Render Template
```php
return $this->render('admin/dashboard.html.twig', [
    'stats' => $stats,
    'recent_activities' => $recentActivities,
    'user_activities' => $userActivities,
    'all_activities' => $activitiesData,
]);
```

## Repository Methods

### File: `src/Repository/ParticipationRepository.php`

#### Method: `countActiveByActivity()`
```php
public function countActiveByActivity(int $activityId): int
{
    $count = $this->createQueryBuilder('p')
        ->select('COUNT(p.id)')
        ->where('p.activityId = :activityId')
        ->andWhere('p.status IN (:statuses)')
        ->setParameter('activityId', $activityId)
        ->setParameter('statuses', ['présent', 'registered', 'inscrit'])
        ->getQuery()
        ->getSingleScalarResult();
    
    return (int) $count;
}
```

#### Method: `findRecentChanges()`
```php
public function findRecentChanges(int $limit = 10, string $orderBy = 'DESC')
{
    return $this->createQueryBuilder('p')
        ->orderBy('p.registeredAt', $orderBy)
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}
```

## Template Implementation

### File: `templates/admin/dashboard.html.twig`

#### User Activities Section
```twig
<!-- Your Activities (Current User) -->
<div class="admin-card">
    <div class="card-header">
        <h2 class="card-title">👤 Mes Activités</h2>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
        {% if user_activities|length > 0 %}
            {% for activity in user_activities %}
            <div class="activity-item" style="padding: 0.75rem 0;">
                <div class="activity-icon">🎯</div>
                <div class="activity-content" style="flex: 1;">
                    <div class="activity-text" style="font-weight: 600;">{{ activity.title }}</div>
                    <div class="activity-time" style="font-size: 0.8rem;">📍 {{ activity.location }}</div>
                    <div class="activity-time" style="font-size: 0.8rem;">📅 {{ activity.startTime }}</div>
                </div>
            </div>
            {% endfor %}
        {% else %}
            <div style="text-align: center; padding: 1.5rem; color: var(--admin-text-muted);">
                <p style="font-size: 0.95rem;">Vous n'avez pas encore rejoint d'activité</p>
            </div>
        {% endif %}
    </div>
</div>
```

#### All Activities with Real Counts
```twig
<!-- All Activities with Counts -->
<div class="admin-card">
    <div class="card-header">
        <h2 class="card-title">📊 Toutes les Activités</h2>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
        {% if all_activities|length > 0 %}
            {% for activity in all_activities|slice(0, 5) %}
            <div class="activity-item" style="padding: 0.75rem 0;">
                <div class="activity-icon" style="font-size: 0.9rem;">
                    {% if activity.type == 'physical' %}🏃
                    {% elseif activity.type == 'cognitive' %}🧠
                    {% elseif activity.type == 'creative' %}🎨
                    {% elseif activity.type == 'social' %}👥
                    {% else %}📋{% endif %}
                </div>
                <div class="activity-content" style="flex: 1;">
                    <div class="activity-text" style="font-weight: 600;">{{ activity.title }}</div>
                    <!-- KEY CHANGE: Shows real participant count, not 0 -->
                    <div class="activity-time" style="font-size: 0.8rem;">👥 {{ activity.participants }}/{{ activity.maxParticipants ?? '∞' }} participants</div>
                </div>
            </div>
            {% endfor %}
        {% else %}
            <div style="text-align: center; padding: 1.5rem; color: var(--admin-text-muted);">
                <p style="font-size: 0.95rem;">Aucune activité disponible</p>
            </div>
        {% endif %}
    </div>
</div>
```

#### Recent Activity with Auto-Refresh
```twig
<!-- Recent Activity -->
<div class="admin-card">
    <div class="card-header">
        <h2 class="card-title">📋 Activité Récente</h2>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
        {% if recent_activities|length > 0 %}
            {% for activity in recent_activities|slice(0, 5) %}
            <div class="activity-item" style="padding: 0.75rem 0;">
                <div class="activity-icon">
                    {% if activity.type == 'activity' %}✅
                    {% elseif activity.type == 'activity-cancel' %}❌
                    {% else %}📋{% endif %}
                </div>
                <div class="activity-content" style="flex: 1;">
                    <div class="activity-text" style="font-size: 0.85rem;"><strong>{{ activity.user }}</strong></div>
                    <div class="activity-time" style="font-size: 0.75rem;">{{ activity.action }}</div>
                    <div class="activity-time" style="font-size: 0.75rem; color: #999;">{{ activity.time }}</div>
                </div>
            </div>
            {% endfor %}
        {% else %}
            <div style="text-align: center; padding: 1.5rem; color: var(--admin-text-muted);">
                <p style="font-size: 0.95rem;">Aucune activité récente</p>
            </div>
        {% endif %}
    </div>
</div>
```

#### JavaScript Auto-Refresh
```javascript
<script>
document.addEventListener('DOMContentLoaded', function() {
    const activityContainer = document.querySelector('.admin-card:first-child .card-body');
    
    // Fetch recent activities every 5 seconds
    function refreshRecentActivities() {
        fetch('/api/admin/recent-activities')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.activities && data.activities.length > 0) {
                    updateActivityList(data.activities);
                }
            })
            .catch(error => console.warn('Failed to refresh activities:', error));
    }
    
    function updateActivityList(activities) {
        if (!activityContainer) return;
        
        // Build HTML for activity items
        let html = '';
        for (const activity of activities) {
            const icon = activity.icon || '📋';
            html += `
                <div class="activity-item">
                    <div class="activity-icon">${icon}</div>
                    <div class="activity-content">
                        <div class="activity-text"><strong>${activity.user}</strong> - ${activity.action} "${activity.activity}"</div>
                        <div class="activity-time">${activity.time}</div>
                    </div>
                </div>
            `;
        }
        
        activityContainer.innerHTML = html;
    }
    
    // Initial refresh and then every 5 seconds
    refreshRecentActivities();
    setInterval(refreshRecentActivities, 5000);
});
</script>
```

## Key Configuration

### Status Values for Active Participations
```php
$activeStatuses = ['présent', 'registered', 'inscrit'];
$cancelledStatuses = ['annulé', 'cancelled'];
```

These are the exact status values stored in the database and checked throughout the code.

### Time Zone Handling
The dashboard uses PHP's DateTime comparison for relative time display:
```php
$now = new \DateTime();
$interval = $now->diff($time);
```

## Performance Optimizations

1. **Single query per activity** - participant count fetched with dedicated method
2. **Limit results** - Recent activities limited to 15, displayed as 10
3. **Array slicing** - All activities reduced to top 5 in template
4. **Async auto-refresh** - Non-blocking fetch for recent activities
5. **Proper indexing** - Database indexes on `participations(senior_id, status)` and `activites(is_active)`

## Integration Points

### With Voice Assistant
When voice assistant adds a participation:
1. Creates new participation record
2. Updates `activites.current_participants` counter
3. Dashboard fetches updated count via `countActiveByActivity()`
4. Recent activity feed shows new join within 5 seconds

### With API Endpoints
- `/api/admin/recent-activities` - Used by JavaScript for auto-refresh
- Existing chat/voice endpoints - No changes needed

## Testing Database Queries

To verify data integrity, you can run these queries:

```sql
-- Check user activities for a specific user
SELECT a.id, a.title, COUNT(p.id) as participants
FROM activites a
LEFT JOIN participations p ON a.id = p.activity_id 
  AND p.status IN ('présent', 'registered', 'inscrit')
WHERE a.is_active = 1
GROUP BY a.id;

-- Check recent activities
SELECT p.id, u.first_name, u.last_name, p.title, p.status, p.registered_at
FROM participations p
JOIN user u ON p.senior_id = u.id
ORDER BY p.registered_at DESC
LIMIT 15;

-- Check participant counts match
SELECT a.id, a.title, a.current_participants,
       COUNT(p.id) as actual_count
FROM activites a
LEFT JOIN participations p ON a.id = p.activity_id 
  AND p.status IN ('présent', 'registered', 'inscrit')
WHERE a.is_active = 1
GROUP BY a.id;
```

## Troubleshooting

### Participant Count Shows Wrong Number
- Verify participation status values are correct: `présent`, `registered`, `inscrit`
- Check `countActiveByActivity()` is using correct statuses
- Run database query above to verify actual counts

### Recent Activity Not Updating
- Check JavaScript console for fetch errors
- Verify `/api/admin/recent-activities` endpoint is accessible
- Confirm auto-refresh interval is 5 seconds

### User Activities Not Showing
- Verify `getUser()` returns valid User object
- Check participations exist for user with active status
- Verify `findBy(['seniorId' => userId])` returns results

## Future Enhancements

- [ ] Add pagination for all activities (currently shows top 5)
- [ ] Add activity filtering by type
- [ ] Add search functionality
- [ ] Export dashboard data to PDF
- [ ] Add user activity history
- [ ] Add detailed participant management
