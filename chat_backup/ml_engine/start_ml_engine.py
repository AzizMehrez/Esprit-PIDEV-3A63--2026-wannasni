"""
ML Engine Startup Script

Starts the WANNASNI ML Engine API server for integration
with the existing chat system.
"""

import os
import sys
import logging
from pathlib import Path

# Add current directory to Python path
current_dir = Path(__file__).parent
sys.path.insert(0, str(current_dir))

def setup_logging():
    """Setup logging configuration."""
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
        handlers=[
            logging.FileHandler('ml_engine.log'),
            logging.StreamHandler(sys.stdout)
        ]
    )

def check_dependencies():
    """Check if required dependencies are available."""
    required_packages = [
        'flask', 'pandas', 'numpy', 'sklearn', 
        'pymysql', 'sqlalchemy', 'requests'
    ]
    
    missing_packages = []
    
    for package in required_packages:
        try:
            __import__(package)
        except ImportError:
            missing_packages.append(package)
    
    if missing_packages:
        print(f"Missing packages: {', '.join(missing_packages)}")
        print("Please install missing packages using: pip install [package_name]")
        return False
    
    return True

def test_database_connection():
    """Test database connection."""
    try:
        from utils.database import db
        # Try a simple query
        result = db.execute_query("SELECT 1 as test")
        logging.info("Database connection successful")
        return True
    except Exception as e:
        logging.error(f"Database connection failed: {e}")
        print("Database connection failed. Please check your database configuration.")
        return False

def main():
    """Main entry point."""
    print("Starting WANNASNI ML Engine...")
    
    # Setup logging
    setup_logging()
    
    # Check dependencies
    if not check_dependencies():
        sys.exit(1)
    
    # Test database connection
    if not test_database_connection():
        print("Warning: Database connection failed. Some features may not work.")
    
    # Import and start the Flask app
    try:
        from ml_api import app
        
        print("ML Engine API Server starting...")
        print("API will be available at: http://127.0.0.1:5000")
        print("Health check endpoint: http://127.0.0.1:5000/health")
        print("\nPress Ctrl+C to stop the server")
        
        app.run(host='127.0.0.1', port=5000, debug=True)
        
    except KeyboardInterrupt:
        print("\nML Engine stopped by user")
    except Exception as e:
        logging.error(f"Failed to start ML Engine: {e}")
        print(f"Error starting ML Engine: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()