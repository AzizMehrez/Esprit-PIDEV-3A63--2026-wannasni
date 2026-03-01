#!/usr/bin/env python3
"""
verification_analyzer.py  –  AI-driven user profile analyzer for WANNASNI verification requests.

Called by PHP VerificationAnalyzerService via proc_open.

Usage:
    python verification_analyzer.py analyze <user_json_path>

Input:  A JSON file with user profile data and activity stats.
Output: JSON to stdout with score + report.

Scoring range: 0.0 (bad) – 1.0 (excellent).
Threshold: score < 0.3 => auto-reject.
"""

import sys
import json
import os
import re
from datetime import datetime, timedelta

# ── Toxicity / spam word lists (lightweight, no ML model needed) ──
TOXIC_WORDS = {
    'spam', 'scam', 'fake', 'fraud', 'hack', 'phishing', 'malware',
    'porn', 'xxx', 'nude', 'sex', 'drug', 'kill', 'bomb', 'terror',
    'racist', 'hate', 'nazi', 'slur',
}

SPAM_PATTERNS = [
    r'(buy|click|free|win|winner|prize|cash|money|earn)\s+(now|here|fast|quick)',
    r'(https?://\S+){3,}',  # 3+ URLs in one text
    r'(.)\1{5,}',           # character repeated 6+ times
    r'[A-Z\s]{20,}',        # 20+ uppercase chars in a row
]


def analyze_profile(data: dict) -> dict:
    """
    Analyze a user profile and return a verification score + report.
    
    Factors considered:
    1. Profile completeness (name, photo, bio, location)
    2. Account age
    3. Post activity (count, diversity, recency)
    4. Content quality (toxicity, spam detection)
    5. Social engagement (connections, likes, comments received)
    """
    report = {}
    scores = {}

    # ── 1. Profile Completeness (0-1) ──
    fields = {
        'firstName': data.get('firstName'),
        'lastName': data.get('lastName'),
        'imageProfil': data.get('imageProfil'),
        'bio': data.get('bio'),
        'phone': data.get('phone'),
        'dateNaissance': data.get('dateNaissance'),
        'location': data.get('location') or data.get('ville'),
    }
    filled = sum(1 for v in fields.values() if v)
    completeness = filled / len(fields)
    scores['profile_completeness'] = completeness
    report['profile_completeness'] = {
        'score': round(completeness, 2),
        'filled': filled,
        'total': len(fields),
        'missing': [k for k, v in fields.items() if not v],
    }

    # ── 2. Account Age (0-1) ──
    created_str = data.get('createdAt')
    account_age_days = 0
    if created_str:
        try:
            created = datetime.fromisoformat(created_str.replace('Z', '+00:00'))
            account_age_days = (datetime.now(created.tzinfo) - created).days
        except Exception:
            account_age_days = 0

    # 0 days: 0.0, 7 days: 0.3, 30 days: 0.6, 90+ days: 1.0
    if account_age_days >= 90:
        age_score = 1.0
    elif account_age_days >= 30:
        age_score = 0.6 + 0.4 * ((account_age_days - 30) / 60)
    elif account_age_days >= 7:
        age_score = 0.3 + 0.3 * ((account_age_days - 7) / 23)
    else:
        age_score = account_age_days * 0.3 / 7

    scores['account_age'] = age_score
    report['account_age'] = {
        'score': round(age_score, 2),
        'days': account_age_days,
    }

    # ── 3. Post Activity (0-1) ──
    post_count = data.get('postCount', 0)
    # 0 posts: 0.0, 1 post: 0.2, 3 posts: 0.5, 10+ posts: 1.0
    if post_count >= 10:
        post_score = 1.0
    elif post_count >= 3:
        post_score = 0.5 + 0.5 * ((post_count - 3) / 7)
    elif post_count >= 1:
        post_score = 0.2 + 0.3 * ((post_count - 1) / 2)
    else:
        post_score = 0.0

    scores['post_activity'] = post_score
    report['post_activity'] = {
        'score': round(post_score, 2),
        'count': post_count,
    }

    # ── 4. Content Quality (0-1) – text toxicity & spam analysis ──
    posts_content = data.get('postsContent', [])
    bio_text = data.get('bio') or ''
    all_text = ' '.join(posts_content + [bio_text]).lower()

    toxic_found = [w for w in TOXIC_WORDS if w in all_text]
    spam_found = [p for p in SPAM_PATTERNS if re.search(p, all_text, re.IGNORECASE)]

    if toxic_found or spam_found:
        content_score = max(0.0, 1.0 - 0.3 * len(toxic_found) - 0.4 * len(spam_found))
    else:
        content_score = 1.0

    scores['content_quality'] = content_score
    report['content_quality'] = {
        'score': round(content_score, 2),
        'toxic_words': toxic_found,
        'spam_patterns': len(spam_found),
    }

    # ── 5. Social Engagement (0-1) ──
    connections = data.get('connectionCount', 0)
    likes_received = data.get('likesReceived', 0)
    comments_received = data.get('commentsReceived', 0)
    engagement = connections + likes_received * 0.5 + comments_received * 0.5

    if engagement >= 50:
        social_score = 1.0
    elif engagement >= 10:
        social_score = 0.4 + 0.6 * ((engagement - 10) / 40)
    elif engagement >= 1:
        social_score = engagement * 0.4 / 10
    else:
        social_score = 0.0

    scores['social_engagement'] = social_score
    report['social_engagement'] = {
        'score': round(social_score, 2),
        'connections': connections,
        'likes_received': likes_received,
        'comments_received': comments_received,
    }

    # ── Final weighted score ──
    weights = {
        'profile_completeness': 0.20,
        'account_age': 0.15,
        'post_activity': 0.20,
        'content_quality': 0.30,
        'social_engagement': 0.15,
    }
    final_score = sum(scores[k] * weights[k] for k in weights)

    # ── Decision ──
    if final_score < 0.3:
        decision = 'reject'
        decision_reason = 'Score too low – account does not meet verification criteria.'
    elif content_score < 0.3:
        decision = 'reject'
        decision_reason = 'Content quality issues detected (toxic/spam content).'
    else:
        decision = 'review'  # Needs human review
        decision_reason = 'Score acceptable – awaiting admin review.'

    return {
        'score': round(final_score, 3),
        'decision': decision,
        'decision_reason': decision_reason,
        'factors': report,
        'analyzed_at': datetime.now().isoformat(),
    }


def main():
    if len(sys.argv) < 3:
        print(json.dumps({'error': 'Usage: verification_analyzer.py analyze <user_json_path>'}))
        sys.exit(1)

    command = sys.argv[1]
    json_path = sys.argv[2]

    if command != 'analyze':
        print(json.dumps({'error': f'Unknown command: {command}'}))
        sys.exit(1)

    if not os.path.exists(json_path):
        print(json.dumps({'error': f'JSON file not found: {json_path}'}))
        sys.exit(1)

    try:
        with open(json_path, 'r', encoding='utf-8') as f:
            data = json.load(f)
    except Exception as e:
        print(json.dumps({'error': f'Failed to read JSON: {str(e)}'}))
        sys.exit(1)

    result = analyze_profile(data)
    print(json.dumps(result, ensure_ascii=False))


if __name__ == '__main__':
    main()
