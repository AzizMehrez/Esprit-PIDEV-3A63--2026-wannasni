#!/usr/bin/env python3
import mysql.connector

conn = mysql.connector.connect(
    user='root', password='', host='127.0.0.1',
    database='wannasni', port=3306
)
cursor = conn.cursor(dictionary=True)

# Get user 600's participation
cursor.execute('SELECT * FROM participations WHERE senior_id = 600 ORDER BY registered_at DESC LIMIT 1')
p = cursor.fetchone()
if p:
    print('User 600 last join:')
    aid = p['activity_id']
    print(f'  Activity ID: {aid}')
    print(f'  Status: {p["status"]}')
    print(f'  Registered: {p["registered_at"]}')
    
    # Check activity
    cursor.execute('SELECT title FROM activites WHERE id = %s' % aid)
    act = cursor.fetchone()
    if act:
        print(f'  Activity: {act["title"]}')

cursor.close()
conn.close()
