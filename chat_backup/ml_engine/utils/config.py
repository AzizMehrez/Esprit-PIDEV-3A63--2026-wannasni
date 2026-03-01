"""
Configuration for ML Engine

Database and other configuration settings for the ML Engine.
"""

# Database Configuration
DATABASE_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',  # Update with your database password
    'database': 'wannasni',
    'charset': 'utf8mb4'
}

# ML Model Configuration
ML_CONFIG = {
    'model_cache_dir': 'models/cache',
    'prediction_threshold': 0.7,
    'recommendation_limit': 10,
    'analysis_window_days': 30
}

# API Configuration
API_CONFIG = {
    'host': '127.0.0.1',
    'port': 5000,
    'debug': True,
    'cors_enabled': True
}

# Health Analytics Configuration
HEALTH_CONFIG = {
    'mood_scale_max': 10,
    'pain_scale_max': 10,
    'vitals_normal_ranges': {
        'blood_pressure_systolic': (90, 140),
        'blood_pressure_diastolic': (60, 90),
        'heart_rate': (60, 100),
        'temperature': (36.1, 37.2)
    },
    'alert_thresholds': {
        'pain_critical': 8,
        'mood_critical': 3,
        'medication_missed_days': 3
    }
}

# Activity Recommendation Configuration
ACTIVITY_CONFIG = {
    'collaborative_filtering_k': 10,
    'content_similarity_threshold': 0.5,
    'health_weight': 0.7,
    'preference_weight': 0.3
}

# Chat Enhancement Configuration
CHAT_CONFIG = {
    'sentiment_model': 'vader',
    'health_keywords_enabled': True,
    'urgency_detection_enabled': True,
    'context_window_messages': 10
}