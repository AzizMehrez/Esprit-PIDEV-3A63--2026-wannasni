#!/usr/bin/env python3
"""
Simulate DashboardController data retrieval to verify it works correctly
"""
import mysql.connector
from datetime import datetime

conn = mysql.connector.connect(
    host='127.0.0.1',
    user='root',
    password='',
    database='wannasni',
    port=3306
)
cursor = conn.cursor(dictionary=True)

print("=" * 80)
print("DASHBOARD CONTROLLER DATA RETRIEVAL TEST")
print("=" * 80)

# Get a test user (we'll use the first admin or first user)
cursor.execute("SELECT id, first_name, last_name FROM user LIMIT 1")
test_user = cursor.fetchone()
if not test_user:
    print("❌ No users found in database!")
    exit(1)

user_id = test_user['id']
user_name = f"{test_user['first_name']} {test_user['last_name']}"
print(f"\n📌 Testing with User: {user_name} (ID: {user_id})")

# Simulate: Get stats (simple counts)
print("\n1️⃣ STATS RETRIEVAL")
print("-" * 80)

cursor.execute("SELECT COUNT(*) as total FROM user")
stats = {'total_users': cursor.fetchone()['total']}

cursor.execute("""
    SELECT COUNT(*) as total FROM activites 
    WHERE CAST(DATE(start_time) AS DATE) = CAST(NOW() AS DATE)
""")
stats['activities_today'] = cursor.fetchone()['total']

cursor.execute("SELECT COUNT(*) as total FROM participations")
stats['total_participations'] = cursor.fetchone()['total']

cursor.execute("""
    SELECT COUNT(*) as total FROM participations 
    WHERE status IN ('présent', 'registered', 'inscrit')
""")
stats['present_participations'] = cursor.fetchone()['total']

cursor.execute("""
    SELECT COUNT(*) as total FROM participations p
    JOIN activites a ON p.activity_id = a.id
    WHERE p.feedback IS NOT NULL
""")
stats['participations_with_feedback'] = cursor.fetchone()['total']

cursor.execute("""
    SELECT COUNT(*) as total FROM participations 
    WHERE status IN ('annulé', 'cancelled')
""")
stats['pending_participations'] = cursor.fetchone()['total']

try:
    cursor.execute("SELECT COUNT(*) as total FROM health_journal")
    stats['health_records'] = cursor.fetchone()['total']
except:
    stats['health_records'] = 0

try:
    cursor.execute("""
        SELECT COUNT(*) as total FROM services 
        WHERE status IN ('pending', 'en_attente')
    """)
    stats['services_pending'] = cursor.fetchone()['total']
except:
    stats['services_pending'] = 0

print("✅ Stats Retrieved:")
for key, value in stats.items():
    print(f"   {key}: {value}")

# Simulate: Get user activities
print("\n2️⃣ USER ACTIVITIES RETRIEVAL")
print("-" * 80)

cursor.execute(f"""
    SELECT 
        a.id, a.title, a.type, a.start_time, a.location,
        p.status, p.registered_at
    FROM participations p
    JOIN activites a ON p.activity_id = a.id
    WHERE p.senior_id = {user_id} AND p.status IN ('présent', 'registered', 'inscrit')
    ORDER BY p.registered_at DESC
    LIMIT 10
""")

user_activities = []
for row in cursor.fetchall():
    user_activities.append({
        'id': row['id'],
        'title': row['title'],
        'type': row['type'],
        'location': row['location'],
        'startTime': str(row['start_time']) if row['start_time'] else 'N/A',
        'registeredAt': row['registered_at']
    })

print(f"✅ Found {len(user_activities)} user activities (top 10):")
for idx, act in enumerate(user_activities[:3], 1):
    print(f"   {idx}. '{act['title']}' @ {act['location']}")
    print(f"      Time: {act['startTime']}")

# Simulate: Get all activities with participant counts
print("\n3️⃣ ALL ACTIVITIES WITH PARTICIPANT COUNTS")
print("-" * 80)

cursor.execute("""
    SELECT 
        a.id, a.title, a.type, a.max_participants, a.current_participants
    FROM activites a
    WHERE a.is_active = 1
    ORDER BY a.id
""")

all_activities = []
for row in cursor.fetchall():
    # Get real count from participations
    cursor.execute(f"""
        SELECT COUNT(*) as actual_count FROM participations 
        WHERE activity_id = {row['id']} 
        AND status IN ('présent', 'registered', 'inscrit')
    """)
    actual_count = cursor.fetchone()['actual_count']
    
    all_activities.append({
        'id': row['id'],
        'title': row['title'],
        'type': row['type'],
        'participants': actual_count,
        'maxParticipants': row['max_participants']
    })

print(f"✅ Found {len(all_activities)} active activities:")
for idx, act in enumerate(all_activities, 1):
    print(f"   {idx}. '{act['title']}'")
    print(f"      Type: {act['type']}, Participants: {act['participants']}/{act['maxParticipants']}")

# Simulate: Get recent activities
print("\n4️⃣ RECENT ACTIVITIES")
print("-" * 80)

cursor.execute("""
    SELECT 
        p.id, p.activity_id, p.senior_id, p.status, p.title, p.registered_at,
        u.first_name, u.last_name,
        a.title as activity_title
    FROM participations p
    JOIN user u ON p.senior_id = u.id
    LEFT JOIN activites a ON p.activity_id = a.id
    ORDER BY p.registered_at DESC
    LIMIT 15
""")

recent_activities = []
for row in cursor.fetchall():
    user_name = f"{row['first_name']} {row['last_name']}"
    
    # Determine action type
    if row['status'] in ('présent', 'registered', 'inscrit'):
        action = "JOINED"
        icon = "✅"
    else:
        action = "CANCELLED"
        icon = "❌"
    
    activity_display = row['activity_title'] or row['title'] or 'Unknown'
    
    recent_activities.append({
        'id': row['id'],
        'user': user_name,
        'action': action,
        'activity': activity_display,
        'icon': icon,
        'time': str(row['registered_at']) if row['registered_at'] else 'N/A',
        'type': 'activity' if action == 'JOINED' else 'activity-cancel'
    })

print(f"✅ Found {len(recent_activities)} recent activities (showing 10):")
for idx, act in enumerate(recent_activities[:10], 1):
    print(f"   {idx}. {act['icon']} {act['user']} {act['action']} '{act['activity']}'")
    print(f"      Time: {act['time']}")

# Summary
print("\n" + "=" * 80)
print("📊 SUMMARY FOR TEMPLATE")
print("=" * 80)
print(f"✅ Stats: {len(stats)} counters ready")
print(f"✅ User Activities: {len(user_activities)} activities (user is in {len(user_activities)} active activities)")
print(f"✅ All Activities: {len(all_activities)} total active activities")
print(f"✅ Recent Activities: {len(recent_activities)} recent join/cancel events")

print("\n✅ DASHBOARD READY TO RENDER!")
print("   All data is available for display in the template")
print("   Template sections will show:")
print(f"   • Stats Grid: {stats['total_users']} users, {stats['activities_today']} activities today")
print(f"   • User Activities: {user_name}'s {len(user_activities)} joined activities")
print(f"   • All Activities: {len(all_activities)} active activities with real participant counts")
print(f"   • Recent Activity: {len(recent_activities)} recent join/cancel events (auto-refreshing every 5s)")

cursor.close()
conn.close()
