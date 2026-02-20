# Location Map Feature - Quick Test Guide

## 🚀 How to Test

### Step 1: Start the Application
```bash
php -S localhost:8000
```
Navigate to: `http://localhost:8000/admin`

### Step 2: Create a New Activity
1. Click **"Activités"** in left sidebar
2. Click **"➕ Nouvelle Activité"** button
3. Fill in the form:
   - **Titre**: "Yoga Class Tuesday"
   - **Description**: "Beginner yoga session"
   - **Type**: Select **"physical"**
   - **Début**: Set date/time to tomorrow
   - **Fin**: Set 1-2 hours after start
   - **Max Participants**: "20"
   - **Status**: "active"
4. Click **"Créer"** button

### Step 3: You'll See the Location Map
After clicking create, you should be redirected to:
```
https://localhost:8000/admin/activities/{id}/select-location
```

The page shows:
- ✅ Interactive map of Tunisia (using Leaflet)
- ✅ Activity details on the right (title, type, time, capacity)
- ✅ List of suggested locations filtered by activity type
- ✅ Markers on the map for each location

### Step 4: Select a Location
**Option A - From List:**
1. Scroll through "Lieux Suggérés" (Suggested Places)
2. Click on any location (e.g., "Yoga & Wellness Studio Tunis")
3. Details appear below showing:
   - Description
   - Address
   - Capacity
   - Opening hours
   - Amenities (WiFi, Parking, etc.)

**Option B - From Map:**
1. Click any marker (📍) on the map
2. A popup shows the venue name
3. Click the location again to select it
4. Details appear below

### Step 5: Confirm Selection
- Click **"✓ Confirmer cette Localisation"** (green button)
- Or click **"⏭️ Continuer sans lieu"** to skip location

### Step 6: Verify
After confirming:
1. You'll be redirected back to activities list
2. Your new activity should appear in the list
3. The **"Participants"** column shows the correct location name

## 📊 What You Should See

### Map Section (Left Side)
- Tunisia centered on the map
- Multiple colored markers showing venue locations
- Colors represent different venue types
- Zoom in/out using mouse wheel or controls

### Sidebar Section (Right Side)
**Activity Info Box:**
```
📋 Détails de l'Activité
Titre: Yoga Class Tuesday
Type: PHYSICAL
Début: 20/02/2026 10:00
Fin: 20/02/2026 12:00
Participants: 20
```

**Suggested Locations Box:**
```
🎯 Lieux Suggérés

Yoga & Wellness Studio Tunis
    📍 Rue de la Liberté, Tunis
    👥 30 places | ⏰ 09:00-20:00
    ✓ Disponible
```

**Location Details Box** (after selection):
```
📌 Yoga & Wellness Studio Tunis
    📍 Rue de la Liberté, Tunis 1000
    📝 Studio spécialisé en yoga...
    👥 Capacité: 30 personnes
    ⏰ Disponible: 09:00-20:00
    ✓ Wifi  ✓ Parking  ✓ Vestiaires
```

## 🧪 Test Cases

### Test 1: Physical Activity
- **Activity Type**: Physical
- **Expected Locations**: Gyms, Parks, Pools, Studios
- **Should See**: 
  - Elite Fitness Gym
  - Parc Belvedère
  - Yoga Studios
  
### Test 2: Cognitive Activity
- **Activity Type**: Cognitive
- **Expected Locations**: Libraries, Schools, Tech Hubs
- **Should See**:
  - Bibliothèque Nationale
  - Institut Supérieur
  - Tech Hub Sfax

### Test 3: Creative Activity
- **Activity Type**: Creative
- **Expected Locations**: Art Studios, Dance Studios
- **Should See**:
  - Studio Salsateca
  - Studio D'Art

### Test 4: Social Activity
- **Activity Type**: Social
- **Expected Locations**: Everything (most flexible)
- **Should See**: All 13 venues

### Test 5: Skip Location
- Create activity → Location page
- Click **"⏭️ Continuer sans lieu"**
- Activity should be created with empty location field

## 🐛 Troubleshooting

### Problem: Map Not Showing
**Solution:**
- Check browser console (F12) for errors
- Verify Leaflet library loaded: `https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/`
- Clear browser cache and refresh

### Problem: No Locations Showing
**Solution:**
- Check that `/public/data/locations.json` exists
- Verify JSON is valid: `php -r "json_decode(file_get_contents('public/data/locations.json'));"`
- Ensure activity type has matching locations

### Problem: Locations Not Filtering by Type
**Solution:**
- Check activity type is set correctly
- Verify `activityTypes` in location JSON includes that type
- Reload page

### Problem: Location Not Being Saved
**Solution:**
- Check form submit is working (use F12 network tab)
- Verify location name matches exactly in JSON
- Check database permissions

## ✅ Success Criteria

When working correctly you should:
- ✅ See map load with Tunis centered
- ✅ See location markers on map
- ✅ See filtered locations in sidebar
- ✅ Click location and see details
- ✅ Confirm location saves to activity
- ✅ Location appears in activities list

## 📱 Responsive Design

The feature is responsive:
- **Desktop**: 2-column layout (map + sidebar)
- **Tablet**: 1-column stacked layout
- **Mobile**: Full-width elements, scrollable content

## 🔗 Related Pages

- Activity creation: `/admin/activities/new`
- Activities list: `/admin/activities`
- Location APIs: `/api/locations/by-type/{type}`
- Documentation: `LOCATION_MAP_FEATURE.md`

## 💡 Tips

1. **Dark Activity Types**: Physical activities → Gyms, Parks
2. **Cultural Activities**: Creative → Art studios, Dance halls
3. **Learning**: Cognitive → Schools, Libraries, Tech hubs
4. **Flexible**: Social activities accept any venue type
5. **Map Interaction**: Scroll to zoom, drag to pan, click markers

## 📊 Activity Type Icons on Map

- 🧘 Yoga Studios
- 💃 Dance Halls
- 🏋️ Gyms
- 📚 Libraries
- 💻 Tech Hubs
- 🎓 Schools
- 🌳 Parks
- 🏢 Community Centers
- 🎭 Auditoriums
- 🏊 Pools
- 🎨 Art Studios

---

**Ready to Test?** Go to `/admin/activities/new` and create your first activity! 🎉
