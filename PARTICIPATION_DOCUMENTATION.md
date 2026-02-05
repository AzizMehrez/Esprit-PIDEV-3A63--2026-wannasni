# Participation Management System - Complete Documentation

## Overview
The Participation CRUD system tracks user engagement with activities, capturing detailed feedback, mood tracking, and attendance information. It provides a complete lifecycle from enrollment to feedback collection and analytics.

---

## System Architecture

### 1. **Database Layer** (Entity + Repository)

#### `Participation Entity` (src/Entity/Participation.php)
Stores all participation data with these main fields:

**Core Fields:**
- `id` - Unique identifier
- `activityId` - Reference to Activity
- `participantId` - Reference to User/Senior

**Status Tracking:**
- `status` - Values: `inscrit`, `présent`, `absent_excusé`, `absent_non_excusé`
- `registrationDate` - When they enrolled
- `registrationMethod` - How: `appli`, `téléphone`, `en_personne`
- `presenceConfirmationDate` - When attendance was confirmed

**Feedback & Ratings:**
- `feedbackRating` - 1-5 star rating
- `feedbackComment` - Text review
- `moodBefore` - 1-5 scale mood before activity
- `moodAfter` - 1-5 scale mood after activity
- `problemsEncountered` - Issues experienced
- `recommendToFriends` - Boolean: Would recommend?
- `photoUrls` - JSON array of photo URLs
- `shareWithFamily` - Share results? (oui/non)
- `hasCertificate` - Boolean: Completed certificate

#### `ParticipationRepository` (src/Repository/ParticipationRepository.php)
Provides database queries:

**Essential Methods:**
- `save($participation, $flush)` - Persist to database
- `remove($participation, $flush)` - Delete record
- `findByParticipantId($id)` - Get all participations for a user
- `findByActivityId($id)` - Get all participants in an activity
- `findWithFeedback($activityId)` - Only participations with ratings/comments
- `getAverageRating($activityId)` - Calculate activity rating
- `countByStatus($activityId)` - Attendance breakdown (present/absent)

---

## Application Flow

### Phase 1: Enrollment
```
User visits Activity Page
    ↓
Clicks "S'inscrire" button
    ↓
POST /my-activities/enroll/{activityId}
    ↓
Controller: UserActivityController::enrollActivity()
    ↓
Creates new Participation record
Sets status = 'inscrit'
Sets registrationDate = now()
Sets registrationMethod = 'appli'
    ↓
Saved to Database
    ↓
Activity participant count incremented
    ↓
Redirect back to Activities
```

### Phase 2: Attendance Tracking (Admin)
```
After activity completes
    ↓
Coach/Admin marks attendance
    ↓
PATCH /participations/{id}/attendance/{status}
    ↓
Updates status & presenceConfirmationDate
    ↓
Database updated
```

### Phase 3: Feedback Submission
```
User views participation history
    ↓
Clicks "Voir détails" on enrollment
    ↓
GET /participations/{id}
    ↓
Shows feedback form (if no feedback yet)
    ↓
User fills form:
  - Star rating (1-5)
  - Mood before/after
  - Comments
  - Problem report
  - Recommend? checkbox
  - Share with family?
    ↓
POST /participations/{id}/feedback
    ↓
ParticipationController::submitFeedback()
    ↓
Updates Participation with feedback data
    ↓
Redirect to Show page with feedback displayed
```

### Phase 4: Analytics
```
Coach views activity stats
    ↓
GET /participations/activity/{activityId}/stats
    ↓
ParticipationController::activityStats()
    ↓
Queries:
  - Total participations
  - Attendance counts by status
  - Average rating
  - Feedback list
    ↓
Renders stats.html.twig with dashboard
```

---

## Controller: ParticipationController

**Location:** `src/Controller/Front/ParticipationController.php`

### Routes & Methods

#### 1. History View (`/history`)
```php
GET /participations/history
→ ParticipationController::history()

Purpose: Show all participations for logged-in user
Returns: history.html.twig with list of enrollments
```

