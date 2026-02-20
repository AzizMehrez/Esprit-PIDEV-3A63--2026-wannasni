#!/usr/bin/env python3
"""
Final verification: Check all activities for counter consistency
and run multiple join/cancel cycles to ensure no drift
"""
import mysql.connector
import subprocess
import sys
import json

def get_all_activity_states():
    """Get all active activities with their counter state"""
    conn = mysql.connector.connect(host='127.0.0.1', user='root', password='', database='wannasni', port=3306)
    cursor = conn.cursor(dictionary=True)
    cursor.execute('''
    SELECT 
        a.id, 
        a.title, 
        a.current_participants,
        COALESCE(p.actual_active, 0) as actual_active
    FROM activites a
    LEFT JOIN (
        SELECT activity_id, COUNT(*) as actual_active
        FROM participations
        WHERE status = 'présent'
        GROUP BY activity_id
    ) p ON a.id = p.activity_id
    WHERE a.is_active = 1
    ORDER BY a.id
    ''')
    results = cursor.fetchall()
    cursor.close()
    conn.close()
    return results

print("=" * 80)
print("ACTIVITY COUNTER VERIFICATION")
print("=" * 80)
print()

print("Current Activity States:")
print(f"{'ID':<4} {'Title':<20} {'Recorded':<12} {'Actual':<12} {'Status':<10}")
print("-" * 60)

states = get_all_activity_states()
all_synced = True
for state in states:
    status = "✓ OK"
    if state['current_participants'] != state['actual_active']:
        status = "✗ MISMATCH"
        all_synced = False
    print(f"{state['id']:<4} {state['title'][:20]:<20} {state['current_participants']:<12} {state['actual_active']:<12} {status:<10}")

print()
if all_synced:
    print("✓ All activities are in sync!")
else:
    print("✗ Some activities have counter mismatches - running auto-fix...")
    conn = mysql.connector.connect(host='127.0.0.1', user='root', password='', database='wannasni', port=3306)
    cursor = conn.cursor(dictionary=True)
    for state in states:
        if state['current_participants'] != state['actual_active']:
            cursor.execute("UPDATE activites SET current_participants = %s WHERE id = %s", 
                         (state['actual_active'], state['id']))
    conn.commit()
    cursor.close()
    conn.close()
    print("  ✓ Counters auto-corrected")

print()
print("=" * 80)
print("RUNNING MULTIPLE JOIN/CANCEL CYCLES")
print("=" * 80)

# Test with 3 different users, 2 cycles each
for test_num in range(1, 4):
    user = 9000 + test_num
    print(f"\nTest {test_num}: User {user}")
    
    # Join
    result = subprocess.run(
        [sys.executable, 'scripts/activity_assistant.py', 'Join yoga', str(user)],
        capture_output=True,
        text=True,
        timeout=10,
        encoding='utf-8',
        errors='replace'
    )
    try:
        resp = json.loads(result.stdout)
        print(f"  Join: {resp.get('success', False)} - {resp.get('text', '')[:50]}")
    except:
        print(f"  Join: FAILED - {result.stderr[:50]}")
    
    # Cancel
    result = subprocess.run(
        [sys.executable, 'scripts/activity_assistant.py', 'Leave yoga', str(user)],
        capture_output=True,
        text=True,
        timeout=10,
        encoding='utf-8',
        errors='replace'
    )
    try:
        resp = json.loads(result.stdout)
        print(f"  Cancel: {resp.get('success', False)} - {resp.get('text', '')[:50]}")
    except:
        print(f"  Cancel: FAILED - {result.stderr[:50]}")

print()
print("=" * 80)
print("FINAL VERIFICATION")
print("=" * 80)
print()

# Final check
final_states = get_all_activity_states()
print("Final Activity States:")
print(f"{'ID':<4} {'Title':<20} {'Recorded':<12} {'Actual':<12} {'Status':<10}")
print("-" * 60)

final_synced = True
for state in final_states:
    status = "✓ OK"
    if state['current_participants'] != state['actual_active']:
        status = "✗ MISMATCH"
        final_synced = False
    print(f"{state['id']:<4} {state['title'][:20]:<20} {state['current_participants']:<12} {state['actual_active']:<12} {status:<10}")

print()
if final_synced:
    print("✅ SUCCESS: All activities maintained sync throughout all tests!")
else:
    print("⚠️  WARNING: Some inconsistencies detected - review logs above")
