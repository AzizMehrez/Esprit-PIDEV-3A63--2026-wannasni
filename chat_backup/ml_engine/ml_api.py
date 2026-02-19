"""
WANNASNI ML Engine API Server

Flask API server that provides machine learning services for the senior care platform.
Includes health analytics, activity recommendations, and chat enhancement.
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import logging
import traceback
from datetime import datetime

import sys
import os
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

# Import configuration
try:
    from utils.config import API_CONFIG, ML_CONFIG
except ImportError:
    # Fallback configuration
    API_CONFIG = {'host': '127.0.0.1', 'port': 5000, 'debug': True}
    ML_CONFIG = {'prediction_threshold': 0.7, 'recommendation_limit': 10}

# Import database manager
try:
    from utils.database import DatabaseManager
    db = DatabaseManager()
except ImportError:
    db = None

# Import ML modules
try:
    from health_analytics.health_predictor import HealthPredictor
    from health_analytics.medication_tracker import MedicationTracker
    from health_analytics.vital_monitor import VitalMonitor
    from health_analytics.mood_analyzer import MoodAnalyzer
    from recommendations.activity_recommender import ActivityRecommender
    from chat_enhancement.health_context_provider import HealthContextProvider
    from chat_enhancement.conversation_analyzer import ConversationAnalyzer
except ImportError:
    # ML modules will be None if imports fail
    HealthPredictor = None
    MedicationTracker = None
    VitalMonitor = None
    MoodAnalyzer = None
    ActivityRecommender = None
    HealthContextProvider = None
    ConversationAnalyzer = None

# Initialize Flask app
app = Flask(__name__)
CORS(app)  # Allow all origins for development

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Initialize ML components (conditionally)
health_predictor = HealthPredictor() if HealthPredictor else None
medication_tracker = MedicationTracker() if MedicationTracker else None
vital_monitor = VitalMonitor() if VitalMonitor else None
mood_analyzer = MoodAnalyzer() if MoodAnalyzer else None
activity_recommender = ActivityRecommender() if ActivityRecommender else None
health_context_provider = HealthContextProvider() if HealthContextProvider else None
conversation_analyzer = ConversationAnalyzer() if ConversationAnalyzer else None

@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint."""
    return jsonify({
        'status': 'healthy',
        'timestamp': datetime.now().isoformat(),
        'version': '1.0.0'
    })

@app.route('/api/health/predict', methods=['POST'])
def predict_health():
    """Predict health trends for a user."""
    try:
        data = request.get_json()
        user_id = data.get('user_id')
        days = data.get('days', 30)
        
        if not user_id:
            return jsonify({'error': 'user_id is required'}), 400
        
        prediction = health_predictor.predict_health_trends(user_id, days)
        return jsonify(prediction)
        
    except Exception as e:
        logger.error(f"Health prediction error: {e}")
        return jsonify({'error': 'Prediction failed'}), 500

@app.route('/api/health/analytics', methods=['POST'])
def health_analytics():
    """Get comprehensive health analytics for a user."""
    try:
        data = request.get_json()
        user_id = data.get('user_id')
        days = data.get('days', 30)
        
        if not user_id:
            return jsonify({'error': 'user_id is required'}), 400
        
        # Get health data
        health_data = db.get_user_health_data(user_id, days)
        vital_trends = db.get_user_vital_trends(user_id, days)
        
        if health_data.empty:
            return jsonify({'message': 'No health data found', 'analytics': {}})
        
        # Analyze trends and patterns
        analytics = health_predictor.analyze_health_patterns(health_data, vital_trends)
        
        # Add medication tracking
        medication_analysis = medication_tracker.analyze_adherence(user_id, days)
        
        # Combine results
        result = {
            'user_id': user_id,
            'period_days': days,
            'health_trends': analytics,
            'medication_tracking': medication_analysis,
            'generated_at': datetime.now().isoformat()
        }
        
        return jsonify(result)
        
    except Exception as e:
        logger.error(f"Health analytics error: {e}")
        return jsonify({'error': 'Analytics failed'}), 500

@app.route('/api/health/mood-analyze', methods=['POST'])
def analyze_mood():
    """Analyze user mood patterns."""
    try:
        data = request.get_json()
        user_id = data.get('user_id')
        days = data.get('days', 7)
        
        if not user_id:
            return jsonify({'error': 'user_id is required'}), 400
        
        mood_analysis = mood_analyzer.analyze_mood_patterns(user_id, days)
        return jsonify(mood_analysis)
        
    except Exception as e:
        logger.error(f"Mood analysis error: {e}")
        return jsonify({'error': 'Mood analysis failed'}), 500

