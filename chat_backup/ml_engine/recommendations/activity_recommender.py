"""
Activity Recommender Module

Intelligent activity recommendation system for senior care management.
Uses collaborative filtering, content-based filtering, and health data
to suggest personalized activities that improve wellness outcomes.
"""

import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from sklearn.metrics.pairwise import cosine_similarity
from sklearn.preprocessing import StandardScaler
from sklearn.cluster import KMeans
import warnings
warnings.filterwarnings('ignore')

from ..utils.database import db
from ..config import ACTIVITY_RECOMMENDATIONS

class ActivityRecommender:
    """Generates personalized activity recommendations using machine learning."""
    
    def __init__(self):
        self.config = ACTIVITY_RECOMMENDATIONS
        self.scaler = StandardScaler()
        self.user_clusters = None
        self.activity_features = None
        
    def get_recommendations(self, user_id: int, limit: int = 5) -> dict:
        """
        Get personalized activity recommendations for a user.
        
        Args:
            user_id: User identifier
            limit: Maximum number of recommendations
            
        Returns:
            Dictionary with personalized recommendations
        """
        try:
            # Get user data
            user_health = db.get_user_health_data(user_id, 30)
            user_activities = db.get_user_activities(user_id, 90)
            user_profile = self._build_user_profile(user_id, user_health, user_activities)
            
            # Generate recommendations using multiple approaches
            collaborative_recs = self._collaborative_filtering(user_id, limit)
            content_recs = self._content_based_filtering(user_profile, limit)
            health_recs = self._health_based_recommendations(user_health, limit)
            mood_recs = self._mood_based_recommendations(user_health, limit)
            
            # Combine and rank recommendations
            combined_recs = self._combine_recommendations(
                collaborative_recs, content_recs, health_recs, mood_recs
            )
            
            # Final ranking and filtering
            final_recommendations = self._rank_and_filter_recommendations(
                combined_recs, user_profile, limit
            )
            
            return {
                'user_id': user_id,
                'recommendations': final_recommendations,
                'recommendation_methods': {
                    'collaborative_filtering': len(collaborative_recs),
                    'content_based': len(content_recs),
                    'health_based': len(health_recs),
                    'mood_based': len(mood_recs)
                },
                'user_profile_summary': self._summarize_user_profile(user_profile),
                'generated_at': datetime.now().isoformat()
            }
            
        except Exception as e:
            return {'error': f'Activity recommendation failed: {str(e)}'}
    
    def _build_user_profile(self, user_id: int, health_data: pd.DataFrame, 
                          activity_data: pd.DataFrame) -> dict:
        """Build comprehensive user profile for recommendations."""
        profile = {
            'user_id': user_id,
            'health_status': {},
            'activity_preferences': {},
            'participation_patterns': {},
            'constraints': []
        }
        
        # Health status analysis
        if not health_data.empty:
            profile['health_status'] = {
                'current_mood': health_data['mood_score'].dropna().tail(3).mean() if 'mood_score' in health_data.columns else 5,
                'average_pain': health_data['pain_level'].dropna().tail(7).mean() if 'pain_level' in health_data.columns else 3,
                'energy_level': health_data['energy_level'].dropna().tail(7).mean() if 'energy_level' in health_data.columns else 5,
                'physical_activity_minutes': health_data['physical_activity_minutes'].dropna().tail(7).mean() if 'physical_activity_minutes' in health_data.columns else 15,
                'sleep_quality': health_data['sleep_hours'].dropna().tail(7).mean() if 'sleep_hours' in health_data.columns else 7
            }
        
        # Activity preferences from historical data
        if not activity_data.empty:
            # Most participated categories
            category_counts = activity_data['category'].value_counts()
            
            # Average mood improvement by category
            mood_improvements = activity_data.groupby('category').apply(
                lambda x: (x['mood_after'] - x['mood_before']).mean()
                if 'mood_after' in x.columns and 'mood_before' in x.columns else 0
            )
            
            profile['activity_preferences'] = {
                'preferred_categories': category_counts.head(3).index.tolist(),
                'most_effective_categories': mood_improvements.nlargest(3).index.tolist(),
                'participation_frequency': len(activity_data) / 90,  # per day over 90 days
                'average_mood_improvement': mood_improvements.mean()
            }
            
            # Participation patterns
            if 'participation_date' in activity_data.columns:
                activity_data['participation_date'] = pd.to_datetime(activity_data['participation_date'])
                activity_data['weekday'] = activity_data['participation_date'].dt.dayofweek
                activity_data['hour'] = activity_data['participation_date'].dt.hour
                
                profile['participation_patterns'] = {
                    'preferred_weekdays': activity_data['weekday'].value_counts().head(3).index.tolist(),
                    'preferred_times': activity_data['hour'].value_counts().head(3).index.tolist(),
                    'most_active_day': activity_data['weekday'].value_counts().idxmax(),
                    'recent_activity_level': len(activity_data[activity_data['participation_date'] > datetime.now() - timedelta(days=14)])
                }
        
        # Health-based constraints
        current_pain = profile['health_status'].get('average_pain', 3)
        current_mood = profile['health_status'].get('current_mood', 5)
        
        if current_pain > 6:
            profile['constraints'].append('high_pain')
        if current_mood < 4:
            profile['constraints'].append('low_mood')
        if profile['health_status'].get('energy_level', 5) < 4:
            profile['constraints'].append('low_energy')
        
        return profile
    
    def _collaborative_filtering(self, user_id: int, limit: int) -> list:
        """Generate recommendations using collaborative filtering."""
        try:
            # Get participation matrix
            participation_data = db.get_activity_participation_matrix(90)
            
            if participation_data.empty:
                return []
            
            # Create user-activity matrix
            user_activity_matrix = participation_data.pivot_table(
                index='user_id', 
                columns='activity_id', 
                values='participation_count',
                fill_value=0
            )
            
            if user_id not in user_activity_matrix.index:
                return []
            
            # Calculate user similarities
            user_similarities = cosine_similarity(user_activity_matrix)
            user_similarity_df = pd.DataFrame(
                user_similarities,
                index=user_activity_matrix.index,
                columns=user_activity_matrix.index
            )
            
            # Find similar users
            similar_users = user_similarity_df[user_id].drop(user_id).nlargest(5)
            
            if similar_users.empty:
                return []
            
            # Get activities liked by similar users but not tried by target user
            target_user_activities = set(user_activity_matrix.loc[user_id][user_activity_matrix.loc[user_id] > 0].index)
            
            recommendations = []
            for similar_user_id, similarity_score in similar_users.items():
                similar_user_activities = user_activity_matrix.loc[similar_user_id]
                
                # Get highly rated activities from similar user
                high_rated_activities = similar_user_activities[similar_user_activities >= 2]
                
                for activity_id, rating in high_rated_activities.items():
                    if activity_id not in target_user_activities:
                        recommendations.append({
                            'activity_id': activity_id,
                            'predicted_rating': rating * similarity_score,
                            'method': 'collaborative_filtering',
                            'confidence': similarity_score
                        })
            
            # Sort by predicted rating and return top recommendations
            recommendations.sort(key=lambda x: x['predicted_rating'], reverse=True)
            return recommendations[:limit]
            
        except Exception as e:
            print(f"Collaborative filtering error: {e}")
            return []
    
    def _content_based_filtering(self, user_profile: dict, limit: int) -> list:
        """Generate recommendations using content-based filtering."""
        try:
            # Get all available activities
            activities_query = """
            SELECT id, title, category, description, 
                   physical_intensity, social_component, 
                   indoor_outdoor, duration_minutes, mood_impact
            FROM activites 
            WHERE is_active = 1
            """
            
            activities = db.execute_query(activities_query)
            
            if activities.empty:
                return []
            
            # Calculate content similarity based on user preferences
            recommendations = []
            preferred_categories = user_profile.get('activity_preferences', {}).get('preferred_categories', [])
            
            for _, activity in activities.iterrows():
                score = 0
                
                # Category preference matching
                if activity['category'] in preferred_categories:
                    score += 0.4
                
                # Health status compatibility
                physical_intensity = activity.get('physical_intensity', 3)
                user_pain = user_profile.get('health_status', {}).get('average_pain', 3)
                
                # Adjust for pain level
                if user_pain > 6 and physical_intensity <= 2:
                    score += 0.3  # Low intensity good for high pain
                elif user_pain <= 3 and physical_intensity >= 4:
                    score += 0.2  # High intensity ok for low pain
                elif abs(physical_intensity - (5 - user_pain)) <= 1:
                    score += 0.2  # Moderate matching
                
                # Mood compatibility
                current_mood = user_profile.get('health_status', {}).get('current_mood', 5)
                mood_impact = activity.get('mood_impact', 0)
                
                if current_mood < 5 and mood_impact > 0:
                    score += 0.3  # Mood-boosting activities for low mood
                
                # Social component for isolation
                if len(user_profile.get('constraints', [])) > 0 and activity.get('social_component', False):
                    score += 0.2
                
                recommendations.append({
                    'activity_id': activity['id'],
                    'title': activity['title'],
                    'category': activity['category'],
                    'predicted_rating': score,
                    'method': 'content_based_filtering',
                    'confidence': min(1.0, score)
                })
            
            # Sort by predicted rating
            recommendations.sort(key=lambda x: x['predicted_rating'], reverse=True)
            return recommendations[:limit]
            
        except Exception as e:
            print(f"Content-based filtering error: {e}")
            return []
    
    def _health_based_recommendations(self, health_data: pd.DataFrame, limit: int) -> list:
        """Generate recommendations based on current health status."""
        recommendations = []
        
        if health_data.empty:
            return recommendations
        
        # Analyze recent health trends
        recent_mood = health_data['mood_score'].dropna().tail(3).mean() if 'mood_score' in health_data.columns else 5
        recent_pain = health_data['pain_level'].dropna().tail(3).mean() if 'pain_level' in health_data.columns else 3
        recent_energy = health_data['energy_level'].dropna().tail(3).mean() if 'energy_level' in health_data.columns else 5
        
        # Health-specific activity mappings
        health_activity_map = {
            'low_mood': [
                {'category': 'social_interaction', 'priority': 0.9, 'reason': 'Combat social isolation'},
                {'category': 'music_therapy', 'priority': 0.8, 'reason': 'Mood elevation through music'},
                {'category': 'pet_therapy', 'priority': 0.7, 'reason': 'Emotional support from animals'},
                {'category': 'art_therapy', 'priority': 0.7, 'reason': 'Creative expression for mood'}
            ],
            'high_pain': [
                {'category': 'gentle_exercise', 'priority': 0.8, 'reason': 'Low-impact movement for pain management'},
                {'category': 'relaxation', 'priority': 0.9, 'reason': 'Stress reduction and pain relief'},
                {'category': 'meditation', 'priority': 0.8, 'reason': 'Mindfulness for pain coping'},
                {'category': 'warm_water_therapy', 'priority': 0.7, 'reason': 'Therapeutic benefits of warm water'}
            ],
            'low_energy': [
                {'category': 'seated_activities', 'priority': 0.9, 'reason': 'Low energy requirement'},
                {'category': 'reading', 'priority': 0.8, 'reason': 'Mental stimulation without physical strain'},
                {'category': 'crafts', 'priority': 0.7, 'reason': 'Engaging hand activities'},
                {'category': 'light_stretching', 'priority': 0.6, 'reason': 'Gentle movement to boost energy'}
            ]
        }
        
        # Generate recommendations based on health conditions
        if recent_mood < 4:
            recommendations.extend([
                {
                    'category': rec['category'],
                    'predicted_rating': rec['priority'],
                    'method': 'health_based_recommendation',
                    'confidence': rec['priority'],
                    'health_reason': rec['reason'],
                    'health_condition': 'low_mood'
                }
                for rec in health_activity_map['low_mood']
            ])
        
        if recent_pain > 6:
            recommendations.extend([
                {
                    'category': rec['category'],
                    'predicted_rating': rec['priority'],
                    'method': 'health_based_recommendation',
                    'confidence': rec['priority'],
                    'health_reason': rec['reason'],
                    'health_condition': 'high_pain'
                }
                for rec in health_activity_map['high_pain']
            ])
        
        if recent_energy < 4:
            recommendations.extend([
                {
                    'category': rec['category'],
                    'predicted_rating': rec['priority'],
                    'method': 'health_based_recommendation',
                    'confidence': rec['priority'],
                    'health_reason': rec['reason'],
                    'health_condition': 'low_energy'
                }
                for rec in health_activity_map['low_energy']
            ])
        
        # Sort by priority and return top recommendations
        recommendations.sort(key=lambda x: x['predicted_rating'], reverse=True)
        return recommendations[:limit]
    
    def _mood_based_recommendations(self, health_data: pd.DataFrame, limit: int) -> list:
        """Generate mood-specific activity recommendations."""
        if health_data.empty or 'mood_score' not in health_data.columns:
            return []
        
        recent_moods = health_data['mood_score'].dropna().tail(7)
        
        if recent_moods.empty:
            return []
        
        avg_mood = recent_moods.mean()
        mood_trend = 'declining' if recent_moods.diff().dropna().mean() < -0.1 else 'stable'
        
        mood_recommendations = self.config['mood_activity_mapping']
        recommendations = []
        
        # Mood-based activity selection
        if avg_mood < 4:  # Depression range
            for activity in mood_recommendations.get('depression', []):
                recommendations.append({
                    'activity_type': activity,
                    'predicted_rating': 0.8,
                    'method': 'mood_based_recommendation',
                    'confidence': 0.8,
                    'mood_condition': 'depression',
                    'target_mood_improvement': 2.0
                })
        
        elif avg_mood < 6:  # Anxiety/low mood range
            for activity in mood_recommendations.get('anxiety', []):
                recommendations.append({
                    'activity_type': activity,
                    'predicted_rating': 0.7,
                    'method': 'mood_based_recommendation',
                    'confidence': 0.7,
                    'mood_condition': 'anxiety',
                    'target_mood_improvement': 1.5
                })
        
        # Trend-based recommendations
        if mood_trend == 'declining':
            recommendations.append({
                'activity_type': 'intervention_activities',
                'predicted_rating': 0.9,
                'method': 'mood_based_recommendation',
                'confidence': 0.8,
                'mood_condition': 'declining_trend',
                'target_mood_improvement': 2.5
            })
        
        return recommendations[:limit]
    
    def _combine_recommendations(self, *recommendation_lists) -> list:
        """Combine recommendations from multiple methods."""
        all_recommendations = []
        
        for rec_list in recommendation_lists:
            all_recommendations.extend(rec_list)
        
        # Group by activity/category and aggregate scores
        combined_recs = {}
        
        for rec in all_recommendations:
            key = rec.get('activity_id') or rec.get('category') or rec.get('activity_type')
            
            if key not in combined_recs:
                combined_recs[key] = {
                    'identifier': key,
                    'total_score': 0,
                    'methods': [],
                    'confidence_scores': [],
                    'reasons': []
                }
            
            combined_recs[key]['total_score'] += rec.get('predicted_rating', 0)
            combined_recs[key]['methods'].append(rec.get('method', 'unknown'))
            combined_recs[key]['confidence_scores'].append(rec.get('confidence', 0.5))
            
            # Add specific reasons
            if 'health_reason' in rec:
                combined_recs[key]['reasons'].append(rec['health_reason'])
            if 'mood_condition' in rec:
                combined_recs[key]['reasons'].append(f"For {rec['mood_condition']}")
        
        # Convert back to list and calculate final scores
        final_recommendations = []
        for key, data in combined_recs.items():
            avg_confidence = np.mean(data['confidence_scores'])
            method_diversity_bonus = len(set(data['methods'])) * 0.1
            
            final_recommendations.append({
                'identifier': key,
                'final_score': data['total_score'] + method_diversity_bonus,
                'average_confidence': round(avg_confidence, 3),
                'recommendation_methods': list(set(data['methods'])),
                'reasons': data['reasons'][:3],  # Limit to top 3 reasons
                'method_count': len(data['methods'])
            })
        
        return final_recommendations
    
    def _rank_and_filter_recommendations(self, combined_recs: list, 
                                       user_profile: dict, limit: int) -> list:
        """Final ranking and filtering of recommendations."""
        # Sort by final score
        combined_recs.sort(key=lambda x: x['final_score'], reverse=True)
        
        # Apply constraints and filters
        filtered_recs = []
        user_constraints = user_profile.get('constraints', [])
        
        for rec in combined_recs[:limit * 2]:  # Get extra to allow for filtering
            # Check constraints
            skip_recommendation = False
            
            # High pain constraint
            if 'high_pain' in user_constraints:
                high_intensity_activities = ['sports', 'dancing', 'hiking']
                if any(activity in str(rec['identifier']).lower() for activity in high_intensity_activities):
                    skip_recommendation = True
            
            # Low energy constraint
            if 'low_energy' in user_constraints:
                high_energy_activities = ['aerobics', 'swimming', 'group_fitness']
                if any(activity in str(rec['identifier']).lower() for activity in high_energy_activities):
                    skip_recommendation = True
            
            if not skip_recommendation:
                # Enhance with timing recommendations
                optimal_timing = self._get_optimal_timing(user_profile)
                
                enhanced_rec = {
                    'activity_identifier': rec['identifier'],
                    'confidence_score': rec['average_confidence'],
                    'recommendation_strength': 'high' if rec['final_score'] > 1.5 else 'medium',
                    'reasons': rec['reasons'],
                    'methods_used': rec['recommendation_methods'],
                    'optimal_timing': optimal_timing,
                    'expected_benefits': self._get_expected_benefits(rec, user_profile),
                    'personalization_score': round(rec['final_score'], 2)
                }
                
                filtered_recs.append(enhanced_rec)
                
                if len(filtered_recs) >= limit:
                    break
        
        return filtered_recs
    
    def _get_optimal_timing(self, user_profile: dict) -> dict:
        """Suggest optimal timing for activities based on user patterns."""
        patterns = user_profile.get('participation_patterns', {})
        time_weights = self.config['time_preference_weights']
        
        # Default timing if no patterns available
        if not patterns:
            return {
                'best_time_of_day': 'morning',
                'best_days_of_week': ['Tuesday', 'Wednesday', 'Thursday'],
                'confidence': 0.3
            }
        
        # Convert preferred weekdays to day names
        weekday_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
        preferred_days = [weekday_names[day] for day in patterns.get('preferred_weekdays', [1, 2, 3])]
        
        # Convert preferred hours to time periods
        preferred_hours = patterns.get('preferred_times', [10, 14, 16])
        if any(hour < 12 for hour in preferred_hours):
            best_time = 'morning'
        elif any(12 <= hour < 18 for hour in preferred_hours):
            best_time = 'afternoon'
        else:
            best_time = 'evening'
        
        return {
            'best_time_of_day': best_time,
            'best_days_of_week': preferred_days[:3],
            'confidence': 0.8 if len(patterns) > 0 else 0.3
        }
    
    def _get_expected_benefits(self, recommendation: dict, user_profile: dict) -> list:
        """Calculate expected benefits from the activity."""
        benefits = []
        
        # Mood benefits
        current_mood = user_profile.get('health_status', {}).get('current_mood', 5)
        if current_mood < 6:
            benefits.append({
                'type': 'mood_improvement',
                'expected_change': '+1.5 to +2.0 mood points',
                'confidence': 0.7
            })
        
        # Physical benefits
        current_pain = user_profile.get('health_status', {}).get('average_pain', 3)
        if 'gentle' in str(recommendation['identifier']).lower() or 'relaxation' in str(recommendation['identifier']).lower():
            benefits.append({
                'type': 'pain_reduction',
                'expected_change': '-0.5 to -1.0 pain points',
                'confidence': 0.6
            })
        
        # Social benefits
        if 'social' in str(recommendation['identifier']).lower():
            benefits.append({
                'type': 'social_connection',
                'expected_change': 'Increased social interaction',
                'confidence': 0.8
            })
        
        # Energy benefits
        if 'energy' in recommendation.get('reasons', []):
            benefits.append({
                'type': 'energy_boost',
                'expected_change': '+1.0 to +1.5 energy points',
                'confidence': 0.6
            })
        
        return benefits
    
    def _summarize_user_profile(self, user_profile: dict) -> dict:
        """Create a summary of user profile for transparency."""
        return {
            'health_status_summary': {
                'mood_level': user_profile.get('health_status', {}).get('current_mood', 'unknown'),
                'pain_level': user_profile.get('health_status', {}).get('average_pain', 'unknown'),
                'energy_level': user_profile.get('health_status', {}).get('energy_level', 'unknown')
            },
            'activity_preferences': user_profile.get('activity_preferences', {}).get('preferred_categories', []),
            'participation_frequency': user_profile.get('activity_preferences', {}).get('participation_frequency', 0),
            'constraints': user_profile.get('constraints', []),
            'recent_activity_level': user_profile.get('participation_patterns', {}).get('recent_activity_level', 0)
        }