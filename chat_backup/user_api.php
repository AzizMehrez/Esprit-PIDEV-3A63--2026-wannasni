<?php
/**
 * User Context API
 * Returns info about the current logged-in user for the chat AI
 * The chat calls this on load to give the AI user context
 */

require_once 'db_config.php';

// Accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$conn = getDBConnection();

try {
    // Check if a user_id is passed (from query string or POST)
    $userId = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $userId = $_GET['user_id'] ?? null;
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $input['user_id'] ?? null;
    }

    if (!$userId) {
        // No user specified — return the first admin user as default
        $stmt = $conn->prepare("SELECT id, email, first_name, last_name, phone, roles, status, created_at, date_naissance, adresse, ville, code_postal, pays, location, user_domain FROM user WHERE JSON_CONTAINS(roles, '\"ROLE_ADMIN\"') LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $stmt = $conn->prepare("SELECT id, email, first_name, last_name, phone, roles, status, created_at, date_naissance, adresse, ville, code_postal, pays, location, user_domain FROM user WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
    }

    $user = $result->fetch_assoc();

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }

    // Get user's related data summary
    $userId = $user['id'];
    $summary = [];

    // Health journal entries count
    $r = $conn->query("SELECT COUNT(*) as cnt FROM health_journal WHERE senior_id = $userId");
    $summary['health_entries'] = $r->fetch_assoc()['cnt'];

    // Activities/participations
    $r = $conn->query("SELECT COUNT(*) as cnt FROM participations WHERE senior_id = $userId");
    $summary['participations'] = $r->fetch_assoc()['cnt'];

    // Diet requests
    $r = $conn->query("SELECT COUNT(*) as cnt FROM demande_regime WHERE user_id = $userId");
    $summary['diet_requests'] = $r->fetch_assoc()['cnt'];

    // Prescribed diets
    $r = $conn->query("SELECT COUNT(*) as cnt FROM regime_prescrit WHERE user_id = $userId");
    $summary['prescribed_diets'] = $r->fetch_assoc()['cnt'];

    // Service requests
    $r = $conn->query("SELECT COUNT(*) as cnt FROM service_request WHERE user_id = $userId");
    $summary['service_requests'] = $r->fetch_assoc()['cnt'];

    // Treatments
    $r = $conn->query("SELECT COUNT(*) as cnt FROM treatment WHERE senior_id = $userId");
    $summary['treatments'] = $r->fetch_assoc()['cnt'];

    // Notifications
    $r = $conn->query("SELECT COUNT(*) as cnt FROM notification WHERE is_read = 0");
    $summary['unread_notifications'] = $r->fetch_assoc()['cnt'];

    echo json_encode([
        'success' => true,
        'user' => $user,
        'summary' => $summary
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    closeDBConnection($conn);
}
?>
