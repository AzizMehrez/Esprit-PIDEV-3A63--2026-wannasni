<?php
/**
 * Database API Endpoint
 * Handles AI chatbot requests to query and manipulate the database
 */

require_once 'db_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit();
}

$action = $input['action'] ?? '';

// Connect to database
$conn = getDBConnection();

try {
    switch ($action) {
        case 'get_schema':
            // Get database schema (tables and columns)
            $result = getSchema($conn);
            break;
            
        case 'query':
            // Execute a SELECT query
            $sql = $input['sql'] ?? '';
            $result = executeQuery($conn, $sql);
            break;
            
        case 'execute':
            // Execute INSERT, UPDATE, DELETE
            $sql = $input['sql'] ?? '';
            $result = executeCommand($conn, $sql);
            break;
            
        case 'get_tables':
            // List all tables
            $result = getTables($conn);
            break;
            
        case 'get_table_data':
            // Get data from a specific table
            $table = $input['table'] ?? '';
            $limit = $input['limit'] ?? 100;
            $result = getTableData($conn, $table, $limit);
            break;
            
        default:
            throw new Exception('Unknown action: ' . $action);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    closeDBConnection($conn);
}

/**
 * Get database schema (tables and columns)
 */
function getSchema($conn) {
    $schema = [];
    
    // Get all tables
    $tables = $conn->query("SHOW TABLES");
    
    while ($table = $tables->fetch_array()) {
        $tableName = $table[0];
        
        // Get columns for this table
        $columns = $conn->query("DESCRIBE `$tableName`");
        $columnList = [];
        
        while ($col = $columns->fetch_assoc()) {
            $columnList[] = [
                'name' => $col['Field'],
                'type' => $col['Type'],
                'null' => $col['Null'],
                'key' => $col['Key'],
                'default' => $col['Default']
            ];
        }
        
        $schema[$tableName] = $columnList;
    }
    
    return $schema;
}

/**
 * Execute a SELECT query
 */
function executeQuery($conn, $sql) {
    // Basic SQL injection prevention - only allow SELECT
    if (!preg_match('/^\s*SELECT/i', $sql)) {
        throw new Exception('Only SELECT queries are allowed for query action');
    }
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Query error: ' . $conn->error);
    }
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

/**
 * Execute INSERT, UPDATE, DELETE commands
 */
function executeCommand($conn, $sql) {
    // Allow INSERT, UPDATE, DELETE
    if (!preg_match('/^\s*(INSERT|UPDATE|DELETE)/i', $sql)) {
        throw new Exception('Only INSERT, UPDATE, DELETE are allowed for execute action');
    }
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Execute error: ' . $conn->error);
    }
    
    return [
        'affected_rows' => $conn->affected_rows,
        'insert_id' => $conn->insert_id
    ];
}

/**
 * Get list of all tables
 */
function getTables($conn) {
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    return $tables;
}

/**
 * Get data from a specific table
 */
function getTableData($conn, $table, $limit = 100) {
    // Sanitize table name
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    
    if (empty($table)) {
        throw new Exception('Invalid table name');
    }
    
    $limit = (int)$limit;
    $sql = "SELECT * FROM `$table` LIMIT $limit";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Query error: ' . $conn->error);
    }
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}
?>
