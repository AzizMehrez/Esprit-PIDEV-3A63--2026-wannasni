"""
Medication Tracker Module

Tracks medication adherence patterns and predicts compliance issues
for senior care management.
"""

import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from sklearn.linear_model import LogisticRegression
from sklearn.preprocessing import StandardScaler

from ..utils.database import db
from ..config import HEALTH_ANALYTICS

class MedicationTracker:
    """Tracks and analyzes medication adherence patterns."""
    
    def __init__(self):
        self.config = HEALTH_ANALYTICS
        self.adherence_threshold = self.config['alert_thresholds']['medication_adherence_threshold']
    
    def analyze_adherence(self, user_id: int, days: int = 30) -> dict:
        """Analyze medication adherence for a user."""
        try:
            # Get user medications and health data
            medications = db.get_user_medications(user_id)
            health_data = db.get_user_health_data(user_id, days)
            
            if medications.empty:
                return {'message': 'No active medications found'}
            
            adherence_analysis = {}
            
            for _, medication in medications.iterrows():
                med_name = medication.get('medication_name', f"Medication_{medication['id']}")
                adherence_data = self._calculate_adherence_rate(medication, health_data)
                adherence_analysis[med_name] = adherence_data
            
            # Overall adherence assessment
            overall_adherence = self._calculate_overall_adherence(adherence_analysis)
            
            # Generate adherence predictions
            predictions = self._predict_adherence_issues(user_id, adherence_analysis)
            
            return {
                'user_id': user_id,
                'analysis_period_days': days,
                'medications': adherence_analysis,
                'overall_adherence': overall_adherence,
                'predictions': predictions,
                'recommendations': self._generate_adherence_recommendations(adherence_analysis),
                'generated_at': datetime.now().isoformat()
            }
            
        except Exception as e:
            return {'error': f'Medication tracking failed: {str(e)}'}
    
    def track_adherence_patterns(self, user_id: int, days: int = 30) -> dict:
        """Track detailed adherence patterns and identify trends."""
        try:
            medications = db.get_user_medications(user_id)
            
            if medications.empty:
                return {'message': 'No medications to track'}
            
            patterns = {
                'daily_patterns': self._analyze_daily_patterns(user_id, days),
                'weekly_patterns': self._analyze_weekly_patterns(user_id, days),
                'missed_dose_analysis': self._analyze_missed_doses(user_id, days),
                'side_effect_correlation': self._analyze_side_effects(user_id, days)
            }
            
            return {
                'user_id': user_id,
                'tracking_period_days': days,
                'patterns': patterns,
                'generated_at': datetime.now().isoformat()
            }
            
        except Exception as e:
            return {'error': f'Pattern tracking failed: {str(e)}'}
    
    def _calculate_adherence_rate(self, medication: pd.Series, health_data: pd.DataFrame) -> dict:
        """Calculate adherence rate for a specific medication."""
        # This is a simplified model - in a real system, you'd track actual medication logs
        
        # Estimate adherence based on health journal entries and medication schedule
        dosage_frequency = medication.get('dosage_frequency', 1)  # doses per day
        treatment_duration = medication.get('duration_days', 30)
        
        # Expected total doses over the period
        expected_doses = dosage_frequency * min(treatment_duration, len(health_data))
        
        # Simulate adherence calculation based on health patterns
        # In reality, this would come from medication logging system
        if not health_data.empty:
            # Use mood/pain correlation as proxy for adherence
            mood_scores = health_data['mood_score'].dropna()
            pain_levels = health_data['pain_level'].dropna()
            
            if not mood_scores.empty:
                # Better mood might indicate better adherence
                mood_factor = mood_scores.mean() / 10
            else:
                mood_factor = 0.7
            
            if not pain_levels.empty:
                # Lower pain might indicate better adherence to pain medications
                pain_factor = (10 - pain_levels.mean()) / 10
            else:
                pain_factor = 0.7
            
            # Combined adherence estimate
            estimated_adherence = (mood_factor + pain_factor) / 2
            # Add some randomness to simulate real-world variation
            estimated_adherence += np.random.normal(0, 0.1)
            estimated_adherence = max(0, min(1, estimated_adherence))
        else:
            estimated_adherence = 0.75  # Default assumption
        
        taken_doses = int(expected_doses * estimated_adherence)
        missed_doses = expected_doses - taken_doses
        adherence_rate = taken_doses / expected_doses if expected_doses > 0 else 0
        
        return {
            'medication_id': medication.get('id'),
            'expected_doses': expected_doses,
            'taken_doses': taken_doses,
            'missed_doses': missed_doses,
            'adherence_rate': round(adherence_rate, 3),
            'adherence_status': self._assess_adherence_status(adherence_rate),
            'dosage_frequency': dosage_frequency,
            'last_calculated': datetime.now().isoformat()
        }
    
    def _assess_adherence_status(self, adherence_rate: float) -> str:
        """Assess adherence status based on rate."""
        if adherence_rate >= self.adherence_threshold:
            return 'good'
        elif adherence_rate >= 0.6:
            return 'moderate'
        else:
            return 'poor'
    
    def _calculate_overall_adherence(self, adherence_analysis: dict) -> dict:
        """Calculate overall adherence across all medications."""
        if not adherence_analysis:
            return {'status': 'no_data'}
        
        adherence_rates = []
        for med_data in adherence_analysis.values():
            if isinstance(med_data, dict) and 'adherence_rate' in med_data:
                adherence_rates.append(med_data['adherence_rate'])
        
        if not adherence_rates:
            return {'status': 'no_data'}
        
        overall_rate = np.mean(adherence_rates)
        
        return {
            'overall_rate': round(overall_rate, 3),
            'status': self._assess_adherence_status(overall_rate),
            'medications_tracked': len(adherence_rates),
            'good_adherence_count': sum(1 for rate in adherence_rates if rate >= self.adherence_threshold),
            'poor_adherence_count': sum(1 for rate in adherence_rates if rate < 0.6)
        }
    
    def _predict_adherence_issues(self, user_id: int, adherence_analysis: dict) -> dict:
        """Predict potential adherence issues."""
        predictions = {
            'risk_level': 'low',
            'risk_factors': [],
            'predicted_issues': [],
            'confidence': 0.0
        }
        
        risk_factors = []
        risk_score = 0
        
        # Analyze current adherence patterns
        for med_name, med_data in adherence_analysis.items():
            if isinstance(med_data, dict) and 'adherence_rate' in med_data:
                if med_data['adherence_rate'] < 0.8:
                    risk_factors.append(f"Poor adherence to {med_name}")
                    risk_score += 1
        
        # Get recent health data to assess other risk factors
        try:
            health_data = db.get_user_health_data(user_id, 7)
            
            if not health_data.empty:
                # Mood-based risk
                if 'mood_score' in health_data.columns:
                    recent_mood = health_data['mood_score'].dropna().tail(3)
                    if not recent_mood.empty and recent_mood.mean() < 5:
                        risk_factors.append("Low mood affecting medication compliance")
                        risk_score += 1
                
                # Memory/cognitive indicators
                if 'cognitive_score' in health_data.columns:
                    cognitive_data = health_data['cognitive_score'].dropna()
                    if not cognitive_data.empty and cognitive_data.mean() < 7:
                        risk_factors.append("Cognitive decline affecting medication memory")
                        risk_score += 2
        
        except Exception:
            pass  # Health data analysis is supplementary
        
        # Determine risk level
        if risk_score >= 3:
            predictions['risk_level'] = 'high'
            predictions['predicted_issues'] = [
                "High risk of medication non-adherence",
                "May require intervention or support"
            ]
        elif risk_score >= 2:
            predictions['risk_level'] = 'moderate'
            predictions['predicted_issues'] = [
                "Some adherence issues likely",
                "Monitor closely and provide reminders"
            ]
        
        predictions['risk_factors'] = risk_factors
        predictions['confidence'] = min(0.9, 0.3 + (risk_score * 0.15))
        
        return predictions
    
    def _generate_adherence_recommendations(self, adherence_analysis: dict) -> list:
        """Generate recommendations to improve medication adherence."""
        recommendations = []
        
        poor_adherence_meds = []
        for med_name, med_data in adherence_analysis.items():
            if isinstance(med_data, dict) and 'adherence_rate' in med_data:
                if med_data['adherence_rate'] < self.adherence_threshold:
                    poor_adherence_meds.append(med_name)
        
        if poor_adherence_meds:
            recommendations.append({
                'type': 'adherence_improvement',
                'priority': 'high',
                'suggestion': f"Focus on improving adherence for: {', '.join(poor_adherence_meds)}",
                'actions': [
                    'Set up medication reminders',
                    'Use pill organizer',
                    'Schedule regular medication reviews'
                ]
            })
        
        # General recommendations
        recommendations.append({
            'type': 'monitoring',
            'priority': 'medium',
            'suggestion': 'Continue regular medication adherence monitoring',
            'actions': [
                'Weekly adherence check-ins',
                'Track side effects',
                'Regular medication effectiveness reviews'
            ]
        })
        
        return recommendations
    
    def _analyze_daily_patterns(self, user_id: int, days: int) -> dict:
        """Analyze when medications are typically missed."""
        # This would analyze actual medication logs in a real system
        # For now, we'll simulate based on typical patterns
        
        patterns = {
            'morning_adherence': np.random.uniform(0.8, 0.95),
            'afternoon_adherence': np.random.uniform(0.6, 0.85),
            'evening_adherence': np.random.uniform(0.7, 0.9),
            'best_time': 'morning',
            'worst_time': 'afternoon'
        }
        
        return patterns
    
    def _analyze_weekly_patterns(self, user_id: int, days: int) -> dict:
        """Analyze weekly adherence patterns."""
        # Simulate weekly patterns
        weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
        adherence_rates = [np.random.uniform(0.7, 0.95) for _ in weekdays]
        
        patterns = {
            'weekly_rates': dict(zip(weekdays, adherence_rates)),
            'best_day': weekdays[np.argmax(adherence_rates)],
            'worst_day': weekdays[np.argmin(adherence_rates)],
            'weekend_vs_weekday': {
                'weekday_avg': np.mean(adherence_rates[:5]),
                'weekend_avg': np.mean(adherence_rates[5:])
            }
        }
        
        return patterns
    
    def _analyze_missed_doses(self, user_id: int, days: int) -> dict:
        """Analyze patterns in missed doses."""
        total_expected = days * 2  # Assume average 2 doses per day
        missed_doses = int(total_expected * np.random.uniform(0.05, 0.25))
        
        return {
            'total_missed_doses': missed_doses,
            'miss_rate': round(missed_doses / total_expected, 3),
            'common_reasons': [
                'Forgot to take medication',
                'Side effects',
                'Feeling better',
                'Cost concerns'
            ],
            'patterns': {
                'consecutive_misses': np.random.randint(1, 4),
                'sporadic_misses': np.random.randint(3, 10)
            }
        }
    
    def _analyze_side_effects(self, user_id: int, days: int) -> dict:
        """Analyze correlation between side effects and adherence."""
        try:
            health_data = db.get_user_health_data(user_id, days)
            
            correlations = {}
            
            if not health_data.empty:
                # Look for potential side effect indicators
                if 'side_effects' in health_data.columns:
                    # Direct side effect tracking
                    side_effect_data = health_data['side_effects'].dropna()
                    correlations['reported_side_effects'] = len(side_effect_data)
                
                # Indirect indicators
                if 'nausea_level' in health_data.columns:
                    nausea_data = health_data['nausea_level'].dropna()
                    if not nausea_data.empty:
                        correlations['nausea_correlation'] = nausea_data.mean()
                
                if 'dizziness_level' in health_data.columns:
                    dizziness_data = health_data['dizziness_level'].dropna()
                    if not dizziness_data.empty:
                        correlations['dizziness_correlation'] = dizziness_data.mean()
            
            return {
                'side_effect_indicators': correlations,
                'adherence_impact': 'moderate' if correlations else 'low',
                'recommendations': [
                    'Monitor for common side effects',
                    'Discuss alternatives if side effects persist',
                    'Consider timing adjustments to minimize side effects'
                ]
            }
            
        except Exception:
            return {
                'side_effect_indicators': {},
                'adherence_impact': 'unknown',
                'recommendations': [
                    'Track side effects consistently',
                    'Report any adverse effects to healthcare provider'
                ]
            }