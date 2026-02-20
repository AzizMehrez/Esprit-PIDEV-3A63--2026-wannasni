import mysql.connector

conn = mysql.connector.connect(host='127.0.0.1', user='root', password='', database='wannasni', port=3306)
cursor = conn.cursor(dictionary=True)

# Check Activity 12's history: what does max_participants say?
# And how many times has it been joined/cancelled?
cursor.execute('''
SELECT COUNT(*) as total_joins 
FROM participations 
WHERE activity_id = 12
''')
total_joins = cursor.fetchone()['total_joins']

cursor.execute('''
SELECT COUNT(*) as active_count 
FROM participations 
WHERE activity_id = 12 AND status = 'présent'
''')
active_count = cursor.fetchone()['active_count']

cursor.execute('''
SELECT current_participants, max_participants 
FROM activites 
WHERE id = 12
''')
activity = cursor.fetchone()

print(f"Activity 12 Analysis:")
print(f"  Table count (current_participants): {activity['current_participants']}")
print(f"  Max seats: {activity['max_participants']}")
print(f"  Active joins (status='présent'): {active_count}")
print(f"  Total records (active+cancelled): {total_joins}")
print(f"  Missing from counter: {active_count - activity['current_participants']}")

print("\n--- Possible Causes ---")
print("1. User 1's join didn't increment (no timestamp suggests legacy/test data)")
print("2. One of the users 99/200/300/400 joined but counter wasn't incremented")
print("3. Someone cancelled but counter was decremented even though they didn't join")

# Let's manually fix it
print("\n--- FIX: Resync counter to actual active participants ---")
correct_count = active_count
print(f"Setting current_participants = {correct_count} for Activity 12")

cursor.execute("UPDATE activites SET current_participants = %s WHERE id = 12", (correct_count,))
conn.commit()

cursor.execute("SELECT current_participants FROM activites WHERE id = 12")
new_count = cursor.fetchone()['current_participants']
print(f"✓ Updated! New counter: {new_count}")

cursor.close()
conn.close()
