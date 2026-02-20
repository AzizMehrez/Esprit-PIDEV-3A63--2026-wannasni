#!/usr/bin/env python3
import mysql.connector

conn = mysql.connector.connect(
    user='root', password='', host='127.0.0.1',
    database='wannasni', port=3306
)
cursor = conn.cursor(dictionary=True)

print('=== RECENT PARTICIPATIONS ===')
cursor.execute('''
    SELECT senior_id, activity_id, status, registered_at 
    FROM participations 
    ORDER BY registered_at DESC 
    LIMIT 30
''')
for row in cursor.fetchall():
    print(f"User {row['senior_id']}: Activity {row['activity_id']}, Status: {row['status']}, Time: {row['registered_at']}")

print()
print('=== ACTIVITIES - SPOT COUNTS ===')
cursor.execute('''
    SELECT id, title, max_participants, current_participants 
    FROM activites 
    ORDER BY id
''')
for row in cursor.fetchall():
    available = row['max_participants'] - row['current_participants']
    print(f"Activity {row['id']}: {row['title']}")
    print(f"  Spots: {available} left ({row['current_participants']}/{row['max_participants']})")

print()
print('=== DUPLICATE JOINS CHECK ===')
cursor.execute('''
    SELECT senior_id, activity_id, COUNT(*) as count 
    FROM participations 
    WHERE status IN ('présent', 'registered', 'inscrit')
    GROUP BY senior_id, activity_id 
    HAVING count > 1
''')
duplicates = cursor.fetchall()
if duplicates:
    print(f'Found {len(duplicates)} duplicate active joins:')
    for row in duplicates:
        print(f'  User {row["senior_id"]} joined Activity {row["activity_id"]} {row["count"]} times!')
else:
    print('No duplicates found')

cursor.close()
conn.close()