@app.route('/api/health/medication-track', methods=['POST'])
def track_medication():
    """Track medication adherence and predict compliance."""
    try:
        data = request.get_json()
        user_id = data.get('user_id')
        days = data.get('days', 30)
        
        if not user_id:
            return jsonify({'error': 'user_id is required'}), 400
        
        tracking = medication_tracker.track_adherence_patterns(user_id, days)
        return jsonify(tracking)
        
    except Exception as e:
        logger.error(f"Medication tracking error: {e}")
        return jsonify({'error': 'Medication tracking failed'}), 500

@app.route('/api/health/vital-monitor', methods=['POST'])
def monitor_vitals():
    """Monitor vital signs and detect anomalies."""
    try:
        data = request.get_json()
        user_id = data.get('user_id')
        days = data.get('days', 14)
        
        if not user_id:
            return jsonify({'error': 'user_id is required'}), 400
        
        monitoring = vital_monitor.monitor_anomalies(user_id, days)
        return jsonify(monitoring)
        
    except Exception as e:
        logger.error(f"Vital monitoring error: {e}")
        return jsonify({'error': 'Vital monitoring failed'}), 500

@app.route('/api/activities/recommend', methods=['POST'])
def recommend_activities():
    """Get personalized activity recommendations."""
    try:
        data = request.get_json()
        user_id = data.get('user_id')
        limit = data.get('limit', 5)
        
        if not user_id:
            return jsonify({'error': 'user_id is required'}), 400
        
        recommendations = activity_recommender.get_recommendations(user_id, limit)
        return jsonify(recommendations)
        
    except Exception as e:
        logger.error(f"Activity recommendation error: {e}")
        return jsonify({'error': 'Recommendation failed'}), 500

@app.route('/api/chat/enhance', methods=['POST'])
def enhance_chat():
    """Enhance chat message with health context and insights."""
    try:
        data = request.get_json()
        user_id = data.get('user_id')
        message = data.get('message')
        context = data.get('context', {})
        
        if not user_id or not message:
            return jsonify({'error': 'user_id and message are required'}), 400
        
        # Analyze conversation for health concerns
        conversation_insights = conversation_analyzer.analyze_message(user_id, message)
        
        # Get health context for enhanced responses
        health_context = health_context_provider.get_user_context(user_id)
        
        # Combine insights
        enhancement = {
            'user_id': user_id,
            'message': message,
            'health_context': health_context,
            'conversation_insights': conversation_insights,
            'timestamp': datetime.now().isoformat()
        }
        
        return jsonify(enhancement)
        
    except Exception as e:
        logger.error(f"Chat enhancement error: {e}")
        return jsonify({'error': 'Chat enhancement failed'}), 500

@app.route('/api/batch/daily-processing', methods=['POST'])
def daily_batch_processing():
    """Run daily batch processing for all users."""
    try:
        # Get all active users
        users_data = db.get_all_users_health_summary(7)
        
        results = {
            'processed_users': 0,
            'health_alerts': 0,
            'recommendations_generated': 0,
            'timestamp': datetime.now().isoformat()
        }
        
        for _, user_data in users_data.iterrows():
            user_id = user_data['user_id']
            
            try:
                # Health analytics
                health_analytics = health_predictor.predict_health_trends(user_id, 7)
                
                # Activity recommendations
                recommendations = activity_recommender.get_recommendations(user_id, 3)
                
                # Check for alerts
                if health_analytics.get('alerts'):
                    results['health_alerts'] += len(health_analytics['alerts'])
                
                if recommendations.get('recommendations'):
                    results['recommendations_generated'] += len(recommendations['recommendations'])
                
                results['processed_users'] += 1
                
            except Exception as user_error:
                logger.warning(f"Error processing user {user_id}: {user_error}")
        
        return jsonify(results)
        
    except Exception as e:
        logger.error(f"Batch processing error: {e}")
        return jsonify({'error': 'Batch processing failed'}), 500

@app.errorhandler(404)
def not_found(error):
    return jsonify({'error': 'Endpoint not found'}), 404

@app.errorhandler(500)
def internal_error(error):
    return jsonify({'error': 'Internal server error'}), 500

if __name__ == '__main__':
    try:
        logger.info("Starting WANNASNI ML Engine API Server")
        logger.info(f"Server will run on {API_CONFIG['host']}:{API_CONFIG['port']}")
        
        app.run(
            host=API_CONFIG['host'],
            port=API_CONFIG['port'],
            debug=API_CONFIG['debug']
        )
    except Exception as e:
        logger.error(f"Failed to start server: {e}")
        print(traceback.format_exc())