#### 2. Show Details (`/{id}`)
```php
GET /participations/{id}
→ ParticipationController::show($id)

Purpose: Display single participation details
Return Types:
  - If feedback exists: Shows feedback display
  - If no feedback: Shows feedback form
```

#### 3. Submit Feedback (`/{id}/feedback`)
```php
POST /participations/{id}/feedback
→ ParticipationController::submitFeedback($id, Request $request)

Form Parameters:
  - rating: 1-5 (int)
  - comment: text feedback
  - mood_before: 1-5 (int)
  - mood_after: 1-5 (int)
  - problems: text describing issues
  - recommend: boolean
  - share: 'oui'/'non'

Updates Participation & persists to database
```

#### 4. Mark Attendance (`/{id}/attendance/{status}`)
```php
POST /participations/{id}/attendance/{status}
→ ParticipationController::markAttendance($id, $status)

Allowed Status Values:
  - present → 'présent'
  - absent_excused → 'absent_excusé'
  - absent_not_excused → 'absent_non_excusé'

Updates status & confirmation date
```

#### 5. Activity Statistics (`/activity/{activityId}/stats`)
```php
GET /participations/activity/{activityId}/stats
→ ParticipationController::activityStats($activityId)

Returns: stats.html.twig with:
  - Participation counts
  - Attendance breakdown
  - Average rating
  - Feedback summary
```

---

## Frontend Templates

### 1. `history.html.twig`
**Path:** `templates/front/participations/history.html.twig`

**Features:**
- List of all participations for user
- Status badges (Inscrit/Présent/Absent)
- Star rating display
- Quick preview of feedback
- Link to detailed view

**Variables:**
```twig
participations: [
  {
    participation: Participation object,
    activity: Activity object
  }
]
```

### 2. `show.html.twig`
**Path:** `templates/front/participations/show.html.twig`

**Two States:**

**State A: No Feedback Yet**
Shows form with:
- Star rating selector (1-5)
- Mood slider before activity (1-5)
- Mood slider after activity (1-5)
- Comment textarea
- Problems encountered textarea
- Recommend checkbox
- Share with family dropdown

**State B: Feedback Exists**
Shows completed feedback:
- Display rating with stars
- Display moods with indicators
- Show comments
- Show problem reports
- Share status

### 3. `stats.html.twig`
**Path:** `templates/front/participations/stats.html.twig`

**Displays:**
- Total participation count
- Feedback response rate
- Average rating (if any)
- Attendance breakdown table
- Individual feedback list

---

## Usage Flow Chart

```
┌─────────────────────────────────────────────────────────────┐
│                    PARTICIPATION LIFECYCLE                  │
└─────────────────────────────────────────────────────────────┘

                    [Activity Page]
                         ↓
                   [User clicks S'inscrire]
                         ↓
        ┌────────────────────────────────┐
        │   POST /enroll/{id}            │
        │   UserActivityController       │
        │   Creates Participation        │
        │   status = 'inscrit'           │
        └────────────────────────────────┘
                         ↓
                    [Activity Occurs]
                         ↓
        ┌────────────────────────────────┐
        │   MARK ATTENDANCE (by coach)    │
        │   /attendance/{id}/{status}    │
        │   Updates status to present    │
        │   time = now()                 │
        └────────────────────────────────┘
                         ↓
        ┌────────────────────────────────┐
        │   USER VIEWS HISTORY           │
        │   /participations/history      │
        │   Lists all enrollments        │
        └────────────────────────────────┘
                         ↓
        ┌────────────────────────────────┐
        │   CLICKS "VIEW DETAILS"        │
        │   GET /participations/{id}     │
        │   Shows feedback form          │
        └────────────────────────────────┘
                         ↓
        ┌────────────────────────────────┐
        │   USER SUBMITS FEEDBACK        │
        │   POST /feedback/{id}          │
        │   Stars, Mood, Comments        │
        └────────────────────────────────┘
                         ↓
        ┌────────────────────────────────┐
        │   COACH VIEWS ANALYTICS        │
        │   /activity/{id}/stats         │
        │   Ratings, Attendance, etc.    │
        └────────────────────────────────┘
```

