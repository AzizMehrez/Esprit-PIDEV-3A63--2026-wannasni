<?php
/**
 * Quick script to list all tables in the wannasni database
 */

require_once 'db_config.php';

$conn = getDBConnection();

echo "<h2>Tables in 'wannasni' database:</h2>";
echo "<ul>";

$result = $conn->query("SHOW TABLES");

if ($result->num_rows > 0) {
    while($row = $result->fetch_array()) {
        echo "<li><strong>" . $row[0] . "</strong></li>";
    }
} else {
    echo "<li>No tables found</li>";
}

echo "</ul>";

closeDBConnection($conn);
?>
