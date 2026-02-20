#!/usr/bin/env python3
"""
Test join/cancel workflow with detailed logging
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
            timeout=10
        )
        return {
            'stdout': result.stdout,
            'stderr': result.stderr,
            'returncode': result.returncode
        }
    except Exception as e:
        return {
            'error': str(e),
            'stderr': str(e)
        }

def get_activity_count(activity_id):
    """Get current participant count for an activity"""
    conn = mysql.connector.connect(host='127.0.0.1', user='root', password='', database='wannasni', port=3306)
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT current_participants FROM activites WHERE id = %s", (activity_id,))
    result = cursor.fetchone()
    cursor.close()
    conn.close()
    return result['current_participants'] if result else None

def get_user_activities(user_id):
    """Get activities user is currently joined to"""
    conn = mysql.connector.connect(host='127.0.0.1', user='root', password='', database='wannasni', port=3306)
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""SELECT a.title, p.status FROM participations p 
                      JOIN activites a ON p.activity_id = a.id 
                      WHERE p.senior_id = %s ORDER BY p.id DESC""", (user_id,))
    activities = cursor.fetchall()
    cursor.close()
    conn.close()
    return activities

# Just a temporary placeholder that's supposed to be replaced
conn = mysql.connector.connect(
        user='root', password='', host='127.0.0.1', 
        database='wannasni', port=3306
    )
    cursor = conn.cursor(dictionary=True)
    cursor.execute(sql, params or ())
    results = cursor.fetchall()
    cursor.close()
    conn.close()
    return results

def test_join_workflow():
    USER_ID = 300
    
    # Clean up first
    print("🧹 Cleaning up user 300...")
    conn = mysql.connector.connect(
        user='root', password='', host='127.0.0.1',
        database='wannasni', port=3306
    )
    cursor = conn.cursor()
    cursor.execute('DELETE FROM participations WHERE senior_id = %s', (USER_ID,))
    conn.commit()
    cursor.close()
    conn.close()
    
    print("\n📊 INITIAL STATE:")
    activities = query_db('SELECT id, title, current_participants, max_participants FROM activites WHERE is_active = 1 LIMIT 1')
    test_activity = activities[0] if activities else None
    if test_activity:
        print(f"  Activity: {test_activity['title']} (ID: {test_activity['id']})")
        print(f"  Spots before join: {test_activity['max_participants'] - test_activity['current_participants']}")
    
    print("\n1️⃣ ATTEMPTING TO JOIN...")
    result = subprocess.run(
        ['python', 'scripts/activity_assistant.py', 'join dance', str(USER_ID), 'en'],
        capture_output=True, text=True
    )
    
    print(f"  Exit code: {result.returncode}")
    if result.stderr:
        print(f"  Stderr: {result.stderr[:200]}")
    
    try:
        response = json.loads(result.stdout)
        print(f"  ✓ Valid JSON returned")
        print(f"  - Intent: {response.get('intent')}")
        print(f"  - Text: {response.get('text', '')[:80]}")
        print(f"  - Audio: {response.get('audio')}")
        print(f"  - Action: {response.get('action')}")
        print(f"  - Success: {response.get('success')}")
    except json.JSONDecodeError as e:
        print(f"  ✗ Invalid JSON: {e}")
        print(f"  Output: {result.stdout[:200]}")
        return
    
    print("\n2️⃣ CHECKING DATABASE AFTER JOIN:")
    participations = query_db('SELECT * FROM participations WHERE senior_id = %s', (USER_ID,))
    print(f"  Participation records: {len(participations)}")
    for p in participations:
        print(f"    - Activity ID: {p['activity_id']}, Status: {p['status']}")
    
    if test_activity:
        activities_after = query_db('SELECT current_participants FROM activites WHERE id = %s', (test_activity['id'],))
        if activities_after:
            spots_after = test_activity['max_participants'] - activities_after[0]['current_participants']
            print(f"  Spots after join: {spots_after}")
    
    print("\n3️⃣ CHECKING MY ACTIVITIES QUERY:")
    result2 = subprocess.run(
        ['python', 'scripts/activity_assistant.py', 'what am i in', str(USER_ID), 'en'],
        capture_output=True, text=True
    )
    
    try:
        response2 = json.loads(result2.stdout)
        print(f"  ✓ My activities returned")
        my_text = response2.get('text', '')
        if 'dance' in my_text.lower():
            print(f"  ✓ Dance activity appears in list")
        else:
            print(f"  ✗ Dance activity NOT in list")
            print(f"  Text: {my_text[:200]}")
    except json.JSONDecodeError as e:
        print(f"  ✗ Invalid JSON: {e}")
    
    print("\n4️⃣ ATTEMPTING TO JOIN AGAIN (should say already joined):")
    result3 = subprocess.run(
        ['python', 'scripts/activity_assistant.py', 'join dance', str(USER_ID), 'en'],
        capture_output=True, text=True
    )
    
    try:
        response3 = json.loads(result3.stdout)
        text = response3.get('text', '')
        if 'already' in text.lower() or 'already joined' in text.lower():
            print(f"  ✓ Correctly says already joined")
        else:
            print(f"  ? Response: {text[:100]}")
    except json.JSONDecodeError:
        print(f"  ✗ Invalid JSON")

if __name__ == '__main__':
    test_join_workflow()
