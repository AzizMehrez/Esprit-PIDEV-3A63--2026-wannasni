<?php
/**
 * ML Engine Configuration for PHP Integration
 * Connects the existing WANNASNI system with Python ML services
 */

// Include existing database configuration
require_once 'db_config.php';

// ML Engine API Configuration
define('ML_API_HOST', 'http://127.0.0.1:5000');
define('ML_API_TIMEOUT', 30);

/**
 * ML Engine endpoints
 */
class MLEndpoints {
    const HEALTH_PREDICTION = '/api/health/predict';
    const HEALTH_ANALYTICS = '/api/health/analytics';
    const ACTIVITY_RECOMMEND = '/api/activities/recommend';
    const CHAT_ENHANCE = '/api/chat/enhance';
    const MOOD_ANALYZE = '/api/health/mood-analyze';
    const MEDICATION_TRACK = '/api/health/medication-track';
    const VITAL_MONITOR = '/api/health/vital-monitor';
}

/**
 * Make API call to ML Engine
 */
function callMLAPI($endpoint, $data = [], $method = 'POST') {
    $url = ML_API_HOST . $endpoint;
    
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => ML_API_TIMEOUT,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ];
    
    if ($method === 'POST' && !empty($data)) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    
    $curl = curl_init();
    curl_setopt_array($curl, $options);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    if (curl_errno($curl)) {
        error_log('ML API Error: ' . curl_error($curl));
        curl_close($curl);
        return ['error' => 'ML service unavailable'];
    }
    
    curl_close($curl);
    
    if ($httpCode !== 200) {
        error_log("ML API HTTP Error: $httpCode");
        return ['error' => 'ML service error'];
    }
    
    $decoded = json_decode($response, true);
    return $decoded ?? ['error' => 'Invalid ML response'];
}

/**
 * Get health analytics for a user
 */
function getHealthAnalytics($userId, $days = 30) {
    return callMLAPI(MLEndpoints::HEALTH_ANALYTICS, [
        'user_id' => $userId,
        'days' => $days
    ]);
}

/**
 * Get activity recommendations for a user
 */
function getActivityRecommendations($userId, $limit = 5) {
    return callMLAPI(MLEndpoints::ACTIVITY_RECOMMEND, [
        'user_id' => $userId,
        'limit' => $limit
    ]);
}

/**
 * Enhance chat message with ML insights
 */
function enhanceChatMessage($userId, $message, $context = []) {
    return callMLAPI(MLEndpoints::CHAT_ENHANCE, [
        'user_id' => $userId,
        'message' => $message,
        'context' => $context
    ]);
}

/**
 * Analyze user mood from recent health journal entries
 */
function analyzeMood($userId, $days = 7) {
    return callMLAPI(MLEndpoints::MOOD_ANALYZE, [
        'user_id' => $userId,
        'days' => $days
    ]);
}

/**
 * Check medication adherence and get predictions
 */
function checkMedicationAdherence($userId, $days = 30) {
    return callMLAPI(MLEndpoints::MEDICATION_TRACK, [
        'user_id' => $userId,
        'days' => $days
    ]);
}

/**
 * Monitor vital signs and detect anomalies
 */
function monitorVitalSigns($userId, $days = 14) {
    return callMLAPI(MLEndpoints::VITAL_MONITOR, [
        'user_id' => $userId,
        'days' => $days
    ]);
}

/**
 * Check if ML Engine is available
 */
function isMLEngineAvailable() {
    $ch = curl_init(ML_API_HOST . '/health');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

/**
 * Log ML Engine interactions for debugging
 */
function logMLInteraction($endpoint, $data, $response) {
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'endpoint' => $endpoint,
        'data' => $data,
        'response' => $response
    ];
    
    error_log('ML_INTERACTION: ' . json_encode($log));
}
?>