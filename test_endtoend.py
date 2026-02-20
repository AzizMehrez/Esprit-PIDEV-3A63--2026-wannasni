#!/usr/bin/env python3
"""
Final End-to-End Test: Complete user journey
1. List available activities
2. Join activity
3. View my activities
4. Cancel activity
5. Verify removed from my activities
"""
import subprocess
import json
import mysql.connector
import sys

def run_voice_command(text, user_id, expected_success=True):
    """Run command and return response"""
    result = subprocess.run(
        [sys.executable, 'scripts/activity_assistant.py', text, str(user_id)],
        capture_output=True,
        text=True,
        timeout=10,
        encoding='utf-8',
        errors='replace'
    )
    try:
        resp = json.loads(result.stdout)
        status = "✓" if resp.get('success') else "✗"
        return {
            'success': resp.get('success'),
            'text': resp.get('text', ''),
            'intent': resp.get('intent', ''),
            'status': status
        }
    except:
        return {
            'success': False,
            'text': f'Parse error: {result.stdout[:50]}',
            'status': '✗'
        }

def get_user_activities(user_id):
    """Get user's current activities"""
    conn = mysql.connector.connect(host='127.0.0.1', user='root', password='', database='wannasni', port=3306)
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""SELECT a.title, p.status FROM participations p
                      JOIN activites a ON p.activity_id = a.id
                      WHERE p.senior_id = %s AND p.status = 'présent'""", (user_id,))
    activities = [r['title'] for r in cursor.fetchall()]
    cursor.close()
    conn.close()
    return activities

TEST_USER = 7777
TEST_ACTIVITY = "dance"

print("=" * 80)
print("END-TO-END USER JOURNEY TEST")
print("=" * 80)
print()

# Step 1: Show available
print("Step 1: List Available Activities")
resp = run_voice_command("What activities are available?", TEST_USER)
print(f"  {resp['status']} {resp['text'][:70]}")
print()

# Step 2: Join activity
print("Step 2: Join Activity")
resp = run_voice_command(f"Join {TEST_ACTIVITY}", TEST_USER)
print(f"  {resp['status']} {resp['text'][:70]}")
activities = get_user_activities(TEST_USER)
print(f"  User's activities: {activities}")
print()

# Step 3: View my activities
print("Step 3: View My Activities")
resp = run_voice_command("What activities am I in?", TEST_USER)
print(f"  {resp['status']} {resp['text'][:70]}")
print()

# Step 4: Try to join again (should fail)
print("Step 4: Try to Join Same Activity Again (should fail)")
resp = run_voice_command(f"Join {TEST_ACTIVITY}", TEST_USER)
print(f"  {resp['status']} {resp['text'][:70]}")
print()

# Step 5: Cancel activity
print("Step 5: Cancel Activity")
resp = run_voice_command(f"Leave {TEST_ACTIVITY}", TEST_USER)
print(f"  {resp['status']} {resp['text'][:70]}")
print()

# Step 6: Verify removed
print("Step 6: Verify Removed from Activities")
activities = get_user_activities(TEST_USER)
if len(activities) == 0:
    print(f"  ✓ User no longer in any activities")
else:
    print(f"  ✗ User still in: {activities}")
print()

# Step 7: Try to cancel again (should fail)
print("Step 7: Try to Cancel Again (should fail)")
resp = run_voice_command(f"Leave {TEST_ACTIVITY}", TEST_USER)
print(f"  {resp['status']} {resp['text'][:70]}")
print()

print("=" * 80)
print("✅ END-TO-END JOURNEY COMPLETE")
print("=" * 80)
