"""
Database utilities for ML Engine

Provides database connection and common query functions
for accessing WANNASNI data.
"""

import pymysql
import pandas as pd
from sqlalchemy import create_engine
import logging
from typing import Optional, Dict, List, Any
from datetime import datetime, timedelta

try:
    from .config import DATABASE_CONFIG
except ImportError:
    from utils.config import DATABASE_CONFIG

class DatabaseManager:
    """Manages database connections and common queries for ML operations."""
    
    def __init__(self):
        self.config = DATABASE_CONFIG
        self.engine = None
        self._setup_connection()
    
    def _setup_connection(self):
        """Setup SQLAlchemy engine for pandas integration."""
        try:
            connection_string = (
                f"mysql+pymysql://{self.config['user']}:{self.config['password']}"
                f"@{self.config['host']}/{self.config['database']}"
                f"?charset={self.config['charset']}"
            )
            self.engine = create_engine(connection_string)
            logging.info("Database connection established")
        except Exception as e:
            logging.error(f"Database connection failed: {e}")
            raise
    
    def get_connection(self):
        """Get raw pymysql connection."""
        return pymysql.connect(
            host=self.config['host'],
            user=self.config['user'],
            password=self.config['password'],
            database=self.config['database'],
            charset=self.config['charset'],
            autocommit=self.config.get('autocommit', False)
        )
    
    def execute_query(self, query: str, params: Dict = None) -> pd.DataFrame:
        """Execute query and return results as pandas DataFrame."""
        try:
            return pd.read_sql(query, self.engine, params=params)
        except Exception as e:
            logging.error(f"Query execution failed: {e}")
            raise
    
    def get_user_health_data(self, user_id: int, days: int = 30) -> pd.DataFrame:
        """Get health journal data for a specific user."""
        query = """
        SELECT hj.*, u.age, u.gender
        FROM health_journal hj
        JOIN user u ON hj.user_id = u.id
        WHERE hj.user_id = %(user_id)s 
        AND hj.created_at >= DATE_SUB(NOW(), INTERVAL %(days)s DAY)
        ORDER BY hj.created_at DESC
        """
        return self.execute_query(query, {'user_id': user_id, 'days': days})
    
    def get_user_activities(self, user_id: int, days: int = 30) -> pd.DataFrame:
        """Get user participation data."""
        query = """
        SELECT p.*, a.title, a.category, a.description, a.mood_impact
        FROM participations p
        JOIN activites a ON p.activites_id = a.id
        WHERE p.user_id = %(user_id)s 
        AND p.participation_date >= DATE_SUB(NOW(), INTERVAL %(days)s DAY)
        ORDER BY p.participation_date DESC
        """
        return self.execute_query(query, {'user_id': user_id, 'days': days})
    
    def get_user_medications(self, user_id: int) -> pd.DataFrame:
        """Get user medication/treatment data."""
        query = """
        SELECT t.*, u.age, u.gender
        FROM treatment t
        JOIN user u ON t.user_id = u.id
        WHERE t.user_id = %(user_id)s 
        AND t.is_active = 1
        ORDER BY t.created_at DESC
        """
        return self.execute_query(query, {'user_id': user_id})
    
    def get_all_users_health_summary(self, days: int = 7) -> pd.DataFrame:
        """Get health summary for all users for recommendation training."""
        query = """
        SELECT 
            u.id as user_id,
            u.age,
            u.gender,
            u.center_id,
            AVG(hj.mood_score) as avg_mood,
            AVG(hj.sleep_hours) as avg_sleep,
            AVG(hj.pain_level) as avg_pain,
            AVG(hj.appetite_level) as avg_appetite,
            COUNT(hj.id) as health_entries
        FROM user u
        LEFT JOIN health_journal hj ON u.id = hj.user_id 
            AND hj.created_at >= DATE_SUB(NOW(), INTERVAL %(days)s DAY)
        WHERE u.is_active = 1
        GROUP BY u.id
        """
        return self.execute_query(query, {'days': days})
    
    def get_activity_participation_matrix(self, days: int = 90) -> pd.DataFrame:
        """Get user-activity participation matrix for collaborative filtering."""
        query = """
        SELECT 
            p.user_id,
            p.activites_id as activity_id,
            a.category,
            AVG(p.mood_before) as avg_mood_before,
            AVG(p.mood_after) as avg_mood_after,
            COUNT(p.id) as participation_count
        FROM participations p
        JOIN activites a ON p.activites_id = a.id
        WHERE p.participation_date >= DATE_SUB(NOW(), INTERVAL %(days)s DAY)
        GROUP BY p.user_id, p.activites_id
        """
        return self.execute_query(query, {'days': days})
    
    def get_user_vital_trends(self, user_id: int, days: int = 30) -> pd.DataFrame:
        """Get vital signs trends for anomaly detection."""
        query = """
        SELECT 
            blood_pressure_systolic,
            blood_pressure_diastolic,
            heart_rate,
            temperature,
            weight,
            glucose_level,
            created_at
        FROM health_journal 
        WHERE user_id = %(user_id)s 
        AND created_at >= DATE_SUB(NOW(), INTERVAL %(days)s DAY)
        AND (blood_pressure_systolic IS NOT NULL 
             OR blood_pressure_diastolic IS NOT NULL 
             OR heart_rate IS NOT NULL)
        ORDER BY created_at ASC
        """
        return self.execute_query(query, {'user_id': user_id, 'days': days})
    
    def save_ml_prediction(self, user_id: int, prediction_type: str, 
                          prediction_data: Dict, confidence: float):
        """Save ML prediction results."""
        try:
            connection = self.get_connection()
            with connection.cursor() as cursor:
                query = """
                INSERT INTO ml_predictions 
                (user_id, prediction_type, prediction_data, confidence, created_at)
                VALUES (%s, %s, %s, %s, %s)
                """
                cursor.execute(query, (
                    user_id,
                    prediction_type,
                    str(prediction_data),
                    confidence,
                    datetime.now()
                ))
            connection.commit()
            connection.close()
        except Exception as e:
            logging.error(f"Failed to save ML prediction: {e}")
    
    def get_chat_history(self, user_id: int, days: int = 7) -> List[Dict]:
        """Get recent chat history for context analysis."""
        query = """
        SELECT message, response, created_at
        FROM chat_history 
        WHERE user_id = %(user_id)s 
        AND created_at >= DATE_SUB(NOW(), INTERVAL %(days)s DAY)
        ORDER BY created_at DESC
        LIMIT 50
        """
        df = self.execute_query(query, {'user_id': user_id, 'days': days})
        return df.to_dict('records') if not df.empty else []

# Global database manager instance
db = DatabaseManager()