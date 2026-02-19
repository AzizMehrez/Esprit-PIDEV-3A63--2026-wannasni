"""
Simple ML Engine Startup Script

Starts the WANNASNI ML Engine API server.
"""

import os
import sys
from pathlib import Path

# Add current directory to Python path
current_dir = Path(__file__).parent
sys.path.insert(0, str(current_dir))

def main():
    """Main entry point."""
    print("🚀 Starting WANNASNI ML Engine...")
    
    # Import and start the Flask app
    try:
        from ml_api import app
        
        print("✅ ML Engine API Server starting...")
        print("🌐 API available at: http://127.0.0.1:5000")
        print("❤️  Health check: http://127.0.0.1:5000/health")
        print("\n⚡ Press Ctrl+C to stop the server")
        print("-" * 50)
        
        app.run(host='127.0.0.1', port=5000, debug=False)
        
    except KeyboardInterrupt:
        print("\n👋 ML Engine stopped by user")
    except Exception as e:
        print(f"❌ Error starting ML Engine: {e}")
        print("\n💡 Troubleshooting tips:")
        print("   1. Make sure you're in the project root directory")
        print("   2. Check if virtual environment is activated")
        print("   3. Verify all dependencies are installed")
        sys.exit(1)

if __name__ == "__main__":
    main()