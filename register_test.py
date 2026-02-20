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
# Register user 1 for activity 13 (test) - they already have 11 and 12
cursor.execute("""
    INSERT INTO participations (senior_id, activity_id, status, registration_date, registered_at)
    VALUES (1, 13, 'présent', NOW(), NOW())
    ON DUPLICATE KEY UPDATE status='présent'
""")
conn.commit()

# Check if user 1 now has all 3 activities
cursor.execute("SELECT activity_id FROM participations WHERE senior_id=1 AND status NOT IN ('annulé', 'cancelled')")
activities = cursor.fetchall()
print(f"User 1 registered for activities: {[row['activity_id'] for row in activities]}")
cursor.close()
conn.close()
