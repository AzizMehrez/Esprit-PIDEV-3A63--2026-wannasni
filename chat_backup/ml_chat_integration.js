/**
 * ML Integration for WANNASNI Chat System - Simplified Version
 * 
 * Works with existing Nexus AI chat interface to provide ML insights
 * in the background without requiring new HTML elements.
 */

class MLChatIntegration {
    constructor() {
        this.mlApiBaseUrl = 'http://127.0.0.1:5000/api';
        this.mlAvailable = false;
        this.userId = null;
        this.init();
    }

    async init() {
        await this.checkMLAvailability();
        this.setupMLStatusIndicator();
        this.initializeEventListeners();
        console.log('🤖 ML Chat Integration initialized');
    }

    async checkMLAvailability() {
        try {
            const response = await fetch('http://127.0.0.1:5000/health');
            if (response.ok) {
                this.mlAvailable = true;
                console.log('✅ ML Engine connected successfully');
                return true;
            }
        } catch (error) {
            console.log('⚠️ ML Engine not available:', error);
            this.mlAvailable = false;
            return false;
        }
    }

    setupMLStatusIndicator() {
        // Add a simple status indicator to the existing chat header
        const chatHeader = document.querySelector('.chat-header');
        if (chatHeader && this.mlAvailable) {
            const statusIndicator = document.createElement('div');
            statusIndicator.id = 'ml-status-indicator';
            statusIndicator.innerHTML = '🤖 AI+';
            statusIndicator.style.cssText = `
                position: absolute;
                top: 8px;
                right: 8px;
                background: #28a745;
                color: white;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 10px;
                font-weight: bold;
                z-index: 1000;
            `;
            statusIndicator.title = 'ML Engine Active - Enhanced health insights enabled';
            chatHeader.appendChild(statusIndicator);
        }
    }

    initializeEventListeners() {
        if (!this.mlAvailable) return;

        // Monitor for new messages to show ML insights
        this.observeChatMessages();
        
        // Get user ID (adjust based on your auth system)
        this.userId = this.getCurrentUserId();
        
        console.log(`🤖 ML Integration active for user ${this.userId}`);
    }

    getCurrentUserId() {
        // Try to get user ID from the API context
        if (window.SmartChatAPI && window.SmartChatAPI.currentUser) {
            return window.SmartChatAPI.currentUser.id;
        }
        return 1; // Default fallback
    }

    observeChatMessages() {
        const messagesContainer = document.getElementById('chat-messages');
        if (!messagesContainer) return;

        // Monitor chat messages for ML-enhanced responses
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    // Check for new bot messages that might benefit from ML insights
                    mutation.addedNodes.forEach((node) => {
                        if (node.classList && node.classList.contains('bot-message')) {
                            this.enhanceMessage(node);
                        }
                    });
                }
            });
        });

        observer.observe(messagesContainer, {
            childList: true,
            subtree: true
        });
    }

    async enhanceMessage(messageElement) {
        if (!this.mlAvailable) return;

        // Add subtle ML enhancement indicator
        const mlIndicator = document.createElement('span');
        mlIndicator.innerHTML = ' 🤖';
        mlIndicator.title = 'Enhanced with ML health insights';
        mlIndicator.style.cssText = 'opacity: 0.6; font-size: 12px; margin-left: 5px;';
        
        const timestamp = messageElement.querySelector('.timestamp');
        if (timestamp) {
            timestamp.appendChild(mlIndicator);
        }
    }

    // Public method to get ML insights (used by the API)
    async getMLInsights(message) {
        if (!this.mlAvailable) return null;

        try {
            const userId = this.getCurrentUserId();
            
            // Get chat enhancement insights
            const chatResponse = await fetch(`${this.mlApiBaseUrl}/chat/enhance`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId,
                    message: message
                })
            });

            // Get activity recommendations
            const activityResponse = await fetch(`${this.mlApiBaseUrl}/activities/recommend`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId,
                    limit: 3
                })
            });

            const chatData = chatResponse.ok ? await chatResponse.json() : null;
            const activityData = activityResponse.ok ? await activityResponse.json() : null;

            return {
                health_context: chatData?.health_context || null,
                conversation_insights: chatData?.conversation_insights || null,
                activity_recommendations: activityData?.recommendations || null
            };
        } catch (error) {
            console.warn('ML insights not available:', error);
            return null;
        }
    }

    // Method to display health summary when requested
    async showHealthSummary() {
        if (!this.mlAvailable) {
            console.log('ML Engine not available');
            return null;
        }

        try {
            const userId = this.getCurrentUserId();
            
            const healthResponse = await fetch(`${this.mlApiBaseUrl}/health/analytics`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId,
                    days: 7
                })
            });

            if (healthResponse.ok) {
                const healthData = await healthResponse.json();
                return healthData;
            }
        } catch (error) {
            console.error('Health summary not available:', error);
        }
        
        return null;
    }
}

// Initialize ML integration when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Wait a bit for the main chat to initialize
    setTimeout(() => {
        window.mlChatIntegration = new MLChatIntegration();
    }, 1000);
});

// Export for use in other scripts
window.MLChatIntegration = MLChatIntegration;