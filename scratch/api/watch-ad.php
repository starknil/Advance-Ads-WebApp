<?php
header('Content-Type: application/json');

// Database connection configuration
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "ads_webapp";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Connection failed: ' . $conn->connect_error]));
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;
$action = isset($input['action']) ? $input['action'] : 'register';

if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
    exit;
}

// Get settings
$settings_query = "SELECT reward_per_ads, daily_limit FROM settings WHERE id = 1";
$settings_result = $conn->query($settings_query);

if (!$settings_result || $settings_result->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Settings not found']);
    $conn->close();
    exit;
}

$settings = $settings_result->fetch_assoc();
$reward_per_ads = $settings['reward_per_ads'];
$hourly_limit = $settings['daily_limit']; // Used as hourly limit

$current_time = date('Y-m-d H:i:s');
$one_hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));

// Count ads watched in the last 1 hour
$count_query = "SELECT COUNT(*) as watch_count FROM adswatched WHERE user_id = ? AND watch_time BETWEEN ? AND ?";
$stmt = $conn->prepare($count_query);
$stmt->bind_param("iss", $user_id, $one_hour_ago, $current_time);
$stmt->execute();
$count_result = $stmt->get_result();
$watch_count = $count_result->fetch_assoc()['watch_count'];

// Total ads watched all time
$total_count_query = "SELECT COUNT(*) as total_watched FROM adswatched WHERE user_id = ?";
$stmt = $conn->prepare($total_count_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_result = $stmt->get_result();
$total_watched = $total_result->fetch_assoc()['total_watched'];

$response = ['status' => 'success'];

if ($action === 'details') {
    $response['message'] = 'Ad watch details';
    $response['total_ads_watched'] = $total_watched;
    $response['ads_left_this_hour'] = max(0, $hourly_limit - $watch_count);
    $response['reward_per_ads'] = $reward_per_ads;
} else {
    if ($watch_count >= $hourly_limit) {
        $response = ['status' => 'error', 'message' => 'Hourly ad watch limit reached'];
    } else {
        $conn->begin_transaction();
        try {
            // Insert ad watch record
            $insert_query = "INSERT INTO adswatched (user_id, watch_time) VALUES (?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("is", $user_id, $current_time);
            $stmt->execute();

            // Update user balance
            $update_query = "UPDATE users SET balance = balance + ? WHERE user_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("di", $reward_per_ads, $user_id);
            $stmt->execute();

            $conn->commit();
            $response['message'] = 'Ad watch recorded';
            $response['new_balance'] = getUserBalance($conn, $user_id);
            $response['watches_this_hour'] = $watch_count + 1;
            $response['earned'] = $reward_per_ads;
        } catch (Exception $e) {
            $conn->rollback();
            $response = ['status' => 'error', 'message' => 'Failed to record ad watch: ' . $e->getMessage()];
        }
    }
}

echo json_encode($response);
$conn->close();

function getUserBalance($conn, $user_id) {
    $query = "SELECT balance FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['balance'];
}
?>