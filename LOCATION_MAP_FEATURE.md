# LOCATION SELECTION MAP FEATURE

## Overview
A new interactive map-based location selection system has been integrated into the activity creation flow. After creating an activity, users are directed to select a location from a list of real venues in Tunisia.

## How It Works

### 1. Activity Creation Flow (Updated)
```
Create Activity Form
    ↓
Save Activity
    ↓
Redirect to Location Selection Map ← NEW!
    ↓
Select Location from Map
    ↓
Save Activity with Location
    ↓
Return to Activities List
```

### 2. Location Selection Features
- **Interactive Map**: Leaflet-based map showing all available locations in Tunisia
- **Smart Filtering**: Locations filtered based on activity type (yoga → yoga studios, dance → dance halls, etc.)
- **Map Markers**: Click markers on map to select locations
- **Location List**: Sidebar showing all relevant venues with details
- **Real-Time Details**: Click any location to see:
  - Address
  - Capacity (number of participants)
  - Available amenities
  - Operating hours
  - Description
- **Availability Checking**: Shows if location is available for your activity's date/time
- **Skip Option**: Can continue without selecting a location

## Supported Activity Types & Venues

### Physical Activities 🏃
- Gyms (Elite Fitness, etc.)
- Parks (Parc El Omrane, Parc Belvedère)
- Pools (Complexe Sportif Olympique)
- Dance Studios
- Yoga Studios

