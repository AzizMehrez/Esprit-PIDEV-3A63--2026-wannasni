"""
Minimal Working ML Engine API

A simplified version that provides basic ML functionality 
without complex dependencies for initial testing.
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import random
import logging
from datetime import datetime, timedelta

# Initialize Flask app
app = Flask(__name__)
CORS(app)

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint."""
    return jsonify({
        'status': 'healthy',
        'timestamp': datetime.now().isoformat(),
        'version': '1.0.0',
        'ml_engine': 'basic'
    })

@app.route('/api/health/analytics', methods=['POST'])
def health_analytics():
    """Basic health analytics endpoint."""
    try:
        data = request.get_json()
        user_id = data.get('user_id')
        days = data.get('days', 30)
        
        # Mock health analytics data
        mock_data = {
            'user_id': user_id,
            'health_trends': {
                'overall_score': random.randint(70, 95),
                'mood': {
                    'current_average': random.randint(6, 9),
                    'trend_direction': random.choice(['improving', 'stable', 'declining']),
                    'quality_assessment': random.choice(['good', 'fair', 'excellent'])
                },
                'pain': {
                    'current_average': random.randint(1, 4),
                    'trend_direction': random.choice(['improving', 'stable']),
                    'quality_assessment': 'manageable'
                },
                'sleep': {
                    'average_hours': random.randint(6, 8),
                    'quality_assessment': random.choice(['good', 'fair', 'excellent'])
                }
            },
            'predictions': {
                'mood_forecast': 'stable',
                'health_risk_level': 'low',
                'recommendations_count': 3
            },
            'analysis_period': f'{days} days',
            'last_updated': datetime.now().isoformat()
        }
        
        return jsonify(mock_data)
        
    except Exception as e:
        logger.error(f"Health analytics error: {e}")
        return jsonify({'error': 'Analytics processing failed'}), 500

@app.route('/api/activities/recommend', methods=['POST'])
def recommend_activities():
    """Basic activity recommendations endpoint."""
    try:
        data = request.get_json()
        user_id = data.get('user_id')
        limit = data.get('limit', 5)
        
        # Mock activity recommendations
        activities = [
            {'activity_identifier': 'Morning Walk', 'confidence_score': 0.85, 'reasons': ['Good for mood', 'Low impact']},
            {'activity_identifier': 'Light Stretching', 'confidence_score': 0.78, 'reasons': ['Pain management', 'Flexibility']},
            {'activity_identifier': 'Social Call', 'confidence_score': 0.92, 'reasons': ['Mood boost', 'Social connection']},
            {'activity_identifier': 'Reading Time', 'confidence_score': 0.73, 'reasons': ['Mental stimulation', 'Relaxation']},
            {'activity_identifier': 'Gentle Yoga', 'confidence_score': 0.68, 'reasons': ['Stress relief', 'Balance']}
        ]
        
        selected_activities = random.sample(activities, min(limit, len(activities)))
        
        # Add optimal timing
        for activity in selected_activities:
            activity['optimal_timing'] = {
                'best_time_of_day': random.choice(['morning', 'afternoon', 'evening']),
                'duration_minutes': random.randint(15, 45)
            }
        
        mock_data = {
            'user_id': user_id,
            'recommendations': selected_activities,
            'total_available': len(activities),
            'generated_at': datetime.now().isoformat()
        }
        
        return jsonify(mock_data)
        
    except Exception as e:
        logger.error(f"Activity recommendation error: {e}")
        return jsonify({'error': 'Recommendation processing failed'}), 500

