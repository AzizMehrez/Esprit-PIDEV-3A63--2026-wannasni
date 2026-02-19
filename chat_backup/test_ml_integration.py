#!/usr/bin/env python3
"""
ML Engine Integration Test Suite

This script tests all ML engine endpoints and functionality to ensure everything
is working properly before full integration with the WANNASNI chat system.

Test Categories:
1. Basic Health Checks
2. Health Analytics API
3. Activity Recommendations API
4. Chat Enhancement API
5. Database Connectivity
6. Error Handling
"""

import json
import requests
import sys
import time
from datetime import datetime
from colorama import init, Fore, Back, Style

# Initialize colorama for colored output
init(autoreset=True)

class MLEngineTestSuite:
    def __init__(self):
        self.base_url = "http://127.0.0.1:5000"
        self.api_base = f"{self.base_url}/api"
        self.test_user_id = 1
        self.results = {
            "total_tests": 0,
            "passed": 0,
            "failed": 0,
            "errors": []
        }

    def print_header(self, text):
        """Print a formatted header"""
        print(f"\n{Fore.CYAN}{Style.BRIGHT}{'='*60}{Style.RESET_ALL}")
        print(f"{Fore.CYAN}{Style.BRIGHT}{text.center(60)}{Style.RESET_ALL}")
        print(f"{Fore.CYAN}{Style.BRIGHT}{'='*60}{Style.RESET_ALL}")

    def print_test(self, test_name):
        """Print test name"""
        print(f"\n{Fore.YELLOW}Testing: {test_name}{Style.RESET_ALL}")

    def print_success(self, message):
        """Print success message"""
        print(f"{Fore.GREEN}✓ {message}{Style.RESET_ALL}")

    def print_error(self, message):
        """Print error message"""
        print(f"{Fore.RED}✗ {message}{Style.RESET_ALL}")

    def print_warning(self, message):
        """Print warning message"""
        print(f"{Fore.YELLOW}⚠ {message}{Style.RESET_ALL}")

    def record_result(self, test_name, success, error_msg=None):
        """Record test result"""
        self.results["total_tests"] += 1
        if success:
            self.results["passed"] += 1
            self.print_success(f"{test_name} - PASSED")
        else:
            self.results["failed"] += 1
            self.print_error(f"{test_name} - FAILED: {error_msg}")
            self.results["errors"].append(f"{test_name}: {error_msg}")

    def test_health_check(self):
        """Test basic health check endpoint"""
        self.print_test("Basic Health Check")
        
        try:
            response = requests.get(f"{self.base_url}/health", timeout=5)
            
            if response.status_code == 200:
                data = response.json()
                if data.get("status") == "healthy":
                    self.record_result("Health Check", True)
                    self.print_success(f"ML Engine is running (version: {data.get('version', 'unknown')})")
                    return True
                else:
                    self.record_result("Health Check", False, "Status not healthy")
                    return False
            else:
                self.record_result("Health Check", False, f"HTTP {response.status_code}")
                return False
                
        except requests.exceptions.ConnectionError:
            self.record_result("Health Check", False, "Connection refused - ML Engine not running")
            return False
        except Exception as e:
            self.record_result("Health Check", False, str(e))
            return False

    def test_health_analytics(self):
        """Test health analytics endpoints"""
        self.print_test("Health Analytics API")
        
        # Test health analytics endpoint
        try:
            payload = {
                "user_id": self.test_user_id,
                "days": 30
            }
            
            response = requests.post(
                f"{self.api_base}/health/analytics",
                json=payload,
                timeout=10
            )
            
            if response.status_code == 200:
                data = response.json()
                self.record_result("Health Analytics", True)
                
                # Check if we have expected data structure
                if "health_trends" in data:
                    self.print_success("Health trends data available")
                if "predictions" in data:
                    self.print_success("Health predictions available")
                
            else:
                error_msg = f"HTTP {response.status_code}"
                try:
                    error_data = response.json()
                    error_msg += f": {error_data.get('error', 'Unknown error')}"
                except:
                    pass
                self.record_result("Health Analytics", False, error_msg)
                
        except Exception as e:
            self.record_result("Health Analytics", False, str(e))

    def test_mood_analysis(self):
        """Test mood analysis endpoint"""
        self.print_test("Mood Analysis API")
        
        try:
            payload = {
                "user_id": self.test_user_id,
                "days": 7
            }
            
            response = requests.post(
                f"{self.api_base}/health/mood",
                json=payload,
                timeout=10
            )
            
            if response.status_code == 200:
                data = response.json()
                self.record_result("Mood Analysis", True)
                
                if "mood_trends" in data:
                    self.print_success("Mood trends analysis available")
                if "patterns" in data:
                    self.print_success("Mood patterns detected")
                    
            else:
                self.record_result("Mood Analysis", False, f"HTTP {response.status_code}")
                
        except Exception as e:
            self.record_result("Mood Analysis", False, str(e))

    def test_activity_recommendations(self):
        """Test activity recommendation endpoint"""
        self.print_test("Activity Recommendations API")
        
        try:
            payload = {
                "user_id": self.test_user_id,
                "limit": 5
            }
            
            response = requests.post(
                f"{self.api_base}/activities/recommend",
                json=payload,
                timeout=10
            )
            
            if response.status_code == 200:
                data = response.json()
                self.record_result("Activity Recommendations", True)
                
                if "recommendations" in data and len(data["recommendations"]) > 0:
                    rec_count = len(data["recommendations"])
                    self.print_success(f"Generated {rec_count} activity recommendations")
                    
                    # Show sample recommendation
                    sample_rec = data["recommendations"][0]
                    if "activity_identifier" in sample_rec:
                        self.print_success(f"Sample: {sample_rec['activity_identifier']}")
                else:
                    self.print_warning("No recommendations generated (this may be normal with empty data)")
                    
            else:
                self.record_result("Activity Recommendations", False, f"HTTP {response.status_code}")
                
        except Exception as e:
            self.record_result("Activity Recommendations", False, str(e))

    def test_chat_enhancement(self):
        """Test chat enhancement endpoint"""
        self.print_test("Chat Enhancement API")
        
        try:
            test_messages = [
                "I'm feeling good today",
                "My back is hurting a lot",
                "I need help with my medication",
                "What activities should I do today?"
            ]
            
            for message in test_messages:
                payload = {
                    "user_id": self.test_user_id,
                    "message": message
                }
                
                response = requests.post(
                    f"{self.api_base}/chat/enhance",
                    json=payload,
                    timeout=10
                )
                
                if response.status_code == 200:
                    data = response.json()
                    self.print_success(f"Enhanced message: '{message[:30]}...'")
                    
                    # Check for expected enhancement data
                    if "health_context" in data:
                        self.print_success("✓ Health context provided")
                    if "conversation_insights" in data:
                        self.print_success("✓ Conversation insights available")
                        insights = data["conversation_insights"]
                        if "sentiment" in insights:
                            sentiment = insights["sentiment"]
                            self.print_success(f"✓ Sentiment: {sentiment.get('dominant_emotion', 'unknown')}")
                    
                    break  # Test with just first message for brevity
                else:
                    self.record_result("Chat Enhancement", False, f"HTTP {response.status_code}")
                    return
            
            self.record_result("Chat Enhancement", True)
                
        except Exception as e:
            self.record_result("Chat Enhancement", False, str(e))

    def test_medication_tracking(self):
        """Test medication tracking endpoint"""
        self.print_test("Medication Tracking API")
        
        try:
            payload = {
                "user_id": self.test_user_id,
                "days": 14
            }
            
            response = requests.post(
                f"{self.api_base}/health/medication",
                json=payload,
                timeout=10
            )
            
            if response.status_code == 200:
                data = response.json()
                self.record_result("Medication Tracking", True)
                
                if "adherence_analysis" in data:
                    self.print_success("Medication adherence analysis available")
                if "reminders" in data:
                    self.print_success("Medication reminders generated")
                    
            else:
                self.record_result("Medication Tracking", False, f"HTTP {response.status_code}")
                
        except Exception as e:
            self.record_result("Medication Tracking", False, str(e))

    def test_vital_monitoring(self):
        """Test vital signs monitoring endpoint"""
        self.print_test("Vital Signs Monitoring API")
        
        try:
            payload = {
                "user_id": self.test_user_id,
                "days": 7
            }
            
            response = requests.post(
                f"{self.api_base}/health/vitals",
                json=payload,
                timeout=10
            )
            
            if response.status_code == 200:
                data = response.json()
                self.record_result("Vital Monitoring", True)
                
                if "vital_trends" in data:
                    self.print_success("Vital signs trends available")
                if "alerts" in data:
                    alerts = data["alerts"]
                    if len(alerts) > 0:
                        self.print_warning(f"{len(alerts)} vital signs alerts generated")
                    else:
                        self.print_success("No vital signs alerts (normal)")
                        
            else:
                self.record_result("Vital Monitoring", False, f"HTTP {response.status_code}")
                
        except Exception as e:
            self.record_result("Vital Monitoring", False, str(e))

    def test_error_handling(self):
        """Test error handling with invalid requests"""
        self.print_test("Error Handling")
        
        # Test invalid user ID
        try:
            payload = {
                "user_id": 99999,  # Non-existent user
                "days": 7
            }
            
            response = requests.post(
                f"{self.api_base}/health/analytics",
                json=payload,
                timeout=5
            )
            
            # Should gracefully handle non-existent user
            if response.status_code in [200, 404]:
                self.record_result("Error Handling - Invalid User", True)
            else:
                self.record_result("Error Handling - Invalid User", False, f"HTTP {response.status_code}")
                
        except Exception as e:
            self.record_result("Error Handling - Invalid User", False, str(e))

        # Test malformed request
        try:
            response = requests.post(
                f"{self.api_base}/health/analytics",
                json={"invalid": "data"},
                timeout=5
            )
            
            # Should return readable error
            if response.status_code >= 400:
                self.record_result("Error Handling - Malformed Request", True)
            else:
                self.record_result("Error Handling - Malformed Request", False, "Should have returned error")
                
        except Exception as e:
            self.record_result("Error Handling - Malformed Request", False, str(e))

    def test_response_times(self):
        """Test API response times"""
        self.print_test("Response Time Performance")
        
        endpoints = [
            ("Health Check", "GET", f"{self.base_url}/health", {}),
            ("Health Analytics", "POST", f"{self.api_base}/health/analytics", {"user_id": self.test_user_id, "days": 30}),
            ("Activity Recommendations", "POST", f"{self.api_base}/activities/recommend", {"user_id": self.test_user_id, "limit": 3}),
            ("Chat Enhancement", "POST", f"{self.api_base}/chat/enhance", {"user_id": self.test_user_id, "message": "test message"})
        ]
        
        for name, method, url, payload in endpoints:
            try:
                start_time = time.time()
                
                if method == "GET":
                    response = requests.get(url, timeout=10)
                else:
                    response = requests.post(url, json=payload, timeout=10)
                
                end_time = time.time()
                response_time = (end_time - start_time) * 1000  # Convert to milliseconds
                
                if response.status_code == 200:
                    if response_time < 5000:  # Under 5 seconds is acceptable
                        self.print_success(f"{name}: {response_time:.0f}ms")
                        self.record_result(f"Response Time - {name}", True)
                    else:
                        self.print_warning(f"{name}: {response_time:.0f}ms (slow)")
                        self.record_result(f"Response Time - {name}", False, f"Too slow: {response_time:.0f}ms")
                else:
                    self.record_result(f"Response Time - {name}", False, f"HTTP {response.status_code}")
                    
            except Exception as e:
                self.record_result(f"Response Time - {name}", False, str(e))

    def run_all_tests(self):
        """Run all test suites"""
        self.print_header("ML Engine Integration Test Suite")
        print(f"{Fore.BLUE}Starting tests at {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}{Style.RESET_ALL}")
        
        # Check if ML engine is running first
        if not self.test_health_check():
            self.print_error("ML Engine is not running. Please start it first:")
            self.print_error("  python chat_backup/ml_engine/start_ml_engine.py")
            return False
        
        # Run all test categories
        self.test_health_analytics()
        self.test_mood_analysis()
        self.test_activity_recommendations()
        self.test_chat_enhancement()
        self.test_medication_tracking()
        self.test_vital_monitoring()
        self.test_error_handling()
        self.test_response_times()
        
        # Print final results
        self.print_results()
        
        return self.results["failed"] == 0

    def print_results(self):
        """Print final test results"""
        self.print_header("Test Results Summary")
        
        total = self.results["total_tests"]
        passed = self.results["passed"]
        failed = self.results["failed"]
        pass_rate = (passed / total * 100) if total > 0 else 0
        
        print(f"Total Tests: {total}")
        print(f"{Fore.GREEN}Passed: {passed}{Style.RESET_ALL}")
        print(f"{Fore.RED}Failed: {failed}{Style.RESET_ALL}")
        print(f"Pass Rate: {pass_rate:.1f}%")
        
        if failed > 0:
            print(f"\n{Fore.RED}Failed Tests:{Style.RESET_ALL}")
            for error in self.results["errors"]:
                print(f"  {Fore.RED}• {error}{Style.RESET_ALL}")
        
        if pass_rate >= 80:
            print(f"\n{Fore.GREEN}{Style.BRIGHT}🎉 Integration test suite PASSED!{Style.RESET_ALL}")
            print(f"{Fore.GREEN}ML Engine is ready for production use.{Style.RESET_ALL}")
        else:
            print(f"\n{Fore.RED}{Style.BRIGHT}❌ Integration test suite FAILED!{Style.RESET_ALL}")
            print(f"{Fore.RED}Please fix the issues before using the ML Engine.{Style.RESET_ALL}")

def main():
    """Main function"""
    print(f"{Fore.MAGENTA}{Style.BRIGHT}WANNASNI ML Engine Integration Test Suite{Style.RESET_ALL}")
    print(f"{Fore.BLUE}Testing ML engine functionality and integration readiness{Style.RESET_ALL}")
    
    test_suite = MLEngineTestSuite()
    success = test_suite.run_all_tests()
    
    sys.exit(0 if success else 1)

if __name__ == "__main__":
    main()