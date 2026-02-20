import mysql.connector

conn = mysql.connector.connect(host='127.0.0.1', user='root', password='', database='wannasni', port=3306)
cursor = conn.cursor(dictionary=True)

# Check user 600's records on activity 12
cursor.execute('SELECT id, activity_id, senior_id, status, registered_at FROM participations WHERE senior_id=600 AND activity_id=12 ORDER BY registered_at DESC')
rows = cursor.fetchall()
print(f'Total records for User 600 on Activity 12: {len(rows)}')
for row in rows:
    print(f"  ID: {row['id']}, Status: {row['status']}, Registered: {row['registered_at']}")

print("\n" + "="*60 + "\n")

# Check all active participants for activity 12
cursor.execute('SELECT p.id, p.senior_id, p.status, p.registered_at FROM participations p WHERE p.activity_id=12 AND p.status="présent" ORDER BY registered_at DESC')
active_rows = cursor.fetchall()
print(f'Active participants for Activity 12 (status=présent): {len(active_rows)}')
for row in active_rows:
    print(f"  User {row['senior_id']}: Participation ID {row['id']}, Registered: {row['registered_at']}")

print("\n" + "="*60 + "\n")

# Check activity spot count
cursor.execute('SELECT id, title, current_participants, max_participants FROM activites WHERE id=12')
activity = cursor.fetchone()
if activity:
    print(f"Activity 12: {activity['title']}")
    print(f"  Current Participants: {activity['current_participants']}")
    print(f"  Max Participants: {activity['max_participants']}")
    print(f"  Expected (from DB count): {len(active_rows)}")
    print(f"  Difference: {activity['current_participants'] - len(active_rows)}")

cursor.close()
conn.close()