@app.route('/api/chat/enhance', methods=['POST'])
def enhance_chat():
    """Basic chat enhancement endpoint."""
    try:
        data = request.get_json()
        user_id = data.get('user_id')
        message = data.get('message', '')
        
        # Basic sentiment analysis
        positive_words = ['good', 'great', 'happy', 'wonderful', 'excellent', 'better', 'fine']
        negative_words = ['bad', 'hurt', 'pain', 'sad', 'terrible', 'awful', 'worse', 'sick']
        health_words = ['pain', 'hurt', 'medication', 'doctor', 'health', 'sick', 'tired']
        
        sentiment_score = 0.5  # neutral
        health_concerns = []
        
        message_lower = message.lower()
        
        # Simple sentiment scoring
        for word in positive_words:
            if word in message_lower:
                sentiment_score += 0.1
        
        for word in negative_words:
            if word in message_lower:
                sentiment_score -= 0.1
        
        # Health concern detection
        for word in health_words:
            if word in message_lower:
                health_concerns.append({
                    'category': 'general_health',
                    'keywords': [word],
                    'severity': 'medium',
                    'requires_attention': sentiment_score < 0.4
                })
        
        # Determine urgency
        urgency_level = 'low'
        if sentiment_score < 0.3 and health_concerns:
            urgency_level = 'high'
        elif health_concerns:
            urgency_level = 'medium'
        
        mock_data = {
            'user_id': user_id,
            'original_message': message,
            'conversation_insights': {
                'sentiment': {
                    'overall_score': max(0, min(1, sentiment_score)),
                    'dominant_emotion': 'positive' if sentiment_score > 0.6 else 'negative' if sentiment_score < 0.4 else 'neutral'
                },
                'health_concerns': {
                    'total_concerns': len(health_concerns),
                    'concern_details': health_concerns
                },
                'urgency_assessment': {
                    'urgency_level': urgency_level,
                    'requires_immediate_attention': urgency_level == 'high'
                }
            },
            'health_context': {
                'health_summary': {
                    'mood': {
                        'current_level': random.randint(6, 9),
                        'status': 'good',
                        'trend': 'stable'
                    },
                    'pain': {
                        'current_level': random.randint(1, 3),
                        'status': 'manageable',
                        'trend': 'stable'
                    }
                },
                'proactive_suggestions': [
                    {'suggestion': 'Consider taking a short walk if weather permits', 'priority': 'medium'},
                    {'suggestion': 'Remember to stay hydrated throughout the day', 'priority': 'low'}
                ]
            },
            'enhanced_at': datetime.now().isoformat()
        }
        
        return jsonify(mock_data)
        
    except Exception as e:
        logger.error(f"Chat enhancement error: {e}")
        return jsonify({'error': 'Chat enhancement failed'}), 500

@app.route('/api/health/mood', methods=['POST'])
def mood_analysis():
    """Basic mood analysis endpoint."""
    try:
        data = request.get_json()
        user_id = data.get('user_id')
        days = data.get('days', 7)
        
        # Mock mood data
        mock_data = {
            'user_id': user_id,
            'mood_trends': {
                'average_mood': random.randint(6, 8),
                'mood_stability': random.choice(['stable', 'variable']),
                'trend_direction': random.choice(['improving', 'stable'])
            },
            'patterns': {
                'best_time_of_day': random.choice(['morning', 'afternoon', 'evening']),
                'mood_factors': ['weather', 'social_interaction', 'physical_activity']
            },
            'analysis_period': f'{days} days',
            'generated_at': datetime.now().isoformat()
        }
        
        return jsonify(mock_data)
        
    except Exception as e:
        logger.error(f"Mood analysis error: {e}")
        return jsonify({'error': 'Mood analysis failed'}), 500

@app.route('/api/health/medication', methods=['POST'])
def medication_tracking():
    """Basic medication tracking endpoint."""
    try:
        data = request.get_json()
        user_id = data.get('user_id')
        days = data.get('days', 14)
        
        # Mock medication data
        mock_data = {
            'user_id': user_id,
            'adherence_analysis': {
                'overall_adherence': random.randint(85, 98),
                'missed_doses': random.randint(0, 2),
                'compliance_trend': 'good'
            },
            'reminders': [
                {'medication': 'Morning medication', 'time': '08:00', 'status': 'pending'},
                {'medication': 'Evening medication', 'time': '20:00', 'status': 'completed'}
            ],
            'analysis_period': f'{days} days',
            'generated_at': datetime.now().isoformat()
        }
        
        return jsonify(mock_data)
        
    except Exception as e:
        logger.error(f"Medication tracking error: {e}")
        return jsonify({'error': 'Medication tracking failed'}), 500

@app.route('/api/health/vitals', methods=['POST'])
def vital_monitoring():
    """Basic vital signs monitoring endpoint."""
    try:
        data = request.get_json()
        user_id = data.get('user_id')
        days = data.get('days', 7)
        
        # Mock vital signs data
        mock_data = {
            'user_id': user_id,
            'vital_trends': {
                'blood_pressure': {
                    'systolic': random.randint(110, 130),
                    'diastolic': random.randint(70, 85),
                    'status': 'normal'
                },
                'heart_rate': random.randint(65, 80),
                'temperature': round(random.uniform(36.2, 37.0), 1)
            },
            'alerts': [],  # No alerts for mock data
            'analysis_period': f'{days} days',
            'generated_at': datetime.now().isoformat()
        }
        
        return jsonify(mock_data)
        
    except Exception as e:
        logger.error(f"Vital monitoring error: {e}")
        return jsonify({'error': 'Vital monitoring failed'}), 500

if __name__ == '__main__':
    print("🚀 Starting Basic ML Engine...")
    print("✅ API available at: http://127.0.0.1:5000")
    print("❤️  Health check: http://127.0.0.1:5000/health")
    print("📊 All endpoints: /api/health/analytics, /api/activities/recommend, /api/chat/enhance")
    print("\n⚡ Press Ctrl+C to stop")
    app.run(host='127.0.0.1', port=5000, debug=False)