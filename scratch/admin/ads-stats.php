<?php
// ads-stats.php - Fixed for clean JSON output

// Start output buffering to prevent accidental whitespace
ob_start();

// Set content type to JSON
header('Content-Type: application/json; charset=UTF-8');

// Database credentials
$servername = "localhost";
$username = "kkiqkkiu_scratch";
$password = "kkiqkkiu_scratch";
$dbname = "kkiqkkiu_scratch";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    ob_end_flush();
    exit;
}

// Validate admin PIN
$pin = $_GET['admin_pin'] ?? '';
$correct_pin = 'Silver1211$'; // Your admin PIN

if ($pin !== $correct_pin) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid or missing admin PIN'
    ]);
    ob_end_flush();
    exit;
}

// Fetch ad stats from adswatched table
// Replace 'adswatched' with your actual table name if different
$table = 'adswatched';

$sql = "
    SELECT 
        COUNT(*) as total_ads,
        SUM(CASE WHEN DATE(watch_date) = CURDATE() THEN 1 ELSE 0 END) as today_ads
    FROM $table
";

$result = $conn->query($sql);

if ($result) {
    $row = $result->fetch_assoc();
    $data = [
        'today_ads' => (int)$row['today_ads'],
        'total_ads' => (int)$row['total_ads']
    ];
    echo json_encode([
        'status' => 'success',
        'message' => 'Stats loaded',
        'data' => $data
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Query failed: ' . $conn->error
    ]);
}

// Close connection
$conn->close();
ob_end_flush();
?>