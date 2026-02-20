#!/usr/bin/env python3
import mysql.connector

conn = mysql.connector.connect(
    user='root',
    password='',
    host='127.0.0.1',
    database='wannasni',
    port=3306
)

cursor = conn.cursor()
print("=== Participations Table Structure ===")
cursor.execute("SHOW COLUMNS FROM participations")
for row in cursor:
    print(f"{row[0]}: {row[1]}")

print("\n=== Sample Data from Participations ===")
cursor.execute("SELECT * FROM participations LIMIT 3")
print(f"Columns: {[desc[0] for desc in cursor.description]}")
for row in cursor:
    print(row)

cursor.close()
conn.close()
