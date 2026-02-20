#!/usr/bin/env python3
import mysql.connector

conn = mysql.connector.connect(
    user='root',
    password='',
    host='127.0.0.1',
    database='wannasni',
    port=3306
)

cursor = conn.cursor(dictionary=True)

# Check user 102's participations
print('=== User 102 Participations ===')
cursor.execute('SELECT activity_id, status, registered_at FROM participations WHERE senior_id = 102')
for row in cursor.fetchall():
    print(f'Activity ID: {row["activity_id"]}, Status: "{row["status"]}", Registered: {row["registered_at"]}')

print()
print('=== Checking status values in table ===')
cursor.execute('SELECT DISTINCT status FROM participations')
for row in cursor.fetchall():
    print(f'Status: "{row["status"]}"')

print()
print('=== Checking if 102 joined machine learning (id=11) ===')
cursor.execute('SELECT * FROM participations WHERE senior_id = 102 AND activity_id = 11')
result = cursor.fetchone()
if result:
    print(f'✓ Found: Status = "{result["status"]}"')
else:
    print('✗ Not found')

cursor.close()
conn.close()
