#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Voice-Controlled Activity Assistant with Multi-Language Support
Supports: English, French, Arabic
"""
import sys
import json
import os
import mysql.connector
import pyttsx3
import datetime
import uuid
import re
import codecs
from difflib import SequenceMatcher

# Force UTF-8 encoding on all platforms
if sys.stdout.encoding != 'utf-8':
    sys.stdout = codecs.getwriter('utf-8')(sys.stdout.buffer, 'strict')
if sys.stderr.encoding != 'utf-8':
    sys.stderr = codecs.getwriter('utf-8')(sys.stderr.buffer, 'strict')

# --- CONFIGURATION ---
DB_CONFIG = {
    'user': 'root',
    'password': '',
    'host': '127.0.0.1',
    'database': 'wannasni',
    'port': 3306,
    'charset': 'utf8mb4'
}

AUDIO_OUTPUT_DIR = os.path.join("public", "audio", "responses")
os.makedirs(AUDIO_OUTPUT_DIR, exist_ok=True)

FUZZY_THRESHOLD = 0.65

def fuzzy_match(text1, text2):
    """Calculate similarity ratio between two strings"""
    return SequenceMatcher(None, text1.lower(), text2.lower()).ratio()

def get_db_connection():
    return mysql.connector.connect(**DB_CONFIG)

def detect_language(text):
    """Detect language from text"""
    if any('\u0600' <= char <= '\u06FF' for char in text):
        return 'ar'
    
    text_lower = text.lower()
    fr_words = ['le', 'la', 'les', 'des', 'activité', 'activités', 'je', 'voudrais', 
                'peux', 'quoi', 'comment', 'mon', 'mes', 'annul', 'inscri']
    en_words = ['the', 'activities', 'what', 'can', 'i', 'want', 'show', 'tell', 
                'cancel', 'register', 'my', 'me']
    
    fr_score = sum(1 for w in fr_words if w in text_lower)
    en_score = sum(1 for w in en_words if w in text_lower)
    
    return 'fr' if fr_score > en_score else 'en'

def text_to_speech(text, lang='en', output_filename=None):
    """Convert text to speech and save as audio file"""
    try:
        engine = pyttsx3.init()
        engine.setProperty('rate', 130)  # Slower for clarity with seniors
        voices = engine.getProperty('voices')
        
        # Try to match voice to language
        for voice in voices:
            voice_lower = voice.name.lower()
            if (lang == 'fr' and 'french' in voice_lower) or \
               (lang == 'ar' and 'arabic' in voice_lower) or \
               (lang == 'en' and 'english' in voice_lower):
                engine.setProperty('voice', voice.id)
                break
        
        if output_filename is None:
            filename = f"response_{uuid.uuid4().hex[:8]}.mp3"
            output_path = os.path.join(AUDIO_OUTPUT_DIR, filename)
        else:
            output_path = output_filename
            filename = os.path.basename(output_path)
        
        engine.save_to_file(text, output_path)
        engine.runAndWait()
        
        return f"/audio/responses/{filename}"
    except Exception as e:
        print(f"TTS Error: {e}", file=sys.stderr)
        return None

def find_best_activity_match(text, activities):
    """Find the best matching activity using fuzzy string matching"""
    if not activities:
        return None
    
    text_norm = text.lower().strip()
    best_match = None
    best_score = 0
    
    # If text is empty or too short, no match
    if len(text_norm) < 2:
        return None
    
    for activity in activities:
        title_lower = activity.get('title', '').lower()
        
        # Exact match highest priority
        if title_lower == text_norm:
            return activity
        
        # Substring match - both directions
        if text_norm in title_lower or title_lower in text_norm:
            return activity
        
        # Fuzzy match on full title
        score = fuzzy_match(text_norm, title_lower)
        
        # Also check individual meaningful words (but at least 3 chars)
        text_words = [w for w in text_norm.split() if len(w) > 2]
        title_words = [w for w in title_lower.split() if len(w) > 2]
        
        # Check if all significant words in text appear in title
        if text_words:
            for word in text_words:
                for title_word in title_words:
                    if word in title_word or title_word in word:
                        score = max(score, 0.9)  # High score for word presence
                    else:
                        word_score = fuzzy_match(word, title_word)
                        score = max(score, word_score)
        
        if score > best_score:
            best_score = score
            best_match = activity
    
    # Use lower threshold (0.55) for longer search terms, stricter (0.70) for short ones
    effective_threshold = 0.55 if len(text_norm) > 3 else 0.70
    return best_match if best_score >= effective_threshold else None

def extract_intent_and_activity(user_text):
    """Extract intent and activity name from user text"""
    text_norm = user_text.lower().strip()
    
    # Helper function to check if keyword matches (whole words only for short keywords)
    def has_keyword(text, keywords):
        """Check if any keyword appears in text (whole words for short keywords)"""
        words = text.split()
        for keyword in keywords:
            # For short keywords (3 chars or less), match whole words only
            if len(keyword) <= 3:
                if keyword in words:
                    return True
            else:
                # For longer keywords, allow substring matching
                if keyword in text:
                    return True
        return False
    
    def get_matching_keywords(text, keywords):
        """Get list of matching keywords"""
        words = text.split()
        matched = []
        for keyword in keywords:
            if len(keyword) <= 3:
                if keyword in words:
                    matched.append(keyword)
            else:
                if keyword in text:
                    matched.append(keyword)
        return matched
    
    # Intent patterns with multilingual support
    list_keywords = ['show', 'list', 'see', 'view', 'what', 'available', 'activit', 
                     'voir', 'liste', 'montre', 'affiche', 'quoi', 'quel', 'quelles', 'disponible',
                     'اعرض', 'قائمة', 'ماذا', 'نشاط', 'تاني']
    
    join_keywords = ['join', 'enroll', 'register', 'sign', 'participate', 'book', 'add me', 'do',
                     'inscri', 'rejoind', 'particip', 'réserv', 'ajoute', 'fais', 'me up',
                     'انضم', 'سجل', 'اشترك', 'احجز']
    
    cancel_keywords = ['cancel', 'remove', 'unsubscribe', 'leave', 'quit', 'delete', 'drop',
                       'annul', 'supprim', 'désinscrire', 'quitt', 'retire',
                       'الغاء', 'حذف', 'مغادرة', 'اسحب']
    
    my_activities_keywords = ['my', 'my activities', 'i\'m', 'am i', 'registered for', 'enrolled',
                              'mon', 'mes', 'je suis', 'participations', 'inscrit', 'what am i',
                              'أنا', 'أنشطتي', 'مسجل']
    
    greeting_keywords = ['hello', 'hi', 'hey', 'bonjour', 'salut', 'ça va', 'مرحبا', 'السلام']
    
    intent = 'chat'
    activity_name = ''
    
    # Check intents in order of priority, using word-boundary aware matching
    if has_keyword(text_norm, greeting_keywords) and len(text_norm.split()) <= 4:
        intent = 'greeting'
    elif has_keyword(text_norm, my_activities_keywords):
        intent = 'list_my_activities'
    elif has_keyword(text_norm, join_keywords):
        intent = 'join'
        # Extract activity name (usually after the verb)
        for word in join_keywords:
            if len(word) <= 3:
                # For short keywords, check word boundary
                word_list = text_norm.split()
                if word in word_list:
                    idx = text_norm.find(' ' + word + ' ')
                    if idx == -1:
                        idx = text_norm.find(word + ' ')
                    if idx == -1:
                        idx = text_norm.find(' ' + word)
                        if idx >= 0:
                            idx += 1
                    if idx == 0 or idx > 0:
                        activity_name = text_norm[idx + len(word):].strip()
                        break
            else:
                # For longer keywords, use substring
                if word in text_norm:
                    idx = text_norm.find(word)
                    activity_name = text_norm[idx + len(word):].strip()
                    break
    elif has_keyword(text_norm, cancel_keywords):
        intent = 'cancel'
        for word in cancel_keywords:
            if len(word) <= 3:
                word_list = text_norm.split()
                if word in word_list:
                    idx = text_norm.find(' ' + word + ' ')
                    if idx == -1:
                        idx = text_norm.find(word + ' ')
                    if idx == -1:
                        idx = text_norm.find(' ' + word)
                        if idx >= 0:
                            idx += 1
                    if idx == 0 or idx > 0:
                        activity_name = text_norm[idx + len(word):].strip()
                        break
            else:
                if word in text_norm:
                    idx = text_norm.find(word)
                    activity_name = text_norm[idx + len(word):].strip()
                    break
    elif has_keyword(text_norm, list_keywords):
        intent = 'list_available'
    
    return intent, activity_name

def get_smart_response(user_text, user_id, lang='en'):
    """Main intelligent response handler"""
    
    # Response templates
    templates = {
        'en': {
            'greeting': "Hi there! I'm your activity buddy. I can help you find fun activities, join events, or tell you what you're already doing.",
            'list_intro': "Great! Here are some fresh activities for you:",
            'no_activities': "Sorry, there aren't any new activities available right now. Check back soon!",
            'my_activities': "Here's what you're doing:",
            'no_my_activities': "You haven't joined anything yet! Want me to show you what's available?",
            'join_success': "Awesome! You're signed up for {activity}. Have fun!",
            'join_already': "You've already joined {activity}!",
            'no_activity_match': "Hmm, I can't find that activity. Try saying the name differently or ask me what's available.",
            'cancel_success': "Done! You're no longer in {activity}.",
            'cancel_notfound': "You're not in that activity.",
            'help': "I can help! Try: 'What activities are available?', 'Join yoga', 'What am I in?', or 'Leave yoga'"
        },
        'fr': {
            'greeting': "Bonjour! Je suis là pour vous aider avec les activités. Vous pouvez me demander d'afficher les activités, de vous inscrire ou de vous parler des vôtres.",
            'list_intro': "Voici les activités disponibles:",
            'no_activities': "Je n'ai pas trouvé d'activités maintenant. Voulez-vous voir ce qui est disponible?",
            'my_activities': "Vous êtes inscrit aux activités suivantes:",
            'no_my_activities': "Vous n'êtes encore inscrit à aucune activité. Voulez-vous que je vous en montre?",
            'join_success': "Parfait! Je vous ai inscrit à {activity}. Amusez-vous!",
            'join_already': "Vous êtes déjà inscrit à {activity}.",
            'no_activity_match': "Je n'ai pas trouvé cette activité. Pourriez-vous être plus précis?",
            'cancel_success': "J'ai annulé votre inscription à {activity}. Pas de problème!",
            'cancel_notfound': "Vous n'êtes pas inscrit à cette activité.",
            'help': "Je peux vous aider! Essayez: 'Affiche-moi les activités', 'Inscris-moi au yoga', 'Quelles activités ai-je?', ou 'Annule yoga'"
        },
        'ar': {
            'greeting': "مرحبا! أنا هنا لمساعدتك في الأنشطة. يمكنك أن تطلب مني عرض الأنشطة أو التسجيل أو إخبارك عن أنشطتك.",
            'list_intro': "إليك الأنشطة المتاحة:",
            'no_activities': "لم أجد أي أنشطة الآن. هل تريد رؤية ما هو متاح?",
            'my_activities': "أنت مسجل في الأنشطة التالية:",
            'no_my_activities': "أنت لم تسجل في أي نشاط حتى الآن. هل تريد أن أعرض عليك بعضا منها?",
            'join_success': "ممتاز! تم تسجيلك في {activity}. استمتع!",
            'join_already': "أنت مسجل بالفعل في {activity}.",
            'no_activity_match': "لم أتمكن من العثور على هذا النشاط. هل يمكنك أن تكون أكثر تحديدا?",
            'cancel_success': "ألغيت تسجيلك في {activity}. لا مشكلة!",
            'cancel_notfound': "أنت لم تسجل في هذا النشاط.",
            'help': "يمكنني المساعدة! جرب: 'اعرض لي الأنشطة', 'سجلني في اليوجا', 'ما أنشطتي?', أو 'ألغِ اليوجا'"
        }
    }
    
    msg = templates.get(lang, templates['en'])
    
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        print(f"DEBUG: Voice assistant invoked - User {user_id}, Lang {lang}, Text: {user_text[:50]}", file=sys.stderr)
    except Exception as e:
        return {
            "text": "Sorry, I'm having database issues right now. Please try again.",
            "audio": None,
            "error": str(e),
            "success": False
        }
    
    intent, activity_name = extract_intent_and_activity(user_text)
    response_text = ""
    display_text = ""
    audio_url = None
    action_text = ""
    should_refresh = False  # Track if page needs refresh after join/cancel
    
    try:
        if intent == 'greeting':
            response_text = msg['greeting']
            action_text = "Ready to help!"
            display_text = response_text
        
        elif intent == 'list_available':
            # Get available activities that user is NOT already joined and has available spots
            cursor.execute("SELECT id, title, type, start_time, location, current_participants, max_participants, description FROM activites WHERE is_active = 1 AND start_time >= NOW() AND id NOT IN (SELECT activity_id FROM participations WHERE senior_id = %s AND status IN (%s, %s, %s)) AND (max_participants IS NULL OR current_participants < max_participants) ORDER BY start_time ASC LIMIT 8", (user_id, 'présent', 'registered', 'inscrit'))
            activities = cursor.fetchall()
            
            # If no future activities, try fallback with all active activities
            if not activities:
                cursor.execute("SELECT id, title, type, start_time, location, current_participants, max_participants, description FROM activites WHERE is_active = 1 AND id NOT IN (SELECT activity_id FROM participations WHERE senior_id = %s AND status IN (%s, %s, %s)) AND (max_participants IS NULL OR current_participants < max_participants) ORDER BY start_time DESC LIMIT 8", (user_id, 'présent', 'registered', 'inscrit'))
                activities = cursor.fetchall()
            
            display_text = ""
            if activities:
                try:
                    # Create readable voice response
                    if lang == 'fr':
                        response_text = "Voici les activités disponibles qui ne sont pas encore dans votre agenda. "
                    elif lang == 'ar':
                        response_text = "إليك الأنشطة المتاحة التي لم تسجل فيها بعد. "
                    else:
                        response_text = "Here are some great activities you're not yet signed up for. "
                    
                    # Read each activity
                    for i, act in enumerate(activities, 1):
                        try:
                            # Safe date formatting
                            if act['start_time']:
                                time_str = act['start_time'].strftime('%A at %I:%M %p')
                            else:
                                time_str = 'Time TBA'
                            
                            # Safe spots calculation
                            max_p = act.get('max_participants', None)
                            curr_p = act.get('current_participants', 0)
                            spots = max(0, (max_p - curr_p) if max_p else 999)
                            
                            location = act.get('location', 'Location TBA')
                            activity_type = act.get('type', 'activity')
                            
                            if lang == 'fr':
                                activity_text = f"Activité {i}: {act['title']}. C'est une activité {activity_type}, le {time_str} à {location}. Il y a {spots} places disponibles. "
                            elif lang == 'ar':
                                activity_text = f"النشاط {i}: {act['title']}. نشاط {activity_type}, في {time_str} في {location}. هناك {spots} أماكن متاحة. "
                            else:
                                # Better English: Add enthusiasm and variety
                                if activity_type.lower() == 'sport':
                                    activity_text = f"Activity {i}: {act['title']}. This fun {activity_type} activity takes place on {time_str} at {location}. {spots} spots left! "
                                elif activity_type.lower() == 'educational':
                                    activity_text = f"Activity {i}: {act['title']}. Join this enriching {activity_type} session on {time_str} at {location}. Only {spots} spots available! "
                                elif activity_type.lower() == 'social':
                                    activity_text = f"Activity {i}: {act['title']}. A great {activity_type} activity happening on {time_str} at {location}. {spots} spots remaining! "
                                else:
                                    activity_text = f"Activity {i}: {act['title']}. A {activity_type} activity on {time_str} at {location}. {spots} spots available! "
                            
                            response_text += activity_text
                        except Exception as e:
                            print(f"Error processing activity {i}: {e}", file=sys.stderr)
                            continue
                    
                    # Add call to action
                    if lang == 'fr':
                        response_text += "Laquelle vous intéresse? Dites simplement 'Rejoins' suivi du nom de l'activité pour vous inscrire."
                    elif lang == 'ar':
                        response_text += "أي واحدة تهمك؟ قل ببساطة 'انضم' متبوعًا باسم النشاط للتسجيل."
                    else:
                        response_text += "Ready to join one? Just say 'Join' and the activity name!"
                    
                    # Create display text for UI (shorter version)
                    display_text = msg['list_intro'] + "\n"
                    for i, act in enumerate(activities[:5], 1):
                        try:
                            if act['start_time']:
                                time_str = act['start_time'].strftime('%a %b %d at %I:%M %p')
                            else:
                                time_str = 'Time TBA'
                            
                            max_p = act.get('max_participants', None)
                            curr_p = act.get('current_participants', 0)
                            spots = max(0, (max_p - curr_p) if max_p else 999)
                            location = act.get('location', 'TBA')
                            activity_type = act.get('type', '').upper() if act.get('type') else 'EVENT'
                            
                            display_text += f"{i}. [{activity_type}] {act['title']}\n   📅 {time_str}\n   📍 {location} ({spots} spots)\n"
                        except Exception as e:
                            print(f"Error formatting activity {i} for display: {e}", file=sys.stderr)
                            display_text += f"{i}. {act['title']}\n"
                    
                    if len(activities) > 5:
                        display_text += f"\n...and {len(activities) - 5} more activities available"
                    
                    action_text = "👆 Say 'Join' + activity name to register"
                
                except Exception as e:
                    print(f"Error reading activities: {e}", file=sys.stderr)
                    response_text = msg['no_activities']
                    display_text = msg['no_activities']
                    action_text = ""
            else:
                # Check if there are no available activities because user joined all, or genuinely no activities
                cursor.execute("SELECT COUNT(*) as count FROM activites WHERE is_active = 1")
                total_active = cursor.fetchone()['count']
                
                if total_active > 0:
                    # User has joined all available activities
                    if lang == 'fr':
                        response_text = "Bravo! Vous êtes déjà inscrit à toutes les activités disponibles. De nouvelles activités seront ajoutées prochainement!"
                    elif lang == 'ar':
                        response_text = "ممتاز! أنت مسجل بالفعل في جميع الأنشطة المتاحة. سيتم إضافة أنشطة جديدة قريبا!"
                    else:
                        response_text = "Great job! You're already registered for all available activities. More will be added soon!"
                    action_text = "✨ You're all set for now!"
                else:
                    # No activities at all
                    response_text = msg['no_activities']
                    action_text = "No activities available"
                
                display_text = response_text
        
        elif intent == 'list_my_activities':
            cursor.execute("SELECT p.id, p.activity_id, a.title, a.start_time, a.location FROM participations p JOIN activites a ON p.activity_id = a.id WHERE p.senior_id = %s AND p.status IN (%s, %s, %s) ORDER BY a.start_time ASC", (user_id, 'présent', 'registered', 'inscrit'))
            
            participations = cursor.fetchall()
            
            if participations:
                if lang == 'fr':
                    response_text = "Voici les activités auxquelles vous participez. "
                elif lang == 'ar':
                    response_text = "إليك الأنشطة التي تشارك فيها. "
                else:
                    response_text = "Here's what you're joined for. "
                
                display_text = msg['my_activities'] + "\n"
                for i, p in enumerate(participations, 1):
                    time_str = p['start_time'].strftime('%a %b %d at %I:%M %p') if p['start_time'] else 'Date TBA'
                    location = p.get('location', 'Location TBA')
                    
                    if lang == 'fr':
                        response_text += f"{p['title']} le {time_str} à {location}. "
                        display_text += f"{i}. {p['title']}\n   📅 {time_str}\n   📍 {location}\n"
                    elif lang == 'ar':
                        response_text += f"{p['title']} في {time_str} في {location}. "
                        display_text += f"{i}. {p['title']}\n   📅 {time_str}\n   📍 {location}\n"
                    else:
                        response_text += f"{p['title']} on {time_str} at {location}. "
                        display_text += f"{i}. {p['title']}\n   📅 {time_str}\n   📍 {location}\n"
                
                if lang == 'fr':
                    response_text += "Dites 'Quitter' ou 'Annuler' suivi du nom de l'activité pour vous désinscrire."
                elif lang == 'ar':
                    response_text += "قل 'اترك' أو 'ألغِ' متبوعًا باسم النشاط للخروج."
                else:
                    response_text += "Say 'Leave' or 'Cancel' with the activity name to drop it."
                
                action_text = "💬 Say 'Leave' + activity name to quit"
            else:
                response_text = msg['no_my_activities']
                display_text = msg['no_my_activities']
                action_text = "No activities yet"
        
        elif intent == 'join':
            if not activity_name:
                response_text = msg['no_activity_match']
                display_text = response_text
            else:
                # Find matching activity (show all that user hasn't joined)
                cursor.execute("SELECT id, title, type, max_participants, current_participants FROM activites WHERE is_active = 1 AND id NOT IN (SELECT activity_id FROM participations WHERE senior_id = %s AND status IN ('présent', 'registered', 'inscrit')) ORDER BY start_time ASC", (user_id,))
                all_activities = cursor.fetchall()
                
                activity = find_best_activity_match(activity_name, all_activities)
                
                if not activity:
                    # If not found in available, check if they're already joined
                    cursor.execute("SELECT a.id, a.title FROM activites a JOIN participations p ON a.id = p.activity_id WHERE p.senior_id = %s AND p.status IN ('présent', 'registered', 'inscrit')", (user_id,))
                    user_activities = cursor.fetchall()
                    user_match = find_best_activity_match(activity_name, user_activities)
                    
                    if user_match:
                        response_text = msg['join_already'].format(activity=user_match['title'])
                    else:
                        response_text = msg['no_activity_match']
                    display_text = response_text
                else:
                    # Check for duplicate join BEFORE inserting
                    cursor.execute("SELECT id, status FROM participations WHERE activity_id = %s AND senior_id = %s", (activity['id'], user_id))
                    existing = cursor.fetchone()
                    
                    if existing:
                        existing_status = existing.get('status', '')
                        if existing_status in ('présent', 'registered', 'inscrit'):
                            # Already joined - this shouldn't happen but prevent duplicate
                            response_text = msg['join_already'].format(activity=activity['title'])
                            display_text = response_text
                            print(f"DEBUG JOIN: User {user_id} already registered for Activity {activity['id']} with status {existing_status}", file=sys.stderr)
                        elif existing_status in ('annulé', 'cancelled'):
                            # Previously cancelled, reactivate
                            now = datetime.datetime.now()
                            try:
                                cursor.execute("UPDATE participations SET status = %s, registered_at = %s WHERE id = %s", ('présent', now, existing['id']))
                                print(f"DEBUG JOIN: Reactivated cancelled participation {existing['id']}", file=sys.stderr)
                                
                                # Update counter (increment by 1)
                                old_count = activity['current_participants']
                                cursor.execute("UPDATE activites SET current_participants = current_participants + 1 WHERE id = %s", (activity['id'],))
                                
                                cursor.execute("SELECT current_participants FROM activites WHERE id = %s", (activity['id'],))
                                verify = cursor.fetchone()
                                new_count = verify['current_participants'] if verify else None
                                print(f"DEBUG JOIN: Counter verification - Old: {old_count}, New: {new_count}", file=sys.stderr)
                                
                                conn.commit()
                                print(f"DEBUG JOIN: Reactivation committed successfully", file=sys.stderr)
                                
                                response_text = msg['join_success'].format(activity=activity['title'])
                                display_text = response_text
                                action_text = f"✅ You're back in {activity['title']}!"
                            except Exception as join_error:
                                conn.rollback()
                                print(f"DEBUG JOIN ERROR: Transaction rolled back - {str(join_error)}", file=sys.stderr)
                                raise
                    else:
                        # New join - Register user with detailed transaction logging
                        now = datetime.datetime.now()
                        try:
                            # Step 1: Insert participation record
                            cursor.execute("INSERT INTO participations (activity_id, senior_id, status, title, registration_method, registered_at) VALUES (%s, %s, %s, %s, %s, %s)", (activity['id'], user_id, 'présent', activity['title'], 'voice_assistant', now))
                            print(f"DEBUG JOIN: Inserted participation record - Activity {activity['id']}, User {user_id}", file=sys.stderr)
                            
                            # Step 2: Update counter
                            old_count = activity['current_participants']
                            cursor.execute("UPDATE activites SET current_participants = current_participants + 1 WHERE id = %s", (activity['id'],))
                            print(f"DEBUG JOIN: Update query executed for Activity {activity['id']}", file=sys.stderr)
                            
                            # Step 3: Verify update worked by reading back
                            cursor.execute("SELECT current_participants FROM activites WHERE id = %s", (activity['id'],))
                            verify = cursor.fetchone()
                            new_count = verify['current_participants'] if verify else None
                            print(f"DEBUG JOIN: Counter verification - Old: {old_count}, New: {new_count}", file=sys.stderr)
                            
                            # Commit transaction
                            conn.commit()
                            print(f"DEBUG JOIN: Transaction committed successfully", file=sys.stderr)
                            
                            response_text = msg['join_success'].format(activity=activity['title'])
                            display_text = response_text
                            action_text = f"✅ You're in {activity['title']}!"
                            should_refresh = True
                        except Exception as join_error:
                            conn.rollback()
                            print(f"DEBUG JOIN ERROR: Transaction rolled back - {str(join_error)}", file=sys.stderr)
                            raise
        
        elif intent == 'cancel':
            if not activity_name:
                response_text = msg['no_activity_match']
                display_text = response_text
            else:
                # Find activity user is registered for
                cursor.execute("SELECT p.id, a.title, a.id as activity_id FROM participations p JOIN activites a ON p.activity_id = a.id WHERE p.senior_id = %s AND p.status IN ('présent', 'registered', 'inscrit')", (user_id,))
                
                user_activities = cursor.fetchall()
                
                # Find best match
                matched = None
                for p in user_activities:
                    if fuzzy_match(activity_name.lower(), p['title'].lower()) >= FUZZY_THRESHOLD:
                        matched = p
                        break
                
                if not matched:
                    response_text = msg['cancel_notfound']
                    display_text = response_text
                else:
                    # Cancel with detailed transaction logging
                    try:
                        print(f"DEBUG CANCEL: Starting cancel - Participation {matched['id']}, Activity {matched['activity_id']}, User {user_id}", file=sys.stderr)
                        
                        # Get current count before update
                        cursor.execute("SELECT current_participants FROM activites WHERE id = %s", (matched['activity_id'],))
                        pre_cancel = cursor.fetchone()
                        old_count = pre_cancel['current_participants'] if pre_cancel else None
                        print(f"DEBUG CANCEL: Counter before cancel = {old_count}", file=sys.stderr)
                        
                        # Step 1: Update participation status
                        cursor.execute("UPDATE participations SET status = %s WHERE id = %s", ('annulé', matched['id']))
                        print(f"DEBUG CANCEL: Updated participation #{matched['id']} to 'annulé'", file=sys.stderr)
                        
                        # Step 2: Decrement counter
                        cursor.execute("UPDATE activites SET current_participants = GREATEST(current_participants - 1, 0) WHERE id = %s", (matched['activity_id'],))
                        print(f"DEBUG CANCEL: Decrement query executed for Activity {matched['activity_id']}", file=sys.stderr)
                        
                        # Step 3: Verify update worked
                        cursor.execute("SELECT current_participants FROM activites WHERE id = %s", (matched['activity_id'],))
                        post_cancel = cursor.fetchone()
                        new_count = post_cancel['current_participants'] if post_cancel else None
                        print(f"DEBUG CANCEL: Counter after cancel = {new_count}, Expected = {max(0, old_count - 1) if old_count else 0}", file=sys.stderr)
                        
                        if old_count is not None and old_count > 0 and new_count == old_count:
                            print(f"DEBUG CANCEL WARNING: Counter didn't decrement! Count still {new_count}", file=sys.stderr)
                        
                        # Commit transaction
                        conn.commit()
                        print(f"DEBUG CANCEL: Transaction committed successfully", file=sys.stderr)
                        
                        response_text = msg['cancel_success'].format(activity=matched['title'])
                        display_text = response_text
                        action_text = f"✌️ Left {matched['title']}"
                        should_refresh = True
                    except Exception as cancel_error:
                        conn.rollback()
                        print(f"DEBUG CANCEL ERROR: Transaction rolled back - {str(cancel_error)}", file=sys.stderr)
                        raise
        
        else:
            response_text = msg['help']
            display_text = response_text
    
    except Exception as e:
        import traceback
        response_text = "Sorry, something went wrong. Please try again."
        display_text = response_text
        action_text = ""
        # Log full error for debugging
        error_msg = f"Intent: {intent}, Activity: {activity_name}, Error: {str(e)}"
        print(f"DEBUG: {error_msg}", file=sys.stderr)
        print(traceback.format_exc(), file=sys.stderr)
    
    finally:
        if conn:
            conn.close()
    
    # Generate audio response using response_text (which is more detailed and narrative)
    audio_url = text_to_speech(response_text, lang)
    
    return {
        "text": display_text,  # UI text (shorter, formatted)
        "audio": audio_url,    # Audio response (full narrative)
        "action": action_text,
        "success": True,
        "lang": lang,
        "intent": intent,
        "should_refresh": should_refresh  # Tell frontend to reload page after join/cancel
    }

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(json.dumps({"error": "Missing arguments", "success": False}))
        sys.exit(1)
    
    user_text = sys.argv[1]
    user_id = sys.argv[2]
    lang_override = sys.argv[3] if len(sys.argv) > 3 else None
    
    # Auto-detect language unless overridden
    lang = lang_override or detect_language(user_text)
    
    result = get_smart_response(user_text, user_id, lang)
    
    # Output as UTF-8 JSON (encoding is set at module level)
    print(json.dumps(result, ensure_ascii=False, indent=None))
