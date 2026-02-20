import mysql.connector

conn = mysql.connector.connect(host='127.0.0.1', user='root', password='', database='wannasni', port=3306)
cursor = conn.cursor(dictionary=True)

# Check ALL participations for activity 12 - full history
cursor.execute('SELECT id, senior_id, status, registered_at FROM participations WHERE activity_id=12 ORDER BY registered_at ASC')
all_rows = cursor.fetchall()
print(f'ALL participation records for Activity 12 (total {len(all_rows)}):')
print(f"{'ID':<5} {'User':<6} {'Status':<10} {'Registered':<25}")
print("-" * 50)
for row in all_rows:
    print(f"{row['id']:<5} {row['senior_id']:<6} {row['status']:<10} {str(row['registered_at']):<25}")

print("\n" + "="*60 + "\n")

# Count by status
cursor.execute('SELECT status, COUNT(*) as count FROM participations WHERE activity_id=12 GROUP BY status')
status_counts = cursor.fetchall()
print('Participation breakdown by status:')
for sc in status_counts:
    print(f"  {sc['status']}: {sc['count']}")

print("\n" + "="*60 + "\n")

# Activity current state
cursor.execute('SELECT id, title, current_participants, max_participants FROM activites WHERE id=12')
activity = cursor.fetchone()
if activity:
    print(f"Activity 12 Counter: {activity['current_participants']}/20")
    print(f"Expected based on 'présent' status: {sum(1 for r in all_rows if r['status'] == 'présent')}")
    print(f"Expected based on NOT 'annulé': {sum(1 for r in all_rows if r['status'] != 'annulé')}")

cursor.close()
conn.close()
