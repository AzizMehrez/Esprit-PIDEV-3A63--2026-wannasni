#!/usr/bin/env python3
"""
Test dashboard data retrieval
"""
import mysql.connector

conn = mysql.connector.connect(
    host='127.0.0.1',
    user='root',
    password='',
    database='wannasni',
    port=3306
)
cursor = conn.cursor(dictionary=True)

# Test 1: Get user activities (assuming user_id = 1 or first user)
print("=" * 80)
print("Test 1: User Activities (10 most recent joined)")
print("=" * 80)

cursor.execute("""
    SELECT 
        a.id, a.title, a.type, a.start_time, a.location,
        p.status, p.registered_at
    FROM participations p
    JOIN activites a ON p.activity_id = a.id
    WHERE p.status IN ('présent', 'registered', 'inscrit')
    ORDER BY p.registered_at DESC
    LIMIT 10
""")

user_activities = cursor.fetchall()
print(f"Found {len(user_activities)} user activities joined\n")
for act in user_activities[:3]:
    print(f"  • {act['title']} (ID: {act['id']})")
    print(f"    Status: {act['status']}, Time: {act['start_time']}")

# Test 2: Get all activities with real participant counts
print("\n" + "=" * 80)
print("Test 2: All Active Activities with Participant Counts")
print("=" * 80)

cursor.execute("""
    SELECT 
        a.id, a.title, a.type, a.max_participants, a.current_participants,
        COUNT(p.id) as actual_count
    FROM activites a
    LEFT JOIN participations p ON a.id = p.activity_id AND p.status IN ('présent', 'registered', 'inscrit')
    WHERE a.is_active = 1
    GROUP BY a.id
    ORDER BY a.id
""")

all_activities = cursor.fetchall()
print(f"\nFound {len(all_activities)} active activities\n")
for act in all_activities:
    print(f"  • {act['title']} (ID: {act['id']})")
    print(f"    Type: {act['type']}")
    print(f"    Counter: {act['current_participants']} | Actual: {act['actual_count']} | Max: {act['max_participants']}")
    print(f"    Match: {'✓' if act['current_participants'] == act['actual_count'] else '✗ MISMATCH'}\n")

# Test 3: Get recent participations
print("=" * 80)
print("Test 3: Recent Joins/Cancellations (Last 10)")
print("=" * 80)

cursor.execute("""
    SELECT 
        p.id, p.activity_id, p.senior_id, p.status, p.title, p.registered_at,
        u.first_name, u.last_name
    FROM participations p
    JOIN user u ON p.senior_id = u.id
    ORDER BY p.registered_at DESC
    LIMIT 10
""")

recent = cursor.fetchall()
print(f"\nFound {len(recent)} recent activities\n")
for act in recent:
    user_name = f"{act['first_name']} {act['last_name']}"
    action = 'JOINED' if act['status'] in ('présent', 'registered', 'inscrit') else 'CANCELLED'
    print(f"  • {user_name} {action} '{act['title']}'")
    print(f"    Time: {act['registered_at']}\n")

cursor.close()
conn.close()

print("✅ Dashboard data retrieval test complete!")