---

## Database Schema

```sql
CREATE TABLE participations (
  id INT PRIMARY KEY AUTO_INCREMENT,
  activity_id INT NOT NULL,
  participant_id INT,
  status VARCHAR(50),                      -- inscrit/présent/absent_excusé/absent_non_excusé
  registration_date DATETIME,
  registration_method VARCHAR(50),         -- appli/téléphone/en_personne
  feedback_rating INT,                     -- 1-5
  feedback_comment TEXT,
  mood_before INT,                         -- 1-5
  mood_after INT,                          -- 1-5
  problems_encountered TEXT,
  recommend_to_friends BOOLEAN,
  photo_urls TEXT,                         -- JSON array
  presence_confirmation_date DATETIME,
  has_certificate BOOLEAN,
  share_with_family VARCHAR(50),           -- oui/non
  senior_id INT,                           -- legacy
  registered_at DATETIME,                  -- legacy
  
  FOREIGN KEY (activity_id) REFERENCES activites(id)
);
```

---

## Key Features Explained

### 1. **Mood Tracking**
Users rate their mood (1-5) before and after activities to measure happiness/wellness impact.

**Example:**
- Before: 2/5 (Not feeling great)
- After: 4/5 (Much better!)
- Impact: +2 mood improvement

### 2. **Star Ratings**
5-star system for activity quality.
- Repository calculates average: `getAverageRating()`
- Used for activity recommendations

### 3. **Attendance Tracking**
Four statuses cover different scenarios:
- `inscrit` - Enrolled but not attended yet
- `présent` - Attended activity
- `absent_excusé` - Didn't attend with excuse
- `absent_non_excusé` - Didn't attend, no excuse

### 4. **Feedback Analytics**
Repository provides aggregation:
- `findWithFeedback()` - Only feedback-providing participants
- `countByStatus()` - Attendance breakdown
- `getAverageRating()` - Activity average score

### 5. **Photo Sharing**
`photoUrls` stored as JSON array:
```json
["https://example.com/photo1.jpg", "https://example.com/photo2.jpg"]
```

---

## Testing the System

### Manual Testing Steps:

1. **Enrollment:**
   - Go to Activities page
   - Click "S'inscrire"
   - Should move to "Mes Inscriptions"
   - Check database: New Participation record created

2. **View History:**
   - Click "Mon Historique"
   - Should list all enrollments
   - Click "Voir détails"

3. **Submit Feedback:**
   - Rating form appears
   - Fill all fields
   - Click "Soumettre mon avis"
   - Check feedback displays
   - Verify in database

4. **View Stats:**
   - Admin access `/participations/activity/{id}/stats`
   - Verify counts and ratings
   - Check feedback list

---

## Integration Points

### With UserActivityController:
```php
// When enrollment happens:
$participation = new Participation();
$participation->setParticipantId($userId);
$participation->setActivityId($activityId);
$participation->setStatus('inscrit');
$participationRepository->save($participation, true);
```

### With ActivityRepository:
```php
// Get activity details for participation view:
$activity = $activityRepository->find($participation->getActivityId());
```

---

## Future Enhancements

Optional additions:
- Email notifications on feedback submission
- Achievement badges for attendance
- Social sharing of photos
- Admin CSV export of feedback
- Automatic certificate generation
- Reminder emails before activities
- Integration with health tracking APIs

---

## Summary

The Participation system provides:
✅ Complete enrollment tracking
✅ Attendance management
✅ Mood/wellness tracking
✅ Multi-field feedback collection
✅ Analytics & reporting
✅ Photo sharing capability
✅ Family sharing options
✅ Certificate support

All with a user-friendly interface and comprehensive backend queries.
