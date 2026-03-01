"""
Conversation Analyzer Module

Analyzes chat conversations for sentiment, health concerns, and emotional patterns
to enhance AI responses in senior care management.
"""

import pandas as pd
import numpy as np
import re
from datetime import datetime, timedelta
from typing import Dict, List, Any, Tuple
from collections import Counter

from ..utils.database import db
from ..config import CHAT_ENHANCEMENT

class ConversationAnalyzer:
    """Analyzes chat conversations for health insights and emotional patterns."""
    
    def __init__(self):
        self.config = CHAT_ENHANCEMENT
        self.health_keywords = self.config['health_concern_keywords']
        self.sentiment_threshold = self.config['sentiment_threshold']
        
        # Load sentiment lexicons (simplified)
        self.positive_words = {
            'happy', 'good', 'great', 'wonderful', 'excellent', 'fantastic', 'amazing',
            'pleased', 'satisfied', 'content', 'joyful', 'cheerful', 'optimistic',
            'better', 'improved', 'recovering', 'healing', 'stronger', 'comfortable'
        }
        
        self.negative_words = {
            'sad', 'bad', 'terrible', 'horrible', 'awful', 'worried', 'anxious',
            'depressed', 'frustrated', 'angry', 'upset', 'disappointed', 'lonely',
            'worse', 'painful', 'struggling', 'difficult', 'challenging', 'sick'
        }
        
        # Health concern categories
        self.health_categories = {
            'pain': ['pain', 'hurt', 'ache', 'sore', 'aching', 'painful', 'discomfort'],
            'mood': ['sad', 'depressed', 'anxious', 'worried', 'down', 'blue', 'upset'],
            'sleep': ['tired', 'exhausted', 'sleepy', 'insomnia', 'sleep', 'rest'],
            'mobility': ['walking', 'moving', 'stairs', 'balance', 'fall', 'dizzy'],
            'memory': ['forgot', 'remember', 'memory', 'confused', 'forget'],
            'social': ['lonely', 'isolated', 'alone', 'family', 'friends', 'visit'],
            'medication': ['pills', 'medicine', 'medication', 'dose', 'prescription']
        }
    
    def analyze_message(self, user_id: int, message: str, context: Dict = None) -> Dict[str, Any]:
        """
        Analyze a single chat message for sentiment, health concerns, and insights.
        
        Args:
            user_id: User identifier
            message: The message text to analyze
            context: Additional context about the conversation
            
        Returns:
            Dictionary with analysis results
        """
        try:
            # Clean and preprocess message
            cleaned_message = self._preprocess_message(message)
            
            # Core analysis
            sentiment_analysis = self._analyze_sentiment(cleaned_message)
            health_concerns = self._detect_health_concerns(cleaned_message)
            emotional_state = self._assess_emotional_state(cleaned_message, sentiment_analysis)
            urgency_assessment = self._assess_urgency(cleaned_message, health_concerns)
            
            # Context-based analysis
            conversation_context = self._analyze_conversation_context(user_id, message)
            response_suggestions = self._generate_response_suggestions(
                sentiment_analysis, health_concerns, emotional_state, urgency_assessment
            )
            
            # Follow-up recommendations
            follow_up_actions = self._recommend_follow_up_actions(
                health_concerns, emotional_state, urgency_assessment
            )
            
            return {
                'user_id': user_id,
                'message': message,
                'analysis_timestamp': datetime.now().isoformat(),
                'sentiment_analysis': sentiment_analysis,
                'health_concerns': health_concerns,
                'emotional_state': emotional_state,
                'urgency_assessment': urgency_assessment,
                'conversation_context': conversation_context,
                'response_suggestions': response_suggestions,
                'follow_up_actions': follow_up_actions,
                'message_classification': self._classify_message_type(cleaned_message)
            }
            
        except Exception as e:
            return {
                'error': f'Message analysis failed: {str(e)}',
                'user_id': user_id,
                'message': message,
                'fallback_analysis': self._get_fallback_analysis()
            }
    
    def analyze_conversation_history(self, user_id: int, days: int = 7) -> Dict[str, Any]:
        """Analyze conversation history for patterns and trends."""
        try:
            # Get conversation history
            chat_history = db.get_chat_history(user_id, days)
            
            if not chat_history:
                return {'message': 'No conversation history found'}
            
            # Analyze patterns
            sentiment_trends = self._analyze_sentiment_trends(chat_history)
            health_concern_patterns = self._analyze_health_concern_patterns(chat_history)
            emotional_patterns = self._analyze_emotional_patterns(chat_history)
            engagement_analysis = self._analyze_engagement_patterns(chat_history)
            
            # Generate insights
            conversation_insights = self._generate_conversation_insights(
                sentiment_trends, health_concern_patterns, emotional_patterns
            )
            
            return {
                'user_id': user_id,
                'analysis_period_days': days,
                'total_conversations': len(chat_history),
                'sentiment_trends': sentiment_trends,
                'health_concern_patterns': health_concern_patterns,
                'emotional_patterns': emotional_patterns,
                'engagement_analysis': engagement_analysis,
                'conversation_insights': conversation_insights,
                'recommendations': self._generate_conversation_recommendations(conversation_insights),
                'generated_at': datetime.now().isoformat()
            }
            
        except Exception as e:
            return {'error': f'Conversation history analysis failed: {str(e)}'}
    
    def _preprocess_message(self, message: str) -> str:
        """Clean and preprocess the message text."""
        # Convert to lowercase
        cleaned = message.lower().strip()
        
        # Remove extra whitespace
        cleaned = re.sub(r'\s+', ' ', cleaned)
        
        # Remove special characters but keep basic punctuation
        cleaned = re.sub(r'[^\w\s.,!?-]', '', cleaned)
        
        return cleaned
    
    def _analyze_sentiment(self, message: str) -> Dict[str, Any]:
        """Analyze sentiment of the message using lexicon-based approach."""
        words = set(message.split())
        
        positive_count = len(words.intersection(self.positive_words))
        negative_count = len(words.intersection(self.negative_words))
        total_sentiment_words = positive_count + negative_count
        
        if total_sentiment_words == 0:
            polarity = 0
            sentiment = 'neutral'
        else:
            polarity = (positive_count - negative_count) / total_sentiment_words
            
            if polarity >= self.sentiment_threshold:
                sentiment = 'positive'
            elif polarity <= -self.sentiment_threshold:
                sentiment = 'negative'
            else:
                sentiment = 'neutral'
        
        # Calculate confidence
        confidence = min(1.0, total_sentiment_words / max(1, len(message.split())) + 0.3)
        
        return {
            'sentiment': sentiment,
            'polarity': round(polarity, 3),
            'confidence': round(confidence, 3),
            'positive_indicators': positive_count,
            'negative_indicators': negative_count,
            'sentiment_words_ratio': round(total_sentiment_words / max(1, len(message.split())), 3)
        }
    
    def _detect_health_concerns(self, message: str) -> Dict[str, Any]:
        """Detect health concerns mentioned in the message."""
        detected_concerns = {}
        concern_details = []
        
        # Check each health category
        for category, keywords in self.health_categories.items():
            matches = [keyword for keyword in keywords if keyword in message]
            
            if matches:
                detected_concerns[category] = {
                    'keywords_found': matches,
                    'relevance_score': len(matches) / len(keywords),
                    'context_phrases': self._extract_context_phrases(message, matches)
                }
                
                concern_details.append({
                    'category': category,
                    'severity': self._assess_concern_severity(message, matches),
                    'keywords': matches,
                    'requires_attention': self._requires_immediate_attention(category, matches, message)
                })
        
        # Overall health concern assessment
        if detected_concerns:
            overall_concern_level = 'high' if any(
                concern['requires_attention'] for concern in concern_details
            ) else 'medium' if len(detected_concerns) > 2 else 'low'
        else:
            overall_concern_level = 'none'
        
        return {
            'concerns_detected': detected_concerns,
            'concern_details': concern_details,
            'overall_concern_level': overall_concern_level,
            'total_concerns': len(detected_concerns),
            'immediate_attention_needed': any(
                concern['requires_attention'] for concern in concern_details
            )
        }
    
    def _extract_context_phrases(self, message: str, keywords: List[str]) -> List[str]:
        """Extract context phrases around health keywords."""
        context_phrases = []
        words = message.split()
        
        for keyword in keywords:
            for i, word in enumerate(words):
                if keyword in word:
                    # Extract surrounding context (3 words before and after)
                    start_idx = max(0, i - 3)
                    end_idx = min(len(words), i + 4)
                    context = ' '.join(words[start_idx:end_idx])
                    context_phrases.append(context)
        
        return context_phrases[:3]  # Limit to top 3 context phrases
    
    def _assess_concern_severity(self, message: str, keywords: List[str]) -> str:
        """Assess the severity of health concerns based on context."""
        severity_indicators = {
            'high': ['severe', 'terrible', 'unbearable', 'emergency', 'urgent', 'help'],
            'medium': ['bad', 'worse', 'uncomfortable', 'concerning', 'worried'],
            'low': ['mild', 'slight', 'little', 'okay', 'manageable']
        }
        
        for severity, indicators in severity_indicators.items():
            if any(indicator in message for indicator in indicators):
                return severity
        
        # Default based on number of keywords
        if len(keywords) > 2:
            return 'medium'
        else:
            return 'low'
    
    def _requires_immediate_attention(self, category: str, keywords: List[str], message: str) -> bool:
        """Determine if health concern requires immediate attention."""
        urgent_keywords = ['emergency', 'urgent', 'help', 'can\'t', 'unable', 'severe', 'terrible']
        
        # Category-specific urgent indicators
        urgent_category_indicators = {
            'pain': ['unbearable', 'severe', '10/10', 'emergency'],
            'mood': ['suicide', 'kill', 'die', 'hopeless', 'end'],
            'mobility': ['fall', 'fell', 'can\'t walk', 'stuck'],
            'memory': ['lost', 'can\'t remember', 'confused', 'disoriented']
        }
        
        # Check general urgent keywords
        if any(urgent_word in message for urgent_word in urgent_keywords):
            return True
        
        # Check category-specific urgent indicators
        category_urgents = urgent_category_indicators.get(category, [])
        if any(indicator in message for indicator in category_urgents):
            return True
        
        return False
    
    def _assess_emotional_state(self, message: str, sentiment_analysis: Dict) -> Dict[str, Any]:
        """Assess emotional state beyond basic sentiment."""
        emotional_indicators = {
            'anxiety': ['worried', 'anxious', 'nervous', 'scared', 'fear'],
            'depression': ['sad', 'down', 'blue', 'hopeless', 'empty'],
            'loneliness': ['lonely', 'alone', 'isolated', 'nobody', 'miss'],
            'frustration': ['frustrated', 'angry', 'annoyed', 'irritated'],
            'contentment': ['content', 'peaceful', 'calm', 'satisfied'],
            'excitement': ['excited', 'thrilled', 'enthusiastic', 'eager']
        }
        
        detected_emotions = {}
        primary_emotion = sentiment_analysis['sentiment']
        
        for emotion, indicators in emotional_indicators.items():
            matches = [indicator for indicator in indicators if indicator in message]
            if matches:
                detected_emotions[emotion] = {
                    'indicators': matches,
                    'strength': len(matches) / len(indicators)
                }
        
        # Determine primary emotion
        if detected_emotions:
            primary_emotion = max(detected_emotions.keys(), 
                                key=lambda x: detected_emotions[x]['strength'])
        
        return {
            'primary_emotion': primary_emotion,
            'detected_emotions': detected_emotions,
            'emotional_complexity': len(detected_emotions),
            'emotional_stability': 'stable' if len(detected_emotions) <= 1 else 'complex',
            'support_needed': self._assess_emotional_support_needed(detected_emotions)
        }
    
    def _assess_emotional_support_needed(self, detected_emotions: Dict) -> str:
        """Assess level of emotional support needed."""
        high_support_emotions = ['depression', 'anxiety', 'loneliness']
        medium_support_emotions = ['frustration', 'sadness']
        
        if any(emotion in detected_emotions for emotion in high_support_emotions):
            return 'high'
        elif any(emotion in detected_emotions for emotion in medium_support_emotions):
            return 'medium'
        else:
            return 'low'
    
    def _assess_urgency(self, message: str, health_concerns: Dict) -> Dict[str, Any]:
        """Assess urgency level of the message."""
        urgency_score = 0
        urgency_factors = []
        
        # Health concern urgency
        if health_concerns['immediate_attention_needed']:
            urgency_score += 3
            urgency_factors.append('immediate_health_attention_needed')
        elif health_concerns['overall_concern_level'] == 'high':
            urgency_score += 2
            urgency_factors.append('high_health_concerns')
        
        # Urgent language patterns
        urgent_patterns = [
            r'\bhelp\b', r'\bemergency\b', r'\burgent\b', r'\bcan\'?t\b',
            r'\bunable\b', r'\bimmediate\b', r'\bright now\b'
        ]
        
        for pattern in urgent_patterns:
            if re.search(pattern, message):
                urgency_score += 1
                urgency_factors.append(f'urgent_language: {pattern}')
        
        # Determine urgency level
        if urgency_score >= 3:
            urgency_level = 'critical'
        elif urgency_score >= 2:
            urgency_level = 'high'
        elif urgency_score >= 1:
            urgency_level = 'medium'
        else:
            urgency_level = 'low'
        
        return {
            'urgency_level': urgency_level,
            'urgency_score': urgency_score,
            'urgency_factors': urgency_factors,
            'requires_escalation': urgency_level in ['critical', 'high'],
            'response_time_recommendation': self._get_response_time_recommendation(urgency_level)
        }
    
    def _get_response_time_recommendation(self, urgency_level: str) -> str:
        """Get recommended response time based on urgency."""
        time_recommendations = {
            'critical': 'immediate (within 5 minutes)',
            'high': 'urgent (within 15 minutes)',
            'medium': 'prompt (within 1 hour)',
            'low': 'standard (within 4 hours)'
        }
        return time_recommendations.get(urgency_level, 'standard')
    
    def _analyze_conversation_context(self, user_id: int, current_message: str) -> Dict[str, Any]:
        """Analyze current message in context of recent conversation history."""
        try:
            # Get recent chat history
            recent_chats = db.get_chat_history(user_id, 1)  # Last 24 hours
            
            context = {
                'conversation_frequency': 'low',
                'topic_continuity': 'new_topic',
                'engagement_level': 'unknown',
                'previous_concerns': []
            }
            
            if not recent_chats:
                return context
            
            # Analyze conversation frequency
            if len(recent_chats) > 5:
                context['conversation_frequency'] = 'high'
            elif len(recent_chats) > 2:
                context['conversation_frequency'] = 'moderate'
            
            # Check for topic continuity
            if len(recent_chats) > 0:
                last_message = recent_chats[0].get('message', '').lower()
                current_words = set(current_message.lower().split())
                last_words = set(last_message.split())
                
                common_words = current_words.intersection(last_words)
                if len(common_words) > 2:
                    context['topic_continuity'] = 'continuing'
            
            # Analyze previous health concerns
            for chat in recent_chats[:5]:  # Last 5 messages
                previous_concerns = self._detect_health_concerns(chat.get('message', ''))
                if previous_concerns['total_concerns'] > 0:
                    context['previous_concerns'].extend(
                        previous_concerns['concern_details']
                    )
            
            return context
            
        except Exception:
            return {'status': 'analysis_failed'}
    
    def _generate_response_suggestions(self, sentiment_analysis: Dict, 
                                     health_concerns: Dict, 
                                     emotional_state: Dict, 
                                     urgency_assessment: Dict) -> List[Dict[str, Any]]:
        """Generate suggestions for how to respond to the message."""
        suggestions = []
        
        # Urgency-based suggestions
        if urgency_assessment['urgency_level'] == 'critical':
            suggestions.append({
                'type': 'emergency_response',
                'priority': 'critical',
                'suggestion': 'Immediate escalation to healthcare provider recommended',
                'action': 'emergency_protocol'
            })
        
        # Health concern suggestions
        if health_concerns['immediate_attention_needed']:
            suggestions.append({
                'type': 'health_attention',
                'priority': 'high',
                'suggestion': 'Acknowledge health concerns and suggest contacting healthcare provider',
                'action': 'health_escalation'
            })
        
        # Emotional support suggestions
        support_needed = emotional_state.get('support_needed', 'low')
        if support_needed == 'high':
            suggestions.append({
                'type': 'emotional_support',
                'priority': 'high',
                'suggestion': 'Provide empathetic response and emotional validation',
                'action': 'emotional_support_protocol'
            })
        
        # Sentiment-based suggestions
        if sentiment_analysis['sentiment'] == 'negative':
            suggestions.append({
                'type': 'mood_support',
                'priority': 'medium',
                'suggestion': 'Offer encouragement and suggest mood-boosting activities',
                'action': 'mood_support'
            })
        elif sentiment_analysis['sentiment'] == 'positive':
            suggestions.append({
                'type': 'positive_reinforcement',
                'priority': 'low',
                'suggestion': 'Acknowledge positive mood and encourage continued wellness',
                'action': 'positive_reinforcement'
            })
        
        return suggestions
    
    def _recommend_follow_up_actions(self, health_concerns: Dict, 
                                   emotional_state: Dict, 
                                   urgency_assessment: Dict) -> List[Dict[str, Any]]:
        """Recommend follow-up actions based on analysis."""
        actions = []
        
        # Health-based follow-ups
        if health_concerns['total_concerns'] > 0:
            actions.append({
                'action': 'health_check_in',
                'timeframe': '24_hours',
                'priority': 'medium',
                'description': 'Follow up on mentioned health concerns'
            })
        
        # Emotional follow-ups
        if emotional_state.get('support_needed') == 'high':
            actions.append({
                'action': 'emotional_check_in',
                'timeframe': '12_hours',
                'priority': 'high',
                'description': 'Check on emotional well-being and provide support'
            })
        
        # Urgency-based follow-ups
        if urgency_assessment['requires_escalation']:
            actions.append({
                'action': 'escalation_follow_up',
                'timeframe': '2_hours',
                'priority': 'critical',
                'description': 'Verify that urgent issues have been addressed'
            })
        
        return actions
    
    def _classify_message_type(self, message: str) -> str:
        """Classify the type of message."""
        if any(word in message for word in ['?', 'what', 'how', 'when', 'where', 'why']):
            return 'question'
        elif any(word in message for word in ['pain', 'hurt', 'sick', 'worried', 'anxious']):
            return 'health_concern'
        elif any(word in message for word in ['happy', 'good', 'great', 'wonderful']):
            return 'positive_sharing'
        elif any(word in message for word in ['help', 'need', 'can you']):
            return 'request_for_help'
        else:
            return 'general_conversation'
    
    def _analyze_sentiment_trends(self, chat_history: List[Dict]) -> Dict[str, Any]:
        """Analyze sentiment trends over conversation history."""
        sentiments = []
        
        for chat in chat_history:
            message = chat.get('message', '')
            sentiment_result = self._analyze_sentiment(message)
            sentiments.append({
                'sentiment': sentiment_result['sentiment'],
                'polarity': sentiment_result['polarity'],
                'timestamp': chat.get('created_at', datetime.now())
            })
        
        if not sentiments:
            return {'status': 'no_data'}
        
        # Calculate trend
        recent_sentiment = np.mean([s['polarity'] for s in sentiments[-3:]])
        older_sentiment = np.mean([s['polarity'] for s in sentiments[:-3]]) if len(sentiments) > 3 else recent_sentiment
        
        return {
            'current_sentiment': sentiments[-1]['sentiment'],
            'average_polarity': round(np.mean([s['polarity'] for s in sentiments]), 3),
            'sentiment_trend': 'improving' if recent_sentiment > older_sentiment else 'declining' if recent_sentiment < older_sentiment else 'stable',
            'positive_conversations': sum(1 for s in sentiments if s['sentiment'] == 'positive'),
            'negative_conversations': sum(1 for s in sentiments if s['sentiment'] == 'negative'),
            'neutral_conversations': sum(1 for s in sentiments if s['sentiment'] == 'neutral')
        }
    
    def _analyze_health_concern_patterns(self, chat_history: List[Dict]) -> Dict[str, Any]:
        """Analyze patterns in health concerns over time."""
        all_concerns = []
        concern_frequency = Counter()
        
        for chat in chat_history:
            message = chat.get('message', '')
            concerns = self._detect_health_concerns(message)
            
            for concern_detail in concerns['concern_details']:
                category = concern_detail['category']
                all_concerns.append({
                    'category': category,
                    'severity': concern_detail['severity'],
                    'timestamp': chat.get('created_at', datetime.now())
                })
                concern_frequency[category] += 1
        
        return {
            'total_health_mentions': len(all_concerns),
            'most_common_concerns': dict(concern_frequency.most_common(5)),
            'concerning_patterns': self._identify_concerning_patterns(all_concerns),
            'health_focus_areas': list(concern_frequency.keys())
        }
    
    def _identify_concerning_patterns(self, concerns: List[Dict]) -> List[str]:
        """Identify concerning patterns in health mentions."""
        patterns = []
        
        if len(concerns) > 5:
            patterns.append('frequent_health_mentions')
        
        # Check for recurring severe concerns
        severe_concerns = [c for c in concerns if c['severity'] == 'high']
        if len(severe_concerns) > 2:
            patterns.append('recurring_severe_concerns')
        
        # Check for pain pattern
        pain_mentions = [c for c in concerns if c['category'] == 'pain']
        if len(pain_mentions) > 3:
            patterns.append('chronic_pain_pattern')
        
        return patterns
    
    def _analyze_emotional_patterns(self, chat_history: List[Dict]) -> Dict[str, Any]:
        """Analyze emotional patterns in conversations."""
        emotional_timeline = []
        
        for chat in chat_history:
            message = chat.get('message', '')
            sentiment_result = self._analyze_sentiment(message)
            emotional_state = self._assess_emotional_state(message, sentiment_result)
            
            emotional_timeline.append({
                'primary_emotion': emotional_state['primary_emotion'],
                'emotional_complexity': emotional_state['emotional_complexity'],
                'timestamp': chat.get('created_at', datetime.now())
            })
        
        return {
            'emotional_stability': 'stable' if all(e['emotional_complexity'] <= 1 for e in emotional_timeline) else 'variable',
            'dominant_emotions': Counter([e['primary_emotion'] for e in emotional_timeline]).most_common(3),
            'emotional_support_frequency': sum(1 for e in emotional_timeline if e['emotional_complexity'] > 1)
        }
    
    def _analyze_engagement_patterns(self, chat_history: List[Dict]) -> Dict[str, Any]:
        """Analyze user engagement patterns."""
        if not chat_history:
            return {'status': 'no_data'}
        
        # Message lengths
        message_lengths = [len(chat.get('message', '').split()) for chat in chat_history]
        
        # Time between conversations
        timestamps = [pd.to_datetime(chat.get('created_at', datetime.now())) for chat in chat_history]
        timestamps.sort()
        
        time_gaps = [(timestamps[i] - timestamps[i-1]).total_seconds() / 3600 for i in range(1, len(timestamps))]
        
        return {
            'average_message_length': round(np.mean(message_lengths), 1),
            'message_length_trend': 'increasing' if message_lengths[-1] > np.mean(message_lengths[:-1]) else 'stable',
            'conversation_frequency': 'high' if len(chat_history) > 5 else 'moderate' if len(chat_history) > 2 else 'low',
            'average_time_between_conversations': round(np.mean(time_gaps), 1) if time_gaps else 0,
            'engagement_level': 'high' if np.mean(message_lengths) > 10 else 'moderate'
        }
    
    def _generate_conversation_insights(self, sentiment_trends: Dict, 
                                      health_patterns: Dict, 
                                      emotional_patterns: Dict) -> List[str]:
        """Generate insights from conversation analysis."""
        insights = []
        
        # Sentiment insights
        if sentiment_trends.get('sentiment_trend') == 'declining':
            insights.append("User's mood appears to be declining over recent conversations")
        
        # Health insights
        common_concerns = health_patterns.get('most_common_concerns', {})
        if common_concerns:
            top_concern = list(common_concerns.keys())[0]
            insights.append(f"Most frequently mentioned health concern: {top_concern}")
        
        # Emotional insights
        if emotional_patterns.get('emotional_stability') == 'variable':
            insights.append("User shows variable emotional states - may benefit from additional support")
        
        return insights
    
    def _generate_conversation_recommendations(self, insights: List[str]) -> List[Dict[str, Any]]:
        """Generate recommendations based on conversation insights."""
        recommendations = []
        
        for insight in insights:
            if 'declining' in insight:
                recommendations.append({
                    'type': 'mood_support',
                    'priority': 'high',
                    'suggestion': 'Increase emotional support and check-ins',
                    'action': 'enhanced_mood_monitoring'
                })
            elif 'health concern' in insight:
                recommendations.append({
                    'type': 'health_focus',
                    'priority': 'medium',
                    'suggestion': 'Address recurring health concerns proactively',
                    'action': 'health_pattern_intervention'
                })
            elif 'emotional' in insight:
                recommendations.append({
                    'type': 'emotional_support',
                    'priority': 'medium',
                    'suggestion': 'Provide consistent emotional validation and support',
                    'action': 'emotional_stability_support'
                })
        
        return recommendations
    
    def _get_fallback_analysis(self) -> Dict[str, Any]:
        """Provide fallback analysis when full analysis fails."""
        return {
            'sentiment_analysis': {'sentiment': 'neutral', 'confidence': 0.3},
            'health_concerns': {'overall_concern_level': 'unknown'},
            'emotional_state': {'primary_emotion': 'neutral', 'support_needed': 'low'},
            'urgency_assessment': {'urgency_level': 'low'},
            'response_suggestions': [
                {'type': 'general_support', 'suggestion': 'Provide supportive response'}
            ]
        }