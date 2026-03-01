"""
Mood Analyzer Module

Advanced mood pattern analysis and mental health monitoring
for senior care management.
"""

import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from sklearn.cluster import KMeans
from sklearn.linear_model import LinearRegression
import warnings
warnings.filterwarnings('ignore')

from ..utils.database import db
from ..config import HEALTH_ANALYTICS

class MoodAnalyzer:
    """Analyzes mood patterns and predicts mental health changes."""
    
    def __init__(self):
        self.config = HEALTH_ANALYTICS
        self.depression_threshold = self.config['alert_thresholds']['mood_depression_threshold']
    
    def analyze_mood_patterns(self, user_id: int, days: int = 7) -> dict:
        """Comprehensive mood pattern analysis for a user."""
        try:
            # Get health data including mood scores
            health_data = db.get_user_health_data(user_id, days)
            
            if health_data.empty or 'mood_score' not in health_data.columns:
                return {'message': 'No mood data available for analysis'}
            
            mood_data = health_data['mood_score'].dropna()
            
            if mood_data.empty:
                return {'message': 'No mood scores found in the specified period'}
            
            # Core mood analysis
            mood_statistics = self._calculate_mood_statistics(mood_data)
            trend_analysis = self._analyze_mood_trends(mood_data)
            volatility_analysis = self._analyze_mood_volatility(mood_data)
            
            # Contextual analysis
            activity_correlation = self._analyze_mood_activity_correlation(user_id, health_data)
            sleep_correlation = self._analyze_mood_sleep_correlation(health_data)
            temporal_patterns = self._analyze_temporal_patterns(health_data)
            
            # Risk assessment
            mental_health_risk = self._assess_mental_health_risk(mood_data, health_data)
            
            # Predictions
            mood_predictions = self._predict_mood_changes(mood_data)
            
            return {
                'user_id': user_id,
                'analysis_period_days': days,
                'mood_statistics': mood_statistics,
                'trend_analysis': trend_analysis,
                'volatility_analysis': volatility_analysis,
                'correlations': {
                    'activity': activity_correlation,
                    'sleep': sleep_correlation
                },
                'temporal_patterns': temporal_patterns,
                'mental_health_risk': mental_health_risk,
                'predictions': mood_predictions,
                'recommendations': self._generate_mood_recommendations(mood_data, mental_health_risk),
                'generated_at': datetime.now().isoformat()
            }
            
        except Exception as e:
            return {'error': f'Mood analysis failed: {str(e)}'}
    
    def _calculate_mood_statistics(self, mood_data: pd.Series) -> dict:
        """Calculate basic mood statistics."""
        return {
            'current_mood': float(mood_data.iloc[-1]) if len(mood_data) > 0 else None,
            'average_mood': round(mood_data.mean(), 2),
            'median_mood': round(mood_data.median(), 2),
            'min_mood': float(mood_data.min()),
            'max_mood': float(mood_data.max()),
            'mood_range': float(mood_data.max() - mood_data.min()),
            'data_points': len(mood_data),
            'below_threshold_days': int((mood_data <= self.depression_threshold).sum()),
            'good_mood_days': int((mood_data >= 7).sum()),
            'mood_consistency': round(mood_data.std(), 2)
        }
    
    def _analyze_mood_trends(self, mood_data: pd.Series) -> dict:
        """Analyze mood trends over time."""
        if len(mood_data) < 3:
            return {'status': 'insufficient_data', 'trend': 'unknown'}
        
        # Linear trend analysis
        X = np.arange(len(mood_data)).reshape(-1, 1)
        y = mood_data.values
        
        model = LinearRegression()
        model.fit(X, y)
        
        trend_slope = model.coef_[0]
        trend_strength = abs(trend_slope)
        r_squared = model.score(X, y)
        
        # Determine trend direction
        if trend_slope > 0.05:
            trend_direction = 'improving'
            trend_significance = 'strong' if trend_slope > 0.15 else 'moderate'
        elif trend_slope < -0.05:
            trend_direction = 'declining'
            trend_significance = 'strong' if trend_slope < -0.15 else 'moderate'
        else:
            trend_direction = 'stable'
            trend_significance = 'weak'
        
        # Recent vs overall trend comparison
        if len(mood_data) >= 6:
            recent_trend = self._calculate_recent_trend(mood_data.tail(3), mood_data.head(3))
        else:
            recent_trend = 'insufficient_data'
        
        return {
            'trend_direction': trend_direction,
            'trend_significance': trend_significance,
            'trend_slope': round(trend_slope, 4),
            'trend_strength': round(trend_strength, 4),
            'trend_reliability': round(r_squared, 3),
            'recent_vs_overall': recent_trend,
            'weekly_change': self._calculate_weekly_change(mood_data)
        }
    
    def _calculate_recent_trend(self, recent_data: pd.Series, earlier_data: pd.Series) -> str:
        """Compare recent mood trend with earlier period."""
        if recent_data.empty or earlier_data.empty:
            return 'insufficient_data'
        
        recent_avg = recent_data.mean()
        earlier_avg = earlier_data.mean()
        
        change = recent_avg - earlier_avg
        
        if change > 0.5:
            return 'recent_improvement'
        elif change < -0.5:
            return 'recent_decline'
        else:
            return 'recent_stable'
    
    def _calculate_weekly_change(self, mood_data: pd.Series) -> dict:
        """Calculate weekly mood change if enough data."""
        if len(mood_data) < 7:
            return {'status': 'insufficient_data'}
        
        # Compare first and last 3 days
        early_period = mood_data.head(3).mean()
        late_period = mood_data.tail(3).mean()
        
        change = late_period - early_period
        percent_change = (change / early_period) * 100 if early_period != 0 else 0
        
        return {
            'absolute_change': round(change, 2),
            'percent_change': round(percent_change, 1),
            'direction': 'improvement' if change > 0 else 'decline' if change < 0 else 'stable'
        }
    
    def _analyze_mood_volatility(self, mood_data: pd.Series) -> dict:
        """Analyze mood volatility and stability."""
        volatility = mood_data.std()
        
        # Calculate day-to-day changes
        if len(mood_data) > 1:
            daily_changes = mood_data.diff().dropna()
            avg_daily_change = abs(daily_changes).mean()
            max_daily_swing = abs(daily_changes).max()
            
            # Volatility classification
            if volatility <= 1:
                volatility_level = 'low'
            elif volatility <= 2:
                volatility_level = 'moderate'
            else:
                volatility_level = 'high'
        else:
            daily_changes = pd.Series([])
            avg_daily_change = 0
            max_daily_swing = 0
            volatility_level = 'unknown'
        
        return {
            'volatility_score': round(volatility, 2),
            'volatility_level': volatility_level,
            'average_daily_change': round(avg_daily_change, 2),
            'max_daily_swing': round(max_daily_swing, 2),
            'stability_rating': self._rate_mood_stability(volatility),
            'mood_swings': int((abs(daily_changes) >= 2).sum()) if len(daily_changes) > 0 else 0
        }
    
    def _rate_mood_stability(self, volatility: float) -> str:
        """Rate mood stability based on volatility."""
        if volatility <= 0.8:
            return 'very_stable'
        elif volatility <= 1.5:
            return 'stable'
        elif volatility <= 2.5:
            return 'somewhat_volatile'
        else:
            return 'highly_volatile'
    
    def _analyze_mood_activity_correlation(self, user_id: int, health_data: pd.DataFrame) -> dict:
        """Analyze correlation between mood and activities."""
        try:
            # Get activity data
            activities = db.get_user_activities(user_id, len(health_data))
            
            if activities.empty:
                return {'status': 'no_activity_data'}
            
            # Calculate mood before/after activity correlations
            mood_improvements = []
            activity_types = {}
            
            for _, activity in activities.iterrows():
                if pd.notna(activity.get('mood_before')) and pd.notna(activity.get('mood_after')):
                    improvement = activity['mood_after'] - activity['mood_before']
                    mood_improvements.append(improvement)
                    
                    category = activity.get('category', 'unknown')
                    if category not in activity_types:
                        activity_types[category] = []
                    activity_types[category].append(improvement)
            
            if not mood_improvements:
                return {'status': 'no_mood_activity_data'}
            
            # Calculate best activity types
            best_activities = {}
            for category, improvements in activity_types.items():
                best_activities[category] = {
                    'average_improvement': round(np.mean(improvements), 2),
                    'activity_count': len(improvements),
                    'success_rate': round((np.array(improvements) > 0).mean(), 2)
                }
            
            return {
                'overall_activity_effect': round(np.mean(mood_improvements), 2),
                'activity_success_rate': round((np.array(mood_improvements) > 0).mean(), 2),
                'best_activity_types': best_activities,
                'total_activities_analyzed': len(mood_improvements)
            }
            
        except Exception:
            return {'status': 'analysis_failed'}
    
    def _analyze_mood_sleep_correlation(self, health_data: pd.DataFrame) -> dict:
        """Analyze correlation between mood and sleep."""
        if 'mood_score' not in health_data.columns or 'sleep_hours' not in health_data.columns:
            return {'status': 'insufficient_data'}
        
        mood_sleep_data = health_data[['mood_score', 'sleep_hours']].dropna()
        
        if len(mood_sleep_data) < 3:
            return {'status': 'insufficient_data'}
        
        # Calculate correlation
        correlation = mood_sleep_data['mood_score'].corr(mood_sleep_data['sleep_hours'])
        
        # Analyze sleep patterns and mood
        sleep_mood_patterns = {}
        
        # Group by sleep duration ranges
        sleep_ranges = [
            (0, 5, 'poor_sleep'),
            (5, 6, 'insufficient_sleep'),
            (6, 8, 'adequate_sleep'),
            (8, 10, 'good_sleep'),
            (10, 24, 'excessive_sleep')
        ]
        
        for min_sleep, max_sleep, category in sleep_ranges:
            sleep_mask = (mood_sleep_data['sleep_hours'] >= min_sleep) & (mood_sleep_data['sleep_hours'] < max_sleep)
            sleep_subset = mood_sleep_data[sleep_mask]
            
            if not sleep_subset.empty:
                sleep_mood_patterns[category] = {
                    'average_mood': round(sleep_subset['mood_score'].mean(), 2),
                    'mood_range': round(sleep_subset['mood_score'].std(), 2),
                    'data_points': len(sleep_subset)
                }
        
        return {
            'correlation_coefficient': round(correlation, 3) if not pd.isna(correlation) else None,
            'correlation_strength': self._interpret_correlation(correlation),
            'sleep_mood_patterns': sleep_mood_patterns,
            'optimal_sleep_range': self._find_optimal_sleep_range(sleep_mood_patterns)
        }
    
    def _interpret_correlation(self, correlation: float) -> str:
        """Interpret correlation strength."""
        if pd.isna(correlation):
            return 'unknown'
        
        abs_corr = abs(correlation)
        if abs_corr >= 0.7:
            return 'strong'
        elif abs_corr >= 0.4:
            return 'moderate'
        elif abs_corr >= 0.2:
            return 'weak'
        else:
            return 'negligible'
    
    def _find_optimal_sleep_range(self, sleep_patterns: dict) -> str:
        """Find the sleep range associated with best mood."""
        if not sleep_patterns:
            return 'unknown'
        
        best_category = max(
            sleep_patterns.keys(),
            key=lambda x: sleep_patterns[x]['average_mood']
        )
        
        return best_category
    
    def _analyze_temporal_patterns(self, health_data: pd.DataFrame) -> dict:
        """Analyze time-based mood patterns."""
        if 'mood_score' not in health_data.columns or 'created_at' not in health_data.columns:
            return {'status': 'insufficient_data'}
        
        mood_time_data = health_data[['mood_score', 'created_at']].dropna()
        mood_time_data['created_at'] = pd.to_datetime(mood_time_data['created_at'])
        mood_time_data['hour'] = mood_time_data['created_at'].dt.hour
        mood_time_data['day_of_week'] = mood_time_data['created_at'].dt.dayofweek
        
        patterns = {}
        
        # Hourly patterns (if multiple entries per day)
        if len(mood_time_data) > 7:
            hourly_mood = mood_time_data.groupby('hour')['mood_score'].mean()
            patterns['hourly'] = {
                'best_hours': hourly_mood.nlargest(3).index.tolist(),
                'worst_hours': hourly_mood.nsmallest(3).index.tolist(),
                'morning_mood': hourly_mood[hourly_mood.index.isin(range(6, 12))].mean(),
                'afternoon_mood': hourly_mood[hourly_mood.index.isin(range(12, 18))].mean(),
                'evening_mood': hourly_mood[hourly_mood.index.isin(range(18, 23))].mean()
            }
        
        # Weekly patterns
        if len(mood_time_data) > 7:
            weekly_mood = mood_time_data.groupby('day_of_week')['mood_score'].mean()
            weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
            
            patterns['weekly'] = {
                'weekday_moods': {weekdays[i]: round(weekly_mood.get(i, 0), 2) for i in range(7)},
                'best_day': weekdays[weekly_mood.idxmax()] if not weekly_mood.empty else 'unknown',
                'worst_day': weekdays[weekly_mood.idxmin()] if not weekly_mood.empty else 'unknown',
                'weekend_vs_weekday': {
                    'weekend_avg': round(weekly_mood[weekly_mood.index.isin([5, 6])].mean(), 2),
                    'weekday_avg': round(weekly_mood[weekly_mood.index.isin(range(5))].mean(), 2)
                }
            }
        
        return patterns
    
    def _assess_mental_health_risk(self, mood_data: pd.Series, health_data: pd.DataFrame) -> dict:
        """Assess mental health risk based on mood patterns."""
        risk_factors = []
        risk_score = 0
        
        # Low mood episodes
        low_mood_episodes = (mood_data <= self.depression_threshold).sum()
        if low_mood_episodes >= len(mood_data) * 0.5:  # 50% of time
            risk_factors.append("Frequent low mood episodes")
            risk_score += 3
        elif low_mood_episodes >= len(mood_data) * 0.3:  # 30% of time
            risk_factors.append("Occasional low mood episodes")
            risk_score += 2
        
        # Declining trend
        if len(mood_data) >= 3:
            X = np.arange(len(mood_data)).reshape(-1, 1)
            model = LinearRegression()
            model.fit(X, mood_data.values)
            
            if model.coef_[0] < -0.1:
                risk_factors.append("Declining mood trend")
                risk_score += 2
        
        # High volatility
        if mood_data.std() > 2.5:
            risk_factors.append("High mood volatility")
            risk_score += 1
        
        # Social isolation indicators (if activity data available)
        try:
            activities = db.get_user_activities(health_data['user_id'].iloc[0] if 'user_id' in health_data.columns else 1, 7)
            social_activities = activities[activities['category'].str.contains('social', case=False, na=False)]
            
            if len(activities) > 0 and len(social_activities) / len(activities) < 0.2:
                risk_factors.append("Limited social engagement")
                risk_score += 1
        except:
            pass
        
        # Determine risk level
        if risk_score >= 5:
            risk_level = 'high'
        elif risk_score >= 3:
            risk_level = 'moderate'
        elif risk_score >= 1:
            risk_level = 'low'
        else:
            risk_level = 'minimal'
        
        return {
            'risk_level': risk_level,
            'risk_score': risk_score,
            'risk_factors': risk_factors,
            'immediate_attention_needed': risk_level == 'high',
            'professional_referral_suggested': risk_score >= 4
        }
    
    def _predict_mood_changes(self, mood_data: pd.Series) -> dict:
        """Predict likely mood changes."""
        if len(mood_data) < 3:
            return {'status': 'insufficient_data'}
        
        # Simple linear prediction for next few days
        X = np.arange(len(mood_data)).reshape(-1, 1)
        model = LinearRegression()
        model.fit(X, mood_data.values)
        
        # Predict next 3 days
        future_points = np.array([[len(mood_data)], [len(mood_data) + 1], [len(mood_data) + 2]])
        predictions = model.predict(future_points)
        
        # Bound predictions to reasonable range
        predictions = np.clip(predictions, 1, 10)
        
        return {
            'next_day_prediction': round(predictions[0], 2),
            'three_day_trend': round(np.mean(predictions), 2),
            'prediction_confidence': min(0.8, model.score(X, mood_data.values)),
            'trend_direction': 'improving' if model.coef_[0] > 0 else 'declining' if model.coef_[0] < 0 else 'stable',
            'volatility_expected': round(mood_data.std(), 2)
        }
    
    def _generate_mood_recommendations(self, mood_data: pd.Series, risk_assessment: dict) -> list:
        """Generate personalized mood improvement recommendations."""
        recommendations = []
        
        current_mood = mood_data.iloc[-1] if len(mood_data) > 0 else 5
        avg_mood = mood_data.mean()
        risk_level = risk_assessment.get('risk_level', 'unknown')
        
        # High-priority recommendations for high-risk individuals
        if risk_level == 'high':
            recommendations.append({
                'type': 'urgent_mental_health',
                'priority': 'high',
                'suggestion': 'Consider professional mental health evaluation due to consistent low mood patterns',
                'actions': [
                    'Schedule appointment with mental health professional',
                    'Inform primary care physician about mood concerns',
                    'Consider crisis support resources if needed'
                ]
            })
        
        # Low mood interventions
        if current_mood <= self.depression_threshold:
            recommendations.append({
                'type': 'mood_boost',
                'priority': 'high',
                'suggestion': 'Immediate mood-boosting activities recommended',
                'actions': [
                    'Engage in social activities or contact friends/family',
                    'Light physical exercise (walking, stretching)',
                    'Exposure to natural light or outdoor time',
                    'Practice relaxation or mindfulness techniques'
                ]
            })
        
        # Activity-based recommendations
        if avg_mood < 6:
            recommendations.append({
                'type': 'activity_engagement',
                'priority': 'medium',
                'suggestion': 'Increase participation in mood-enhancing activities',
                'actions': [
                    'Join group activities or social events',
                    'Engage in creative or hobby activities',
                    'Regular physical exercise routine',
                    'Volunteer work or helping others'
                ]
            })
        
        # Sleep and lifestyle
        recommendations.append({
            'type': 'lifestyle_optimization',
            'priority': 'medium',
            'suggestion': 'Optimize daily routines for better mood stability',
            'actions': [
                'Maintain consistent sleep schedule',
                'Regular meal times and balanced nutrition',
                'Limit alcohol and caffeine',
                'Create daily structure and routine'
            ]
        })
        
        # Monitoring and tracking
        recommendations.append({
            'type': 'mood_monitoring',
            'priority': 'low',
            'suggestion': 'Continue regular mood tracking and monitoring',
            'actions': [
                'Daily mood check-ins',
                'Identify mood triggers and patterns',
                'Celebrate small improvements',
                'Regular review with care team'
            ]
        })
        
        return recommendations