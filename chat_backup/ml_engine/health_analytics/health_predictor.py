"""
Health Predictor Module

Advanced health trend analysis and prediction for seniors using machine learning.
Analyzes patterns in health journal data to predict health deterioration,
improve care outcomes, and generate timely alerts.
"""

import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from sklearn.ensemble import IsolationForest
from sklearn.linear_model import LinearRegression
from sklearn.preprocessing import StandardScaler
import warnings
warnings.filterwarnings('ignore')

from ..utils.database import db
from ..config import HEALTH_ANALYTICS

class HealthPredictor:
    """Predicts health trends and identifies concerning patterns."""
    
    def __init__(self):
        self.config = HEALTH_ANALYTICS
        self.scaler = StandardScaler()
        self.anomaly_detector = IsolationForest(
            contamination=1 - self.config['anomaly_detection_sensitivity']
        )
    
    def predict_health_trends(self, user_id: int, days: int = 30) -> dict:
        """
        Predict health trends for a specific user.
        
        Args:
            user_id: User identifier
            days: Number of days to analyze
            
        Returns:
            Dictionary with health predictions and alerts
        """
        try:
            # Get user health data
            health_data = db.get_user_health_data(user_id, days)
            
            if health_data.empty:
                return {'message': 'No health data available for prediction'}
            
            # Analyze different health aspects
            mood_trend = self._analyze_mood_trend(health_data)
            pain_analysis = self._analyze_pain_patterns(health_data)
            sleep_analysis = self._analyze_sleep_patterns(health_data)
            vital_signs = self._analyze_vital_signs(health_data)
            
            # Generate health score
            health_score = self._calculate_health_score(health_data)
            
            # Detect anomalies
            anomalies = self._detect_health_anomalies(health_data)
            
            # Generate alerts
            alerts = self._generate_health_alerts(health_data, anomalies)
            
            return {
                'user_id': user_id,
                'analysis_period_days': days,
                'health_score': health_score,
                'trends': {
                    'mood': mood_trend,
                    'pain': pain_analysis,
                    'sleep': sleep_analysis,
                    'vital_signs': vital_signs
                },
                'anomalies': anomalies,
                'alerts': alerts,
                'recommendations': self._generate_health_recommendations(health_data),
                'generated_at': datetime.now().isoformat()
            }
            
        except Exception as e:
            return {'error': f'Health prediction failed: {str(e)}'}
    
    def analyze_health_patterns(self, health_data: pd.DataFrame, vital_trends: pd.DataFrame) -> dict:
        """Comprehensive health pattern analysis."""
        patterns = {}
        
        if not health_data.empty:
            patterns['overall_trend'] = self._calculate_overall_trend(health_data)
            patterns['stability_index'] = self._calculate_stability_index(health_data)
            patterns['decline_risk'] = self._assess_decline_risk(health_data)
        
        if not vital_trends.empty:
            patterns['vital_stability'] = self._analyze_vital_stability(vital_trends)
            patterns['cardiovascular_risk'] = self._assess_cardiovascular_risk(vital_trends)
        
        return patterns
    
    def _analyze_mood_trend(self, health_data: pd.DataFrame) -> dict:
        """Analyze mood trends and patterns."""
        if 'mood_score' not in health_data.columns or health_data['mood_score'].isna().all():
            return {'status': 'no_data', 'trend': 'unknown'}
        
        mood_scores = health_data['mood_score'].dropna()
        
        if len(mood_scores) < 3:
            return {'status': 'insufficient_data', 'trend': 'unknown'}
        
        # Calculate trend using linear regression
        X = np.arange(len(mood_scores)).reshape(-1, 1)
        y = mood_scores.values
        
        model = LinearRegression()
        model.fit(X, y)
        
        trend_slope = model.coef_[0]
        current_avg = mood_scores.tail(7).mean() if len(mood_scores) >= 7 else mood_scores.mean()
        
        # Determine trend direction and severity
        if trend_slope < -0.1:
            trend_direction = 'declining'
            severity = 'high' if trend_slope < -0.3 else 'moderate'
        elif trend_slope > 0.1:
            trend_direction = 'improving'
            severity = 'high' if trend_slope > 0.3 else 'moderate'
        else:
            trend_direction = 'stable'
            severity = 'low'
        
        # Depression risk assessment
        depression_risk = 'high' if current_avg <= self.config['alert_thresholds']['mood_depression_threshold'] else 'low'
        
        return {
            'trend_direction': trend_direction,
            'trend_strength': abs(trend_slope),
            'severity': severity,
            'current_average': round(current_avg, 2),
            'depression_risk': depression_risk,
            'data_points': len(mood_scores)
        }
    
    def _analyze_pain_patterns(self, health_data: pd.DataFrame) -> dict:
        """Analyze pain level patterns and frequency."""
        if 'pain_level' not in health_data.columns or health_data['pain_level'].isna().all():
            return {'status': 'no_data'}
        
        pain_data = health_data['pain_level'].dropna()
        
        if pain_data.empty:
            return {'status': 'no_data'}
        
        current_avg = pain_data.tail(7).mean() if len(pain_data) >= 7 else pain_data.mean()
        high_pain_days = (pain_data >= self.config['alert_thresholds']['pain_alert_threshold']).sum()
        high_pain_frequency = high_pain_days / len(pain_data) * 100
        
        # Pain trend analysis
        if len(pain_data) >= 3:
            X = np.arange(len(pain_data)).reshape(-1, 1)
            model = LinearRegression()
            model.fit(X, pain_data.values)
            trend_slope = model.coef_[0]
        else:
            trend_slope = 0
        
        return {
            'current_average': round(current_avg, 2),
            'high_pain_frequency_percent': round(high_pain_frequency, 1),
            'trend_slope': trend_slope,
            'max_pain': pain_data.max(),
            'pain_variability': round(pain_data.std(), 2)
        }
    
    def _analyze_sleep_patterns(self, health_data: pd.DataFrame) -> dict:
        """Analyze sleep quality and duration patterns."""
        if 'sleep_hours' not in health_data.columns:
            return {'status': 'no_data'}
        
        sleep_data = health_data['sleep_hours'].dropna()
        
        if sleep_data.empty:
            return {'status': 'no_data'}
        
        avg_sleep = sleep_data.mean()
        sleep_consistency = sleep_data.std()
        
        # Quality assessment
        quality_assessment = 'good'
        if avg_sleep < 6:
            quality_assessment = 'poor_duration'
        elif avg_sleep > 9:
            quality_assessment = 'excessive'
        elif sleep_consistency > 2:
            quality_assessment = 'inconsistent'
        
        return {
            'average_hours': round(avg_sleep, 2),
            'consistency_score': round(sleep_consistency, 2),
            'quality_assessment': quality_assessment,
            'min_hours': sleep_data.min(),
            'max_hours': sleep_data.max()
        }
    
    def _analyze_vital_signs(self, health_data: pd.DataFrame) -> dict:
        """Analyze vital signs patterns."""
        vitals = {}
        
        # Blood pressure analysis
        if 'blood_pressure_systolic' in health_data.columns and 'blood_pressure_diastolic' in health_data.columns:
            systolic = health_data['blood_pressure_systolic'].dropna()
            diastolic = health_data['blood_pressure_diastolic'].dropna()
            
            if not systolic.empty:
                high_bp_count = (systolic > self.config['alert_thresholds']['blood_pressure_high']).sum()
                vitals['blood_pressure'] = {
                    'avg_systolic': round(systolic.mean(), 1),
                    'avg_diastolic': round(diastolic.mean(), 1),
                    'high_bp_episodes': int(high_bp_count),
                    'bp_variability': round(systolic.std(), 1)
                }
        
        # Heart rate analysis
        if 'heart_rate' in health_data.columns:
            hr_data = health_data['heart_rate'].dropna()
            if not hr_data.empty:
                vitals['heart_rate'] = {
                    'average': round(hr_data.mean(), 1),
                    'variability': round(hr_data.std(), 1),
                    'min': hr_data.min(),
                    'max': hr_data.max()
                }
        
        return vitals
    
    def _calculate_health_score(self, health_data: pd.DataFrame) -> dict:
        """Calculate overall health score (0-100)."""
        score_components = {}
        total_score = 0
        component_count = 0
        
        # Mood component (0-25 points)
        if 'mood_score' in health_data.columns:
            mood_avg = health_data['mood_score'].dropna().tail(7).mean()
            if not pd.isna(mood_avg):
                mood_score = (mood_avg / 10) * 25  # Convert to 0-25 scale
                score_components['mood'] = round(mood_score, 1)
                total_score += mood_score
                component_count += 1
        
        # Pain component (0-25 points, inverse)
        if 'pain_level' in health_data.columns:
            pain_avg = health_data['pain_level'].dropna().tail(7).mean()
            if not pd.isna(pain_avg):
                pain_score = ((10 - pain_avg) / 10) * 25  # Inverse scoring
                score_components['pain'] = round(pain_score, 1)
                total_score += pain_score
                component_count += 1
        
        # Sleep component (0-25 points)
        if 'sleep_hours' in health_data.columns:
            sleep_avg = health_data['sleep_hours'].dropna().tail(7).mean()
            if not pd.isna(sleep_avg):
                # Optimal sleep is 7-8 hours
                if 7 <= sleep_avg <= 8:
                    sleep_score = 25
                else:
                    sleep_score = max(0, 25 - abs(sleep_avg - 7.5) * 3)
                score_components['sleep'] = round(sleep_score, 1)
                total_score += sleep_score
                component_count += 1
        
        # Activity component (0-25 points)
        if 'physical_activity_minutes' in health_data.columns:
            activity_avg = health_data['physical_activity_minutes'].dropna().tail(7).mean()
            if not pd.isna(activity_avg):
                activity_score = min(25, (activity_avg / 30) * 25)  # 30 min = full score
                score_components['activity'] = round(activity_score, 1)
                total_score += activity_score
                component_count += 1
        
        final_score = total_score / component_count if component_count > 0 else 0
        
        return {
            'overall_score': round(final_score, 1),
            'components': score_components,
            'assessment': self._assess_health_score(final_score)
        }
    
    def _assess_health_score(self, score: float) -> str:
        """Assess health status based on score."""
        if score >= 80:
            return 'excellent'
        elif score >= 65:
            return 'good'
        elif score >= 50:
            return 'fair'
        elif score >= 35:
            return 'poor'
        else:
            return 'critical'
    
    def _detect_health_anomalies(self, health_data: pd.DataFrame) -> list:
        """Detect anomalies in health data using machine learning."""
        anomalies = []
        
        # Select numeric columns for anomaly detection
        numeric_cols = health_data.select_dtypes(include=[np.number]).columns
        usable_cols = [col for col in numeric_cols if not health_data[col].isna().all()]
        
        if len(usable_cols) < 2:
            return anomalies
        
        try:
            # Prepare data for anomaly detection
            data_for_analysis = health_data[usable_cols].dropna()
            
            if len(data_for_analysis) < 10:
                return anomalies
            
            # Standardize data
            scaled_data = self.scaler.fit_transform(data_for_analysis)
            
            # Detect anomalies
            anomaly_labels = self.anomaly_detector.fit_predict(scaled_data)
            
            # Extract anomalous records
            anomaly_indices = np.where(anomaly_labels == -1)[0]
            
            for idx in anomaly_indices:
                original_idx = data_for_analysis.index[idx]
                anomalies.append({
                    'date': health_data.loc[original_idx, 'created_at'].isoformat() if 'created_at' in health_data.columns else 'unknown',
                    'type': 'health_pattern_anomaly',
                    'severity': 'medium',
                    'description': 'Unusual combination of health metrics detected'
                })
        
        except Exception as e:
            print(f"Anomaly detection error: {e}")
        
        return anomalies
    
    def _generate_health_alerts(self, health_data: pd.DataFrame, anomalies: list) -> list:
        """Generate actionable health alerts."""
        alerts = []
        
        # Mood alerts
        if 'mood_score' in health_data.columns:
            recent_mood = health_data['mood_score'].dropna().tail(3)
            if not recent_mood.empty and recent_mood.mean() <= self.config['alert_thresholds']['mood_depression_threshold']:
                alerts.append({
                    'type': 'mood_alert',
                    'severity': 'high',
                    'message': 'Consistently low mood scores detected. Consider mental health evaluation.',
                    'action': 'schedule_mental_health_check'
                })
        
        # Pain alerts
        if 'pain_level' in health_data.columns:
            recent_pain = health_data['pain_level'].dropna().tail(3)
            if not recent_pain.empty and recent_pain.mean() >= self.config['alert_thresholds']['pain_alert_threshold']:
                alerts.append({
                    'type': 'pain_alert',
                    'severity': 'high',
                    'message': 'High pain levels detected consistently. Pain management review recommended.',
                    'action': 'review_pain_management'
                })
        
        # Add anomaly alerts
        for anomaly in anomalies:
            alerts.append({
                'type': 'anomaly_alert',
                'severity': anomaly['severity'],
                'message': anomaly['description'],
                'action': 'investigate_health_pattern'
            })
        
        return alerts
    
    def _generate_health_recommendations(self, health_data: pd.DataFrame) -> list:
        """Generate personalized health recommendations."""
        recommendations = []
        
        # Mood-based recommendations
        if 'mood_score' in health_data.columns:
            mood_avg = health_data['mood_score'].dropna().tail(7).mean()
            if not pd.isna(mood_avg) and mood_avg < 6:
                recommendations.append({
                    'type': 'mood_improvement',
                    'suggestion': 'Consider engaging in social activities or light exercise to improve mood',
                    'priority': 'high'
                })
        
        # Sleep recommendations
        if 'sleep_hours' in health_data.columns:
            sleep_avg = health_data['sleep_hours'].dropna().tail(7).mean()
            if not pd.isna(sleep_avg):
                if sleep_avg < 6:
                    recommendations.append({
                        'type': 'sleep_improvement',
                        'suggestion': 'Focus on improving sleep duration. Consider sleep hygiene practices.',
                        'priority': 'medium'
                    })
                elif sleep_avg > 9:
                    recommendations.append({
                        'type': 'sleep_assessment',
                        'suggestion': 'Excessive sleep detected. Consider medical evaluation for underlying causes.',
                        'priority': 'medium'
                    })
        
        return recommendations
    
    def _calculate_overall_trend(self, health_data: pd.DataFrame) -> str:
        """Calculate overall health trend direction."""
        trends = []
        
        for col in ['mood_score', 'sleep_hours', 'physical_activity_minutes']:
            if col in health_data.columns and not health_data[col].isna().all():
                data = health_data[col].dropna()
                if len(data) >= 3:
                    X = np.arange(len(data)).reshape(-1, 1)
                    model = LinearRegression()
                    model.fit(X, data.values)
                    trends.append(model.coef_[0])
        
        if col == 'pain_level' and col in health_data.columns:
            data = health_data[col].dropna()
            if len(data) >= 3:
                X = np.arange(len(data)).reshape(-1, 1)
                model = LinearRegression()
                model.fit(X, data.values)
                trends.append(-model.coef_[0])  # Inverse for pain
        
        if not trends:
            return 'unknown'
        
        avg_trend = np.mean(trends)
        if avg_trend > 0.1:
            return 'improving'
        elif avg_trend < -0.1:
            return 'declining'
        else:
            return 'stable'
    
    def _calculate_stability_index(self, health_data: pd.DataFrame) -> float:
        """Calculate health stability index (0-1, higher is more stable)."""
        stability_scores = []
        
        for col in ['mood_score', 'sleep_hours', 'pain_level']:
            if col in health_data.columns:
                data = health_data[col].dropna()
                if len(data) > 1:
                    cv = data.std() / data.mean() if data.mean() != 0 else 1
                    stability = max(0, 1 - cv)  # Convert coefficient of variation to stability
                    stability_scores.append(stability)
        
        return round(np.mean(stability_scores), 3) if stability_scores else 0.0
    
    def _assess_decline_risk(self, health_data: pd.DataFrame) -> str:
        """Assess overall health decline risk."""
        risk_factors = 0
        
        # Check recent mood trends
        if 'mood_score' in health_data.columns:
            mood_data = health_data['mood_score'].dropna().tail(7)
            if not mood_data.empty and mood_data.mean() < 5:
                risk_factors += 1
        
        # Check pain levels
        if 'pain_level' in health_data.columns:
            pain_data = health_data['pain_level'].dropna().tail(7)
            if not pain_data.empty and pain_data.mean() > 6:
                risk_factors += 1
        
        # Check sleep patterns
        if 'sleep_hours' in health_data.columns:
            sleep_data = health_data['sleep_hours'].dropna().tail(7)
            if not sleep_data.empty:
                avg_sleep = sleep_data.mean()
                if avg_sleep < 5 or avg_sleep > 10:
                    risk_factors += 1
        
        if risk_factors >= 2:
            return 'high'
        elif risk_factors == 1:
            return 'moderate'
        else:
            return 'low'
    
    def _analyze_vital_stability(self, vital_trends: pd.DataFrame) -> dict:
        """Analyze stability of vital signs."""
        stability = {}
        
        for col in ['blood_pressure_systolic', 'heart_rate']:
            if col in vital_trends.columns:
                data = vital_trends[col].dropna()
                if len(data) > 1:
                    cv = data.std() / data.mean() if data.mean() != 0 else 0
                    stability[col] = {
                        'coefficient_of_variation': round(cv, 3),
                        'stability_rating': 'stable' if cv < 0.1 else 'variable' if cv < 0.2 else 'unstable'
                    }
        
        return stability
    
    def _assess_cardiovascular_risk(self, vital_trends: pd.DataFrame) -> dict:
        """Assess cardiovascular risk based on vital signs."""
        risk_factors = []
        risk_score = 0
        
        if 'blood_pressure_systolic' in vital_trends.columns:
            bp_data = vital_trends['blood_pressure_systolic'].dropna()
            if not bp_data.empty:
                avg_bp = bp_data.mean()
                if avg_bp > 140:
                    risk_factors.append('hypertension')
                    risk_score += 2
                elif avg_bp > 130:
                    risk_factors.append('elevated_bp')
                    risk_score += 1
        
        if 'heart_rate' in vital_trends.columns:
            hr_data = vital_trends['heart_rate'].dropna()
            if not hr_data.empty:
                avg_hr = hr_data.mean()
                if avg_hr > 100:
                    risk_factors.append('tachycardia')
                    risk_score += 1
                elif avg_hr < 50:
                    risk_factors.append('bradycardia')
                    risk_score += 1
        
        if risk_score >= 3:
            risk_level = 'high'
        elif risk_score >= 2:
            risk_level = 'moderate'
        elif risk_score >= 1:
            risk_level = 'low'
        else:
            risk_level = 'minimal'
        
        return {
            'risk_level': risk_level,
            'risk_factors': risk_factors,
            'risk_score': risk_score
        }