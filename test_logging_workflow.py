#!/usr/bin/env python3
"""
Test join/cancel workflow with detailed logging capture
"""
import subprocess
import json
import mysql.connector
import sys

def run_voice_command(text, user_id):
    """Run a voice command and capture both stdout and stderr"""
    try:
        result = subprocess.run(
            [sys.executable, 'scripts/activity_assistant.py', text, str(user_id)],
            capture_output=True,
            text=True,
            timeout=10,
            encoding='utf-8',
            errors='replace'
        )
        return {
            'stdout': result.stdout,
            'stderr': result.stderr,
            'returncode': result.returncode
        }
    except Exception as e:
        return {
            'stdout': '',
            'stderr': str(e),
            'error': str(e),
            'returncode': -1
        }

def get_activity_state(activity_id):
    """Get current participant count for an activity"""
    conn = mysql.connector.connect(host='127.0.0.1', user='root', password='', database='wannasni', port=3306)
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT current_participants, max_participants FROM activites WHERE id = %s", (activity_id,))
    result = cursor.fetchone()
    cursor.close()
    conn.close()
    return result

def get_user_joinings(user_id, activity_id):
    """Get user's participation status for a specific activity"""
    conn = mysql.connector.connect(host='127.0.0.1', user='root', password='', database='wannasni', port=3306)
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT id, status FROM participations WHERE senior_id = %s AND activity_id = %s", (user_id, activity_id))
    result = cursor.fetchall()
    cursor.close()
    conn.close()
    return result

# Test with a new user to avoid conflicts
TEST_USER = 8888
TEST_ACTIVITY = 12  # dance

print("=" * 80)
print("COMPREHENSIVE JOIN/CANCEL TEST WITH DEBUG LOGGING")
print("=" * 80)
print(f"Test User: {TEST_USER}, Activity: {TEST_ACTIVITY} (dance)")
print()

# Initial state
state = get_activity_state(TEST_ACTIVITY)
print(f"Initial Activity State: {state['current_participants']}/{state['max_participants']} participants")
print()

# STEP 1: Join
print("STEP 1: Join Activity")
print("-" * 80)
result = run_voice_command(f"Join dance", TEST_USER)
print("COMMAND OUTPUT:")
print(f"  Text: {result['stdout'][:200]}")
print()
print("DEBUG LOGS (stderr):")
for line in result['stderr'].split('\n')[:20]:
    if line.strip():
        print(f"  {line}")
print()

# Check state after join
state_after_join = get_activity_state(TEST_ACTIVITY)
joins = get_user_joinings(TEST_USER, TEST_ACTIVITY)
print(f"After Join: Activity = {state_after_join['current_participants']}/{state_after_join['max_participants']}")
print(f"User Joins: {[(j['id'], j['status']) for j in joins]}")
print()

# STEP 2: Cancel
print("STEP 2: Cancel Activity")
print("-" * 80)
result = run_voice_command(f"Leave dance", TEST_USER)
print("COMMAND OUTPUT:")
print(f"  Text: {result['stdout'][:200]}")
print()
print("DEBUG LOGS (stderr):")
for line in result['stderr'].split('\n')[:30]:
    if line.strip():
        print(f"  {line}")
print()

# Check state after cancel
state_after_cancel = get_activity_state(TEST_ACTIVITY)
joins_after = get_user_joinings(TEST_USER, TEST_ACTIVITY)
print(f"After Cancel: Activity = {state_after_cancel['current_participants']}/{state_after_cancel['max_participants']}")
print(f"User Joins: {[(j['id'], j['status']) for j in joins_after]}")
print()

# Verify results
print("=" * 80)
print("VERIFICATION")
print("=" * 80)
expected_after_join = state['current_participants'] + 1
expected_after_cancel = state['current_participants']

if state_after_join['current_participants'] == expected_after_join:
    print(f"✓ Join counter incremented correctly")
else:
    print(f"✗ Join counter FAILED: expected {expected_after_join}, got {state_after_join['current_participants']}")

if state_after_cancel['current_participants'] == expected_after_cancel:
    print(f"✓ Cancel counter decremented correctly")
else:
    print(f"✗ Cancel counter FAILED: expected {expected_after_cancel}, got {state_after_cancel['current_participants']}")

active_joins = [j for j in joins_after if j['status'] == 'présent']
if len(active_joins) == 0:
    print(f"✓ User participation properly cancelled")
else:
    print(f"✗ Participation FAILED to cancel: {active_joins}")
