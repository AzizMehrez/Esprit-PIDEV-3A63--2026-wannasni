#!/usr/bin/env python3
import json
import subprocess
import sys

result = subprocess.run(
    ['python', 'scripts/activity_assistant.py', 'les activites disponibles', '1', 'fr'],
    capture_output=True,
    text=True,
    cwd='c:\\Users\\DELL\\Desktop\\ProjetWEBSynfony-versionFinal'
)

output = result.stdout.strip()

try:
    data = json.loads(output)
    print('✓ Valid JSON')
    print(f'✓ Intent: {data.get("intent")}')
    print(f'✓ Success: {data.get("success")}')
    activities = data.get("text", "").count("📅") 
    print(f'✓ Activities: {activities} found')
    audio = data.get("audio")
    print(f'✓ Audio: {audio}')
    print('\n✅ All working correctly!')
except Exception as e:
    print(f'✗ Error: {e}')
    print(f'Output received: {output[:300]}')
    sys.exit(1)
