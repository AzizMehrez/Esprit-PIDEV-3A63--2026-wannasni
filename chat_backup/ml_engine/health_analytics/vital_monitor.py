"""
Vital Monitor Module

Real-time vital signs monitoring and anomaly detection
for senior health management.
"""

import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from sklearn.ensemble import IsolationForest
from sklearn.preprocessing import StandardScaler
from scipy import stats
import warnings
warnings.filterwarnings('ignore')

from ..utils.database import db
from ..config import HEALTH_ANALYTICS

class VitalMonitor:
    """Monitors vital signs and detects health anomalies."""
    
    def __init__(self):
        self.config = HEALTH_ANALYTICS
        self.thresholds = self.config['alert_thresholds']
        self.scaler = StandardScaler()
        self.anomaly_detector = IsolationForest(contamination=0.1, random_state=42)
    
    def monitor_anomalies(self, user_id: int, days: int = 14) -> dict:
        """Monitor vital signs and detect anomalies."""
        try:
            # Get vital signs data
            vital_data = db.get_user_vital_trends(user_id, days)
            
            if vital_data.empty:
                return {'message': 'No vital signs data available'}
            
            # Analyze each vital sign
            bp_analysis = self._analyze_blood_pressure(vital_data)
            hr_analysis = self._analyze_heart_rate(vital_data)
            temp_analysis = self._analyze_temperature(vital_data)
            weight_analysis = self._analyze_weight_trends(vital_data)
            glucose_analysis = self._analyze_glucose_levels(vital_data)
            
            # Overall anomaly detection
            overall_anomalies = self._detect_overall_anomalies(vital_data)
            
            # Risk assessment
            cardiovascular_risk = self._assess_cardiovascular_risk(vital_data)
            
            # Generate alerts
            alerts = self._generate_vital_alerts(bp_analysis, hr_analysis, temp_analysis, overall_anomalies)
            
            return {
                'user_id': user_id,
                'monitoring_period_days': days,
                'vital_signs_analysis': {
                    'blood_pressure': bp_analysis,
                    'heart_rate': hr_analysis,
                    'temperature': temp_analysis,
                    'weight': weight_analysis,
                    'glucose': glucose_analysis
                },
                'anomalies': overall_anomalies,
                'cardiovascular_risk': cardiovascular_risk,
                'alerts': alerts,
                'recommendations': self._generate_vital_recommendations(vital_data, alerts),
                'generated_at': datetime.now().isoformat()
            }
            
        except Exception as e:
            return {'error': f'Vital monitoring failed: {str(e)}'}
    
    def _analyze_blood_pressure(self, vital_data: pd.DataFrame) -> dict:
        """Analyze blood pressure patterns and trends."""
        if 'blood_pressure_systolic' not in vital_data.columns or 'blood_pressure_diastolic' not in vital_data.columns:
            return {'status': 'no_data'}
        
        systolic = vital_data['blood_pressure_systolic'].dropna()
        diastolic = vital_data['blood_pressure_diastolic'].dropna()
        
        if systolic.empty or diastolic.empty:
            return {'status': 'no_data'}
        
        # Basic statistics
        sys_stats = {
            'current': float(systolic.iloc[-1]) if len(systolic) > 0 else None,
            'average': round(systolic.mean(), 1),
            'min': float(systolic.min()),
            'max': float(systolic.max()),
            'trend': self._calculate_trend(systolic)
        }
        
        dia_stats = {
            'current': float(diastolic.iloc[-1]) if len(diastolic) > 0 else None,
            'average': round(diastolic.mean(), 1),
            'min': float(diastolic.min()),
            'max': float(diastolic.max()),
            'trend': self._calculate_trend(diastolic)
        }
        
        # Hypertension classification
        classification = self._classify_blood_pressure(sys_stats['average'], dia_stats['average'])
        
        # Variability analysis
        sys_variability = systolic.std()
        dia_variability = diastolic.std()
        
        # Alert conditions
        alerts = []
        high_sys_count = (systolic > self.thresholds['blood_pressure_high']).sum()
        high_dia_count = (diastolic > self.thresholds['blood_pressure_low']).sum()
        
        if high_sys_count > 0:
            alerts.append(f"High systolic pressure detected {high_sys_count} times")
        
        if sys_variability > 20:
            alerts.append("High blood pressure variability detected")
        
        return {
            'systolic': sys_stats,
            'diastolic': dia_stats,
            'classification': classification,
            'variability': {
                'systolic_std': round(sys_variability, 1),
                'diastolic_std': round(dia_variability, 1),
                'stability_rating': 'stable' if sys_variability < 15 else 'variable'
            },
            'high_reading_episodes': {
                'systolic_high': int(high_sys_count),
                'diastolic_high': int(high_dia_count)
            },
            'alerts': alerts,
            'data_points': len(systolic)
        }
    
    def _classify_blood_pressure(self, systolic: float, diastolic: float) -> dict:
        """Classify blood pressure according to medical guidelines."""
        if systolic < 90 or diastolic < 60:
            category = 'hypotension'
            risk_level = 'moderate'
        elif systolic < 120 and diastolic < 80:
            category = 'normal'
            risk_level = 'low'
        elif systolic < 130 and diastolic < 80:
            category = 'elevated'
            risk_level = 'low'
        elif systolic < 140 or diastolic < 90:
            category = 'stage_1_hypertension'
            risk_level = 'moderate'
        elif systolic < 180 or diastolic < 120:
            category = 'stage_2_hypertension'
            risk_level = 'high'
        else:
            category = 'hypertensive_crisis'
            risk_level = 'critical'
        
        return {
            'category': category,
            'risk_level': risk_level,
            'description': self._get_bp_description(category)
        }
    
    def _get_bp_description(self, category: str) -> str:
        """Get description for blood pressure category."""
        descriptions = {
            'hypotension': 'Blood pressure lower than normal',
            'normal': 'Blood pressure in normal range',
            'elevated': 'Blood pressure slightly elevated',
            'stage_1_hypertension': 'Stage 1 high blood pressure',
            'stage_2_hypertension': 'Stage 2 high blood pressure',
            'hypertensive_crisis': 'Dangerously high blood pressure requiring immediate attention'
        }
        return descriptions.get(category, 'Unknown blood pressure category')
    
    def _analyze_heart_rate(self, vital_data: pd.DataFrame) -> dict:
        """Analyze heart rate patterns."""
        if 'heart_rate' not in vital_data.columns:
            return {'status': 'no_data'}
        
        hr_data = vital_data['heart_rate'].dropna()
        
        if hr_data.empty:
            return {'status': 'no_data'}
        
        # Basic statistics
        stats = {
            'current': float(hr_data.iloc[-1]) if len(hr_data) > 0 else None,
            'average': round(hr_data.mean(), 1),
            'min': float(hr_data.min()),
            'max': float(hr_data.max()),
            'resting_hr_estimate': round(hr_data.quantile(0.25), 1),  # Lower quartile as estimate
            'trend': self._calculate_trend(hr_data)
        }
        
        # Heart rate classification
        classification = self._classify_heart_rate(stats['average'])
        
        # Variability analysis
        variability = hr_data.std()
        hrv_estimate = self._estimate_hrv(hr_data)
        
        # Anomaly detection
        hr_anomalies = self._detect_hr_anomalies(hr_data)
        
        return {
            'statistics': stats,
            'classification': classification,
            'variability': {
                'standard_deviation': round(variability, 1),
                'hrv_estimate': hrv_estimate,
                'stability_rating': 'stable' if variability < 15 else 'variable'
            },
            'anomalies': hr_anomalies,
            'data_points': len(hr_data)
        }
    
    def _classify_heart_rate(self, avg_hr: float) -> dict:
        """Classify heart rate according to medical guidelines."""
        if avg_hr < 50:
            category = 'severe_bradycardia'
            risk_level = 'high'
        elif avg_hr < 60:
            category = 'bradycardia'
            risk_level = 'moderate'
        elif avg_hr <= 100:
            category = 'normal'
            risk_level = 'low'
        elif avg_hr <= 120:
            category = 'mild_tachycardia'
            risk_level = 'moderate'
        else:
            category = 'tachycardia'
            risk_level = 'high'
        
        return {
            'category': category,
            'risk_level': risk_level,
            'description': self._get_hr_description(category)
        }
    
    def _get_hr_description(self, category: str) -> str:
        """Get description for heart rate category."""
        descriptions = {
            'severe_bradycardia': 'Severely slow heart rate',
            'bradycardia': 'Slow heart rate',
            'normal': 'Normal heart rate',
            'mild_tachycardia': 'Mildly elevated heart rate',
            'tachycardia': 'Fast heart rate'
        }
        return descriptions.get(category, 'Unknown heart rate category')
    
    def _estimate_hrv(self, hr_data: pd.Series) -> dict:
        """Estimate heart rate variability."""
        if len(hr_data) < 5:
            return {'status': 'insufficient_data'}
        
        # Calculate successive differences
        successive_diffs = hr_data.diff().dropna()
        
        # RMSSD approximation (Root Mean Square of Successive Differences)
        rmssd = np.sqrt(np.mean(successive_diffs**2))
        
        # SDNN approximation (Standard Deviation of Normal-to-Normal intervals)
        sdnn = hr_data.std()
        
        # HRV classification (simplified)
        if rmssd > 20:
            hrv_status = 'good'
        elif rmssd > 15:
            hrv_status = 'fair'
        else:
            hrv_status = 'poor'
        
        return {
            'rmssd_estimate': round(rmssd, 1),
            'sdnn_estimate': round(sdnn, 1),
            'hrv_status': hrv_status,
            'variability_score': round(rmssd / 30 * 100, 1)  # Normalized score
        }
    
    def _detect_hr_anomalies(self, hr_data: pd.Series) -> list:
        """Detect heart rate anomalies."""
        anomalies = []
        
        # Extreme values
        extremely_low = hr_data[hr_data < 40]
        extremely_high = hr_data[hr_data > 150]
        
        if len(extremely_low) > 0:
            anomalies.append({
                'type': 'severe_bradycardia_episodes',
                'count': len(extremely_low),
                'severity': 'high',
                'values': extremely_low.tolist()
            })
        
        if len(extremely_high) > 0:
            anomalies.append({
                'type': 'severe_tachycardia_episodes',
                'count': len(extremely_high),
                'severity': 'high',
                'values': extremely_high.tolist()
            })
        
        # Sudden changes
        if len(hr_data) > 1:
            hr_changes = hr_data.diff().abs()
            sudden_changes = hr_changes[hr_changes > 30]
            
            if len(sudden_changes) > 0:
                anomalies.append({
                    'type': 'sudden_hr_changes',
                    'count': len(sudden_changes),
                    'severity': 'medium',
                    'max_change': float(sudden_changes.max())
                })
        
        return anomalies
    
    def _analyze_temperature(self, vital_data: pd.DataFrame) -> dict:
        """Analyze body temperature patterns."""
        if 'temperature' not in vital_data.columns:
            return {'status': 'no_data'}
        
        temp_data = vital_data['temperature'].dropna()
        
        if temp_data.empty:
            return {'status': 'no_data'}
        
        # Convert to Celsius if needed (assume Fahrenheit if > 50)
        if temp_data.mean() > 50:
            temp_celsius = (temp_data - 32) * 5/9
        else:
            temp_celsius = temp_data
        
        # Basic statistics
        stats = {
            'current': round(temp_celsius.iloc[-1], 1) if len(temp_celsius) > 0 else None,
            'average': round(temp_celsius.mean(), 1),
            'min': round(temp_celsius.min(), 1),
            'max': round(temp_celsius.max(), 1),
            'trend': self._calculate_trend(temp_celsius)
        }
        
        # Temperature classification
        fever_episodes = (temp_celsius > 37.5).sum()
        hypothermia_episodes = (temp_celsius < 35.0).sum()
        
        classification = self._classify_temperature(stats['average'])
        
        return {
            'statistics': stats,
            'classification': classification,
            'fever_episodes': int(fever_episodes),
            'hypothermia_episodes': int(hypothermia_episodes),
            'temperature_stability': round(temp_celsius.std(), 2),
            'data_points': len(temp_celsius)
        }
    
    def _classify_temperature(self, avg_temp: float) -> dict:
        """Classify body temperature."""
        if avg_temp < 35.0:
            category = 'hypothermia'
            risk_level = 'high'
        elif avg_temp < 36.1:
            category = 'low_normal'
            risk_level = 'low'
        elif avg_temp <= 37.2:
            category = 'normal'
            risk_level = 'low'
        elif avg_temp <= 38.0:
            category = 'low_grade_fever'
            risk_level = 'moderate'
        elif avg_temp <= 39.0:
            category = 'fever'
            risk_level = 'moderate'
        else:
            category = 'high_fever'
            risk_level = 'high'
        
        return {
            'category': category,
            'risk_level': risk_level,
            'description': self._get_temp_description(category)
        }
    
    def _get_temp_description(self, category: str) -> str:
        """Get description for temperature category."""
        descriptions = {
            'hypothermia': 'Dangerously low body temperature',
            'low_normal': 'Below normal body temperature',
            'normal': 'Normal body temperature',
            'low_grade_fever': 'Mild fever',
            'fever': 'Moderate fever',
            'high_fever': 'High fever requiring medical attention'
        }
        return descriptions.get(category, 'Unknown temperature category')
    
    def _analyze_weight_trends(self, vital_data: pd.DataFrame) -> dict:
        """Analyze weight trends and changes."""
        if 'weight' not in vital_data.columns:
            return {'status': 'no_data'}
        
        weight_data = vital_data['weight'].dropna()
        
        if weight_data.empty:
            return {'status': 'no_data'}
        
        # Basic statistics
        stats = {
            'current': round(weight_data.iloc[-1], 1) if len(weight_data) > 0 else None,
            'average': round(weight_data.mean(), 1),
            'min': round(weight_data.min(), 1),
            'max': round(weight_data.max(), 1),
            'total_change': round(weight_data.iloc[-1] - weight_data.iloc[0], 1) if len(weight_data) > 1 else 0,
            'trend': self._calculate_trend(weight_data)
        }
        
        # Weight change analysis
        weight_changes = self._analyze_weight_changes(weight_data)
        
        return {
            'statistics': stats,
            'change_analysis': weight_changes,
            'data_points': len(weight_data)
        }
    
    def _analyze_weight_changes(self, weight_data: pd.Series) -> dict:
        """Analyze significant weight changes."""
        if len(weight_data) < 2:
            return {'status': 'insufficient_data'}
        
        # Calculate weekly weight change rate
        days_span = len(weight_data)
        total_change = weight_data.iloc[-1] - weight_data.iloc[0]
        weekly_rate = (total_change / days_span) * 7 if days_span > 0 else 0
        
        # Rapid weight loss/gain detection
        rapid_loss = weekly_rate < -1.0  # More than 1kg/week loss
        rapid_gain = weekly_rate > 1.0   # More than 1kg/week gain
        
        # Classification
        if rapid_loss:
            concern_level = 'high'
            description = 'Rapid weight loss detected'
        elif rapid_gain:
            concern_level = 'moderate'
            description = 'Rapid weight gain detected'
        elif abs(weekly_rate) > 0.5:
            concern_level = 'low'
            description = 'Moderate weight change'
        else:
            concern_level = 'none'
            description = 'Stable weight'
        
        return {
            'weekly_change_rate': round(weekly_rate, 2),
            'concern_level': concern_level,
            'description': description,
            'total_change_percent': round((total_change / weight_data.iloc[0]) * 100, 1) if weight_data.iloc[0] != 0 else 0
        }
    
    def _analyze_glucose_levels(self, vital_data: pd.DataFrame) -> dict:
        """Analyze blood glucose patterns."""
        if 'glucose_level' not in vital_data.columns:
            return {'status': 'no_data'}
        
        glucose_data = vital_data['glucose_level'].dropna()
        
        if glucose_data.empty:
            return {'status': 'no_data'}
        
        # Basic statistics
        stats = {
            'current': round(glucose_data.iloc[-1], 1) if len(glucose_data) > 0 else None,
            'average': round(glucose_data.mean(), 1),
            'min': round(glucose_data.min(), 1),
            'max': round(glucose_data.max(), 1),
            'trend': self._calculate_trend(glucose_data)
        }
        
        # Glucose classification
        high_episodes = (glucose_data > 140).sum()  # mg/dL
        low_episodes = (glucose_data < 70).sum()    # mg/dL
        
        classification = self._classify_glucose(stats['average'])
        
        return {
            'statistics': stats,
            'classification': classification,
            'high_glucose_episodes': int(high_episodes),
            'low_glucose_episodes': int(low_episodes),
            'glucose_variability': round(glucose_data.std(), 1),
            'data_points': len(glucose_data)
        }
    
    def _classify_glucose(self, avg_glucose: float) -> dict:
        """Classify blood glucose levels."""
        if avg_glucose < 70:
            category = 'hypoglycemia'
            risk_level = 'high'
        elif avg_glucose <= 99:
            category = 'normal'
            risk_level = 'low'
        elif avg_glucose <= 125:
            category = 'prediabetes'
            risk_level = 'moderate'
        else:
            category = 'diabetes_range'
            risk_level = 'high'
        
        return {
            'category': category,
            'risk_level': risk_level,
            'description': self._get_glucose_description(category)
        }
    
    def _get_glucose_description(self, category: str) -> str:
        """Get description for glucose category."""
        descriptions = {
            'hypoglycemia': 'Low blood sugar requiring immediate attention',
            'normal': 'Normal blood glucose levels',
            'prediabetes': 'Elevated glucose indicating diabetes risk',
            'diabetes_range': 'High glucose suggesting diabetes'
        }
        return descriptions.get(category, 'Unknown glucose category')
    
    def _calculate_trend(self, data: pd.Series) -> str:
        """Calculate trend direction for time series data."""
        if len(data) < 3:
            return 'insufficient_data'
        
        # Use linear regression to determine trend
        from sklearn.linear_model import LinearRegression
        
        X = np.arange(len(data)).reshape(-1, 1)
        model = LinearRegression()
        model.fit(X, data.values)
        
        slope = model.coef_[0]
        
        if abs(slope) < 0.01:
            return 'stable'
        elif slope > 0:
            return 'increasing'
        else:
            return 'decreasing'
    
    def _detect_overall_anomalies(self, vital_data: pd.DataFrame) -> list:
        """Detect anomalies across all vital signs using machine learning."""
        anomalies = []
        
        # Select numeric vital sign columns
        vital_cols = ['blood_pressure_systolic', 'blood_pressure_diastolic', 
                     'heart_rate', 'temperature', 'glucose_level']
        available_cols = [col for col in vital_cols if col in vital_data.columns]
        
        if len(available_cols) < 2:
            return anomalies
        
        # Prepare data for anomaly detection
        vital_subset = vital_data[available_cols].dropna()
        
        if len(vital_subset) < 5:
            return anomalies
        
        try:
            # Standardize data
            scaled_data = self.scaler.fit_transform(vital_subset)
            
            # Detect anomalies
            anomaly_labels = self.anomaly_detector.fit_predict(scaled_data)
            
            # Extract anomalous records
            anomaly_indices = np.where(anomaly_labels == -1)[0]
            
            for idx in anomaly_indices:
                original_idx = vital_subset.index[idx]
                anomaly_record = vital_subset.loc[original_idx]
                
                anomalies.append({
                    'timestamp': vital_data.loc[original_idx, 'created_at'].isoformat() if 'created_at' in vital_data.columns else 'unknown',
                    'type': 'vital_signs_anomaly',
                    'severity': 'medium',
                    'values': {col: float(anomaly_record[col]) for col in available_cols if not pd.isna(anomaly_record[col])},
                    'description': 'Unusual combination of vital signs detected'
                })
        
        except Exception as e:
            print(f"Anomaly detection error: {e}")
        
        return anomalies
    
    def _assess_cardiovascular_risk(self, vital_data: pd.DataFrame) -> dict:
        """Assess cardiovascular risk based on vital signs."""
        risk_factors = []
        risk_score = 0
        
        # Blood pressure risk
        if 'blood_pressure_systolic' in vital_data.columns:
            sys_bp = vital_data['blood_pressure_systolic'].dropna()
            if not sys_bp.empty:
                avg_sys = sys_bp.mean()
                if avg_sys > 140:
                    risk_factors.append('hypertension')
                    risk_score += 3
                elif avg_sys > 130:
                    risk_factors.append('elevated_blood_pressure')
                    risk_score += 2
        
        # Heart rate risk
        if 'heart_rate' in vital_data.columns:
            hr_data = vital_data['heart_rate'].dropna()
            if not hr_data.empty:
                avg_hr = hr_data.mean()
                hr_variability = hr_data.std()
                
                if avg_hr > 100:
                    risk_factors.append('tachycardia')
                    risk_score += 2
                elif avg_hr < 50:
                    risk_factors.append('bradycardia')
                    risk_score += 2
                
                if hr_variability > 20:
                    risk_factors.append('high_heart_rate_variability')
                    risk_score += 1
        
        # Overall risk assessment
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
            'medical_evaluation_recommended': risk_score >= 4
        }
    
    def _generate_vital_alerts(self, bp_analysis: dict, hr_analysis: dict, 
                             temp_analysis: dict, anomalies: list) -> list:
        """Generate actionable alerts based on vital signs analysis."""
        alerts = []
        
        # Blood pressure alerts
        if bp_analysis.get('classification', {}).get('risk_level') == 'critical':
            alerts.append({
                'type': 'critical_blood_pressure',
                'severity': 'critical',
                'message': 'Hypertensive crisis detected - immediate medical attention required',
                'action': 'emergency_medical_care'
            })
        elif bp_analysis.get('classification', {}).get('risk_level') == 'high':
            alerts.append({
                'type': 'high_blood_pressure',
                'severity': 'high',
                'message': 'Stage 2 hypertension detected - medical evaluation needed',
                'action': 'schedule_medical_appointment'
            })
        
        # Heart rate alerts
        if hr_analysis.get('classification', {}).get('risk_level') == 'high':
            alerts.append({
                'type': 'abnormal_heart_rate',
                'severity': 'high',
                'message': 'Abnormal heart rate detected - cardiology consultation recommended',
                'action': 'cardiology_referral'
            })
        
        # Temperature alerts
        if temp_analysis.get('classification', {}).get('risk_level') == 'high':
            alerts.append({
                'type': 'temperature_concern',
                'severity': 'medium',
                'message': 'Concerning temperature readings - monitor closely',
                'action': 'increase_monitoring'
            })
        
        # Anomaly alerts
        for anomaly in anomalies:
            alerts.append({
                'type': 'vital_anomaly',
                'severity': anomaly['severity'],
                'message': anomaly['description'],
                'action': 'investigate_pattern'
            })
        
        return alerts
    
    def _generate_vital_recommendations(self, vital_data: pd.DataFrame, alerts: list) -> list:
        """Generate recommendations for vital sign management."""
        recommendations = []
        
        # High-priority medical recommendations
        critical_alerts = [alert for alert in alerts if alert['severity'] == 'critical']
        if critical_alerts:
            recommendations.append({
                'type': 'emergency_care',
                'priority': 'critical',
                'suggestion': 'Seek immediate emergency medical care',
                'actions': [
                    'Call emergency services',
                    'Go to nearest emergency room',
                    'Do not delay medical attention'
                ]
            })
        
        # General vital sign monitoring
        recommendations.append({
            'type': 'monitoring_consistency',
            'priority': 'high',
            'suggestion': 'Maintain consistent vital sign monitoring',
            'actions': [
                'Regular blood pressure checks',
                'Daily heart rate monitoring',
                'Temperature tracking during illness',
                'Weight monitoring for fluid status'
            ]
        })
        
        # Lifestyle recommendations
        recommendations.append({
            'type': 'lifestyle_optimization',
            'priority': 'medium',
            'suggestion': 'Optimize lifestyle for cardiovascular health',
            'actions': [
                'Regular light exercise as approved by physician',
                'Heart-healthy diet with reduced sodium',
                'Stress management techniques',
                'Adequate hydration',
                'Regular sleep schedule'
            ]
        })
        
        # Medication compliance
        recommendations.append({
            'type': 'medication_adherence',
            'priority': 'medium',
            'suggestion': 'Maintain consistent medication regimen',
            'actions': [
                'Take blood pressure medications as prescribed',
                'Monitor for side effects',
                'Regular medication reviews with healthcare provider',
                'Use pill organizers for consistency'
            ]
        })
        
        return recommendations