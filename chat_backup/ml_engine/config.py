"""
ML Engine Configuration

Configuration settings for the WANNASNI ML enhancement system.
"""

import os
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Database Configuration (matching db_config.php)
DATABASE_CONFIG = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASS', ''),
    'database': os.getenv('DB_NAME', 'wannasni'),
    'charset': 'utf8mb4',
    'autocommit': True
}

# ML Engine Settings
ML_CONFIG = {
    'models_path': './ml_engine/models/',
    'health_prediction_interval': 3600,  # 1 hour in seconds
    'activity_recommendation_interval': 86400,  # 24 hours in seconds
    'chat_enhancement_enabled': True,
    'background_processing': True,
    'max_health_records': 1000,  # For training
    'recommendation_cache_ttl': 3600  # 1 hour cache
}

# API Settings
API_CONFIG = {
    'host': '127.0.0.1',
    'port': 5000,
    'debug': True,
    'cors_origins': ['*']
}

# Health Analytics Configuration
HEALTH_ANALYTICS = {
    'alert_thresholds': {
        'blood_pressure_high': 140,
        'blood_pressure_low': 90,
        'heart_rate_high': 100,
        'heart_rate_low': 60,
        'mood_depression_threshold': 3,  # 1-10 scale
        'pain_alert_threshold': 7,  # 1-10 scale
        'medication_adherence_threshold': 0.8  # 80%
    },
    'trend_analysis_days': 30,
    'anomaly_detection_sensitivity': 0.95
}

# Activity Recommendation Configuration
ACTIVITY_RECOMMENDATIONS = {
    'max_recommendations': 5,
    'similarity_threshold': 0.7,
    'mood_activity_mapping': {
        'depression': ['physical_activity', 'social_interaction'],
        'anxiety': ['relaxation', 'mindfulness'],
        'fatigue': ['light_exercise', 'rest'],
        'energetic': ['group_activities', 'outdoor']
    },
    'time_preference_weights': {
        'morning': 1.2,
        'afternoon': 1.0,
        'evening': 0.8
    }
}

# Chat Enhancement Configuration
CHAT_ENHANCEMENT = {
    'context_window_days': 7,
    'sentiment_threshold': 0.6,
    'health_concern_keywords': [
        'pain', 'tired', 'dizzy', 'nausea', 'headache',
        'sad', 'depressed', 'anxious', 'worried', 'stressed'
    ],
    'proactive_suggestions': {
        'health_deterioration': True,
        'medication_reminders': True,
        'activity_suggestions': True,
        'social_check_ins': True
    }
}