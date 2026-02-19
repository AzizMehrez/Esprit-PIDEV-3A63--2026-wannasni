"""
Health Context Provider Module

Provides personalized health context and insights to enhance 
chat responses for senior care management.
"""

import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from typing import Dict, List, Any
import re

from ..utils.database import db
from ..config import CHAT_ENHANCEMENT

class HealthContextProvider:
    """Provides health context to enhance chat interactions."""
    
    def __init__(self):
        self.config = CHAT_ENHANCEMENT
        self.context_window_days = self.config['context_window_days']
    
    def get_user_context(self, user_id: int) -> Dict[str, Any]:
        """
        Get comprehensive health context for a user to enhance chat responses.
        
        Args:
            user_id: User identifier
            
        Returns:
            Dictionary with health context and insights
        """
        try:
            # Gather health data
            health_data = db.get_user_health_data(user_id, self.context_window_days)
            medications = db.get_user_medications(user_id)
            recent_activities = db.get_user_activities(user_id, self.context_window_days)
            chat_history = db.get_chat_history(user_id, self.context_window_days)
            
            # Build context
            health_summary = self._create_health_summary(health_data)
            medication_context = self._create_medication_context(medications)
            activity_context = self._create_activity_context(recent_activities)
            conversation_insights = self._analyze_conversation_patterns(chat_history)
            
            # Generate proactive suggestions
            health_alerts = self._generate_health_alerts(health_data)
            proactive_suggestions = self._generate_proactive_suggestions(
                health_data, recent_activities, conversation_insights
            )
            
            # Create personalized greeting context
            personalized_context = self._create_personalized_context(
                health_summary, activity_context, conversation_insights
            )
            
            return {
                'user_id': user_id,
                'context_date': datetime.now().isoformat(),
                'context_period_days': self.context_window_days,
                'health_summary': health_summary,
                'medication_context': medication_context,
                'activity_context': activity_context,
                'conversation_insights': conversation_insights,
                'health_alerts': health_alerts,
                'proactive_suggestions': proactive_suggestions,
                'personalized_context': personalized_context,
                'chat_enhancement_flags': self._get_enhancement_flags(health_data, medications)
            }
            
        except Exception as e:
            return {
                'error': f'Failed to get user context: {str(e)}',
                'user_id': user_id,
                'fallback_context': self._get_fallback_context()
            }
    
    def _create_health_summary(self, health_data: pd.DataFrame) -> Dict[str, Any]:
        """Create a concise health summary for context."""
        if health_data.empty:
            return {'status': 'no_recent_data'}
        
        summary = {}
        
        # Mood analysis
        if 'mood_score' in health_data.columns:
            recent_moods = health_data['mood_score'].dropna().tail(3)
            if not recent_moods.empty:
                avg_mood = recent_moods.mean()
                mood_trend = self._calculate_simple_trend(recent_moods)
                
                summary['mood'] = {
                    'current_level': round(avg_mood, 1),
                    'trend': mood_trend,
                    'status': 'concerning' if avg_mood < 4 else 'good' if avg_mood > 7 else 'moderate',
                    'last_updated': health_data['created_at'].max().isoformat() if 'created_at' in health_data.columns else 'unknown'
                }
        
        # Pain analysis
        if 'pain_level' in health_data.columns:
            recent_pain = health_data['pain_level'].dropna().tail(3)
            if not recent_pain.empty:
                avg_pain = recent_pain.mean()
                pain_trend = self._calculate_simple_trend(recent_pain)
                
                summary['pain'] = {
                    'current_level': round(avg_pain, 1),
                    'trend': pain_trend,
                    'status': 'high' if avg_pain > 6 else 'moderate' if avg_pain > 3 else 'low'
                }
        
        # Sleep analysis
        if 'sleep_hours' in health_data.columns:
            recent_sleep = health_data['sleep_hours'].dropna().tail(3)
            if not recent_sleep.empty:
                avg_sleep = recent_sleep.mean()
                
                summary['sleep'] = {
                    'average_hours': round(avg_sleep, 1),
                    'quality': 'poor' if avg_sleep < 6 else 'excessive' if avg_sleep > 9 else 'good'
                }
        
        # Energy levels
        if 'energy_level' in health_data.columns:
            recent_energy = health_data['energy_level'].dropna().tail(3)
            if not recent_energy.empty:
                avg_energy = recent_energy.mean()
                
                summary['energy'] = {
                    'current_level': round(avg_energy, 1),
                    'status': 'low' if avg_energy < 4 else 'high' if avg_energy > 7 else 'moderate'
                }
        
        return summary
    
    def _calculate_simple_trend(self, data_series: pd.Series) -> str:
        """Calculate simple trend direction."""
        if len(data_series) < 2:
            return 'stable'
        
        recent_avg = data_series.tail(2).mean()
        earlier_avg = data_series.head(max(1, len(data_series) - 2)).mean()
        
        if recent_avg > earlier_avg + 0.5:
            return 'improving'
        elif recent_avg < earlier_avg - 0.5:
            return 'declining'
        else:
            return 'stable'
    
    def _create_medication_context(self, medications: pd.DataFrame) -> Dict[str, Any]:
        """Create medication context for chat enhancement."""
        if medications.empty:
            return {'status': 'no_active_medications'}
        
        context = {
            'active_medications_count': len(medications),
            'medication_types': [],
            'potential_concerns': [],
            'adherence_reminders': []
        }
        
        # Analyze medication types
        for _, medication in medications.iterrows():
            med_name = medication.get('medication_name', 'Unknown medication')
            med_type = medication.get('medication_type', 'general')
            
            context['medication_types'].append({
                'name': med_name,
                'type': med_type,
                'frequency': medication.get('dosage_frequency', 'as needed')
            })
        
        # Generate adherence reminders
        current_hour = datetime.now().hour
        if 8 <= current_hour <= 10:
            context['adherence_reminders'].append("Morning medication time")
        elif 17 <= current_hour <= 19:
            context['adherence_reminders'].append("Evening medication time")
        
        return context
    
    def _create_activity_context(self, activities: pd.DataFrame) -> Dict[str, Any]:
        """Create activity context for recommendations."""
        if activities.empty:
            return {'status': 'no_recent_activities', 'recommendation': 'Consider participating in some activities today'}
        
        context = {
            'recent_activity_count': len(activities),
            'last_activity_date': activities['participation_date'].max().isoformat() if 'participation_date' in activities.columns else 'unknown',
            'favorite_categories': [],
            'mood_impact_summary': {},
            'activity_suggestions': []
        }
        
        # Analyze favorite categories
        if 'category' in activities.columns:
            category_counts = activities['category'].value_counts()
            context['favorite_categories'] = category_counts.head(3).index.tolist()
        
        # Mood impact analysis
        if 'mood_before' in activities.columns and 'mood_after' in activities.columns:
            mood_improvements = activities['mood_after'] - activities['mood_before']
            avg_improvement = mood_improvements.mean()
            
            context['mood_impact_summary'] = {
                'average_improvement': round(avg_improvement, 1),
                'successful_activities': int((mood_improvements > 0).sum()),
                'most_effective_category': activities.groupby('category')['mood_after'].mean().idxmax() if len(activities) > 1 else 'unknown'
            }
        
        # Recent activity level assessment
        days_since_last = (datetime.now() - pd.to_datetime(activities['participation_date'].max())).days if 'participation_date' in activities.columns else 7
        
        if days_since_last > 3:
            context['activity_suggestions'].append("It's been a few days since your last activity. Consider joining something today!")
        elif days_since_last == 0:
            context['activity_suggestions'].append("Great job staying active today!")
        
        return context
    
    def _analyze_conversation_patterns(self, chat_history: List[Dict]) -> Dict[str, Any]:
        """Analyze recent conversation patterns for insights."""
        if not chat_history:
            return {'status': 'no_recent_conversations'}
        
        insights = {
            'conversation_count': len(chat_history),
            'health_concerns_mentioned': [],
            'emotional_indicators': [],
            'common_topics': [],
            'response_engagement': 'unknown'
        }
        
        # Analyze messages for health concerns
        health_keywords = self.config['health_concern_keywords']
        
        for conversation in chat_history[-10:]:  # Analyze last 10 conversations
            message = conversation.get('message', '').lower()
            
            # Check for health concerns
            for keyword in health_keywords:
                if keyword in message:
                    if keyword not in insights['health_concerns_mentioned']:
                        insights['health_concerns_mentioned'].append(keyword)
            
            # Analyze emotional indicators
            if any(word in message for word in ['sad', 'worried', 'anxious', 'scared']):
                insights['emotional_indicators'].append('negative_emotion')
            elif any(word in message for word in ['happy', 'good', 'great', 'wonderful']):
                insights['emotional_indicators'].append('positive_emotion')
        
        # Recent conversation frequency
        recent_conversations = [c for c in chat_history if self._is_recent_conversation(c)]
        
        if len(recent_conversations) > 5:
            insights['response_engagement'] = 'high'
        elif len(recent_conversations) > 2:
            insights['response_engagement'] = 'moderate'
        else:
            insights['response_engagement'] = 'low'
        
        return insights
    
    def _is_recent_conversation(self, conversation: Dict) -> bool:
        """Check if conversation is recent (within last 24 hours)."""
        try:
            if 'created_at' in conversation:
                conv_time = pd.to_datetime(conversation['created_at'])
                return (datetime.now() - conv_time) < timedelta(hours=24)
        except:
            pass
        return False
    
    def _generate_health_alerts(self, health_data: pd.DataFrame) -> List[Dict[str, Any]]:
        """Generate health alerts for immediate attention."""
        alerts = []
        
        if health_data.empty:
            return alerts
        
        # Mood alerts
        if 'mood_score' in health_data.columns:
            recent_moods = health_data['mood_score'].dropna().tail(3)
            if not recent_moods.empty and recent_moods.mean() < 3:
                alerts.append({
                    'type': 'mood_concern',
                    'severity': 'high',
                    'message': 'Low mood levels detected in recent entries',
                    'suggestion': 'Consider discussing this with a healthcare provider or counselor'
                })
        
        # Pain alerts
        if 'pain_level' in health_data.columns:
            recent_pain = health_data['pain_level'].dropna().tail(3)
            if not recent_pain.empty and recent_pain.mean() > 7:
                alerts.append({
                    'type': 'pain_concern',
                    'severity': 'medium',
                    'message': 'High pain levels reported recently',
                    'suggestion': 'Pain management review might be helpful'
                })
        
        # Vital signs alerts
        if 'blood_pressure_systolic' in health_data.columns:
            recent_bp = health_data['blood_pressure_systolic'].dropna().tail(3)
            if not recent_bp.empty and recent_bp.mean() > 140:
                alerts.append({
                    'type': 'blood_pressure_concern',
                    'severity': 'medium',
                    'message': 'Elevated blood pressure readings',
                    'suggestion': 'Monitor closely and consider medical consultation'
                })
        
        return alerts
    
    def _generate_proactive_suggestions(self, health_data: pd.DataFrame, 
                                      activities: pd.DataFrame, 
                                      conversation_insights: Dict) -> List[Dict[str, Any]]:
        """Generate proactive suggestions based on user patterns."""
        suggestions = []
        
        # Health-based suggestions
        if not health_data.empty:
            if 'mood_score' in health_data.columns:
                recent_mood = health_data['mood_score'].dropna().tail(3).mean()
                
                if recent_mood < 5:
                    suggestions.append({
                        'type': 'mood_boost',
                        'priority': 'high',
                        'suggestion': "I notice you've been feeling a bit down lately. Would you like to talk about activities that usually help improve your mood?",
                        'follow_up_actions': ['activity_recommendations', 'emotional_support']
                    })
        
        # Activity-based suggestions
        if not activities.empty and 'participation_date' in activities.columns:
            days_since_activity = (datetime.now() - pd.to_datetime(activities['participation_date'].max())).days
            
            if days_since_activity > 3:
                suggestions.append({
                    'type': 'activity_engagement',
                    'priority': 'medium',
                    'suggestion': "It's been a few days since your last activity. Would you like me to suggest some activities that match your interests?",
                    'follow_up_actions': ['show_activity_calendar', 'personalized_recommendations']
                })
        
        # Conversation pattern suggestions
        health_concerns = conversation_insights.get('health_concerns_mentioned', [])
        if health_concerns:
            suggestions.append({
                'type': 'health_follow_up',
                'priority': 'medium',
                'suggestion': f"I noticed you mentioned {', '.join(health_concerns[:2])} recently. How are you feeling about that today?",
                'follow_up_actions': ['health_assessment', 'care_team_notification']
            })
        
        return suggestions
    
    def _create_personalized_context(self, health_summary: Dict, activity_context: Dict, 
                                   conversation_insights: Dict) -> Dict[str, Any]:
        """Create personalized context for chat responses."""
        context = {
            'greeting_style': 'standard',
            'conversation_tone': 'supportive',
            'key_focus_areas': [],
            'response_adaptations': []
        }
        
        # Adapt greeting based on mood
        mood_status = health_summary.get('mood', {}).get('status', 'moderate')
        if mood_status == 'concerning':
            context['greeting_style'] = 'gentle_caring'
            context['conversation_tone'] = 'extra_supportive'
            context['key_focus_areas'].append('emotional_wellbeing')
        elif mood_status == 'good':
            context['greeting_style'] = 'upbeat'
            context['conversation_tone'] = 'encouraging'
        
        # Focus areas based on health data
        if health_summary.get('pain', {}).get('status') == 'high':
            context['key_focus_areas'].append('pain_management')
            context['response_adaptations'].append('avoid_strenuous_activity_suggestions')
        
        # Activity engagement level
        activity_count = activity_context.get('recent_activity_count', 0)
        if activity_count == 0:
            context['key_focus_areas'].append('activity_encouragement')
        elif activity_count > 5:
            context['key_focus_areas'].append('activity_celebration')
        
        return context
    
    def _get_enhancement_flags(self, health_data: pd.DataFrame, medications: pd.DataFrame) -> Dict[str, bool]:
        """Get flags for chat enhancement features."""
        flags = {
            'health_monitoring_active': not health_data.empty,
            'medication_reminders_needed': not medications.empty,
            'mood_support_enabled': False,
            'activity_suggestions_enabled': True,
            'health_alerts_active': False
        }
        
        # Enable mood support if needed
        if not health_data.empty and 'mood_score' in health_data.columns:
            recent_mood = health_data['mood_score'].dropna().tail(3).mean()
            if recent_mood < 5:
                flags['mood_support_enabled'] = True
        
        # Enable health alerts if concerning patterns
        if not health_data.empty:
            concerning_indicators = 0
            
            # Check various health indicators
            if 'mood_score' in health_data.columns:
                if health_data['mood_score'].dropna().tail(3).mean() < 4:
                    concerning_indicators += 1
            
            if 'pain_level' in health_data.columns:
                if health_data['pain_level'].dropna().tail(3).mean() > 6:
                    concerning_indicators += 1
            
            if concerning_indicators >= 1:
                flags['health_alerts_active'] = True
        
        return flags
    
    def _get_fallback_context(self) -> Dict[str, Any]:
        """Provide fallback context when user context cannot be retrieved."""
        return {
            'greeting_style': 'standard',
            'conversation_tone': 'supportive',
            'default_suggestions': [
                'Ask about daily wellness',
                'Suggest light activities',
                'Encourage health tracking'
            ],
            'enhancement_level': 'basic'
        }