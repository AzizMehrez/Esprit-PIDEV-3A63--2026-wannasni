import mysql.connector

conn = mysql.connector.connect(host='127.0.0.1', user='root', password='', database='wannasni', port=3306)
cursor = conn.cursor(dictionary=True)

# Check all activities for desync
cursor.execute('''
SELECT 
    a.id, 
    a.title, 
    a.current_participants,
    COALESCE(p.active_count, 0) as actual_active,
    ABS(a.current_participants - COALESCE(p.active_count, 0)) as discrepancy
FROM activites a
LEFT JOIN (
    SELECT activity_id, COUNT(*) as active_count
    FROM participations
    WHERE status = 'présent'
    GROUP BY activity_id
) p ON a.id = p.activity_id
WHERE a.is_active = 1
ORDER BY discrepancy DESC
''')

issues = cursor.fetchall()
print(f"Activity Sync Analysis (Active activities only):\n")
print(f"{'ID':<5} {'Title':<20} {'Counter':<10} {'Actual':<10} {'Diff':<5}")
print("-" * 55)

fixed = 0
for issue in issues:
    diff = issue['discrepancy']
    if diff > 0:
        print(f"{issue['id']:<5} {issue['title'][:20]:<20} {issue['current_participants']:<10} {issue['actual_active']:<10} {diff:<5} ❌")
        # Fix it
        cursor.execute("UPDATE activites SET current_participants = %s WHERE id = %s", (issue['actual_active'], issue['id']))
        conn.commit()
        fixed += 1
    else:
        print(f"{issue['id']:<5} {issue['title'][:20]:<20} {issue['current_participants']:<10} {issue['actual_active']:<10} {diff:<5} ✓")

print(f"\n✓ Fixed {fixed} activity counters")

# Verify fixes
cursor.execute('''
SELECT 
    a.id, 
    a.title, 
    a.current_participants,
    COALESCE(p.active_count, 0) as actual_active,
    ABS(a.current_participants - COALESCE(p.active_count, 0)) as discrepancy
FROM activites a
LEFT JOIN (
    SELECT activity_id, COUNT(*) as active_count
    FROM participations
    WHERE status = 'présent'
    GROUP BY activity_id
) p ON a.id = p.activity_id
WHERE a.is_active = 1
ORDER BY discrepancy DESC
''')

after = cursor.fetchall()
any_broken = [x for x in after if x['discrepancy'] > 0]
if any_broken:
    print(f"\n⚠️  Still broken: {len(any_broken)} activities")
else:
    print(f"\n✓ All active activities now in sync!")

cursor.close()
conn.close()