### Cognitive Activities 🧠
- Libraries (Bibliothèque Nationale)
- Tech Hubs (Tech Hub Sfax)
- Schools (Institut Supérieur d'Informatique)
- Community Centers

### Creative Activities 🎨
- Art Studios
- Dance Studios
- Community Centers
- Auditoriums

### Social Activities 👥
- Community Centers
- Parks
- Libraries
- Yoga Studios
- Dance Studios
- Auditoriums

## Available Locations in Tunisia

### Tunis Region
1. **Yoga & Wellness Studio Tunis** - Rue de la Liberté
   - Capacity: 30 | Type: Studio | Hours: 09:00-20:00
   
2. **Parc El Omrane Supérieur** - Outdoor
   - Capacity: 50 | Type: Park | Hours: 06:00-18:00
   
3. **Studio Salsateca** - Avenue Habib Bourguiba
   - Capacity: 40 | Type: Dance | Hours: 10:00-22:00
   
4. **Centre Culturel** - Boulevard 9 Avril
   - Capacity: 80 | Type: Multipurpose | Hours: 09:00-21:00
   
5. **Elite Fitness Gym** - Zone Industrielle Ariana
   - Capacity: 60 | Type: Gym | Hours: 06:00-22:00
   
6. **Bibliothèque Nationale** - Place 7 Novembre
   - Capacity: 100 | Type: Library | Hours: 09:00-18:00
   
7. **Institut Supérieur d'Informatique** - Technopole Ghazala
   - Capacity: 60 | Type: School/Tech | Hours: 08:00-17:00
   
8. **Parc Belvedère** - Avenue de la Liberté
   - Capacity: 100 | Type: Park | Hours: 06:00-20:00
   
9. **Maison des Jeunes** - Avenue Mohamed Amin
   - Capacity: 70 | Type: Community | Hours: 10:00-20:00
   
10. **Studio D'Art & Créativité** - Rue de Carthage
    - Capacity: 25 | Type: Art Studio | Hours: 10:00-18:00
    
11. **Auditorium Ezzahra** - Route La Marsa
    - Capacity: 200 | Type: Auditorium | Hours: 09:00-22:00
    
12. **Complexe Sportif Olympique** - Boulevard du Lac Nord
    - Capacity: 80 | Type: Pool | Hours: 06:00-20:00

### Sfax Region
13. **Tech Hub Sfax** - Avenue de l'Environnement
    - Capacity: 50 | Type: Tech Hub | Hours: 08:00-18:00

## How to Use

### Step 1: Create an Activity
1. Go to Admin → Activités → Ajouter une Activité
2. Fill in activity details:
   - Title (e.g., "Yoga Class")
   - Description
   - Type (Physical, Cognitive, Creative, Social)
   - Start time
   - End time
   - Max participants

### Step 2: Select Location
After saving, you'll be redirected to the location selection map:

1. **View the Map**: See all available locations in Tunisia
2. **Filter Results**: Locations automatically filtered by activity type
3. **Browse List**: Scroll sidebar to see all suggested venues
4. **See Details**: Click on any location to see:
   - Full address
   - Capacity
   - Amenities (WiFi, Parking, AC, etc.)
   - Operating hours
   - Description
5. **Confirm Selection**: Click "✓ Confirmer cette Localisation"

### Step 3: Complete
Activity is saved with the selected location and you return to the activities list.

## API Endpoints

### Get Locations by Activity Type
```
GET /api/locations/by-type/{type}
Response: { locations: [...], count: N }
```

### Check Location Availability
```
POST /api/locations/check-availability
Body: {
  location: "Location Name",
  start_time: "2026-02-20 10:00",
  end_time: "2026-02-20 12:00",
  activity_id: 123
}
Response: { available: true|false, conflicts: [...] }
```

### Get Location Details
```
GET /api/locations/{id}
Response: { location: {...} }
```

## Technical Details

### Files Added/Modified

#### New Files
- `public/data/locations.json` - Location database with all venues
- `src/Controller/Api/LocationController.php` - Location API endpoints
- `templates/admin/activities/select_location.html.twig` - Map interface

#### Modified Files
- `src/Controller/Admin/ActivityAdminController.php` - New location selection route

### Location Data Structure
```json
{
  "id": "unique_id",
  "name": "Venue Name",
  "type": "venue_type",
  "activityTypes": ["physical", "social"],
  "address": "Street Address",
  "latitude": 36.8065,
  "longitude": 10.1678,
  "capacity": 30,
  "description": "Description",
  "amenities": ["Wifi", "Parking"],
  "availability": "09:00-20:00"
}
```

## Features

✅ Interactive Leaflet map centered on Tunisia
✅ Real-time location filtering by activity type
✅ Clickable map markers
✅ Location sidebar with details
✅ Amenities display
✅ Capacity information
✅ Operating hours display
✅ Geographic coordinates (lat/lng)
✅ Skip location option
✅ Responsive design (desktop and mobile)
✅ Availability checking API
✅ Conflict detection with other activities

## Database Integration

The location is saved to the `Activity` entity's `location` field as the venue name:
```php
$activity->setLocation($selectedLocation);
```

When viewing activity details, the location displays naturally in all lists and detail views.

## Future Enhancements

1. **Real-Time Booking**: Integrate with venue booking systems
2. **Capacity Management**: Check actual venue availability
3. **Pricing**: Add rental fees based on location and duration
4. **Reviews**: Show venue ratings from previous activities
5. **More Locations**: Add venues in other Tunisian cities
6. **Custom Locations**: Allow admins to add new venues
7. **Occupancy Calendar**: Visual calendar showing venue busy days
8. **Email Notifications**: Notify venue managers of bookings

## Troubleshooting

### Map Not Loading
- Check that `/data/locations.json` exists
- Verify Leaflet CDN is accessible
- Check browser console for errors

### Locations Not Filtering
- Verify activity type matches location's `activityTypes`
- Check location JSON format
- Clear browser cache

### Selection Not Saving
- Ensure location name matches exactly
- Check database connection
- Verify form submission

## Testing

To test the feature:
1. Go to `/admin/activities/new`
2. Create a new activity with title "Test Activity"
3. Select type "physical" or "social"
4. Set start/end times
5. Click Create
6. You'll be redirected to location map
7. Click on a marker or location in list
8. Click "✓ Confirmer cette Localisation"
9. Activity should be created with location

## Status

✅ **COMPLETE AND READY TO USE**

All components implemented and tested.
