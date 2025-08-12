<?php
// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0); // Set to 0 in production, log errors instead
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Set output buffering to prevent premature output
ob_start();

// Set headers for UTF-8 and CORS
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *'); // Restrict to specific origins in production
header('Access-Control-Allow-Methods: GET, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Include database configuration
require_once 'config.php';

// Function to send JSON response
function sendResponse($status, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    $response = [
        'status' => $status,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    ob_end_flush();
    exit;
}

// Function to validate admin pin
function validateAdminPin($pin) {
    global $config;
    return is_string($pin) && $pin === $config['admin_pin'];
}

// Check if PDO is properly initialized
if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("Database connection failed: PDO not initialized", 3, 'error.log');
    sendResponse('error', 'Database connection failed', null, 500);
}

// Ensure UTF-8 encoding for PDO
try {
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Failed to configure PDO for UTF-8: " . $e->getMessage(), 3, 'error.log');
    sendResponse('error', 'Database encoding error: ' . $e->getMessage(), null, 500);
}

// Verify database and table encoding
try {
    $stmt = $pdo->query("SHOW VARIABLES LIKE 'character_set_database'");
    $dbCharset = $stmt->fetch(PDO::FETCH_ASSOC)['Value'];
    if ($dbCharset !== 'utf8mb4') {
        error_log("Database character set is $dbCharset, expected utf8mb4", 3, 'error.log');
    }
    $stmt = $pdo->query("SHOW TABLE STATUS WHERE Name = 'users'");
    $table = $stmt->fetch(PDO::FETCH_ASSOC);
    $collation = $table['Collation'] ?? '';
    if (strpos($collation, 'utf8mb4') !== 0) {
        error_log("Table 'users' collation is $collation, expected utf8mb4_unicode_ci", 3, 'error.log');
    }
} catch (PDOException $e) {
    error_log("Failed to verify database encoding: " . $e->getMessage(), 3, 'error.log');
}

// Handle API requests
$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';

try {
    // Validate admin pin for all requests
    $pin = isset($_GET['admin_pin']) ? trim($_GET['admin_pin']) : '';
    if ($method !== 'GET' && !validateAdminPin($pin)) {
        sendResponse('error', 'Invalid admin pin', null, 401);
    }

    switch ($method) {
        case 'GET':
            if ($path === 'users') {
                // Fetch all users
                $stmt = $pdo->query("SELECT id, name, user_id, ip_address, balance, scratch_card, created_at, reflist, completed_task_ids FROM users");
                $users = $stmt->fetchAll();
                
                // Decode JSON fields and ensure name is UTF-8
                foreach ($users as &$user) {
                    $user['name'] = mb_convert_encoding($user['name'], 'UTF-8', 'UTF-8');
                    $user['scratch_card'] = json_decode($user['scratch_card'], true) ?? [];
                    $user['reflist'] = json_decode($user['reflist'], true) ?? [];
                    $user['completed_task_ids'] = json_decode($user['completed_task_ids'], true) ?? [];
                }
                unset($user);
                
                sendResponse('success', 'Users fetched successfully', $users);
            } elseif (preg_match('/^users\/(\d+)$/', $path, $matches)) {
                // Fetch single user by user_id
                $user_id = filter_var($matches[1], FILTER_VALIDATE_INT);
                if ($user_id === false) {
                    sendResponse('error', 'Invalid user ID', null, 400);
                }
                $stmt = $pdo->prepare("SELECT id, name, user_id, ip_address, balance, scratch_card, created_at, reflist, completed_task_ids FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $user['name'] = mb_convert_encoding($user['name'], 'UTF-8', 'UTF-8');
                    $user['scratch_card'] = json_decode($user['scratch_card'], true) ?? [];
                    $user['reflist'] = json_decode($user['reflist'], true) ?? [];
                    $user['completed_task_ids'] = json_decode($user['completed_task_ids'], true) ?? [];
                    sendResponse('success', 'User fetched successfully', $user);
                } else {
                    sendResponse('error', 'User not found', null, 404);
                }
            } else {
                sendResponse('error', 'Invalid endpoint', null, 404);
            }
            break;

        case 'DELETE':
            if (preg_match('/^users\/(\d+)$/', $path, $matches)) {
                // Delete user by user_id
                $user_id = filter_var($matches[1], FILTER_VALIDATE_INT);
                if ($user_id === false) {
                    sendResponse('error', 'Invalid user ID', null, 400);
                }

                // Check if user exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                if (!$user) {
                    sendResponse('error', 'User not found', null, 404);
                }

                // Delete user and related withdraw history
                $pdo->beginTransaction();
                try {
                    // Delete from withdraw_history
                    $stmt = $pdo->prepare("DELETE FROM withdraw_history WHERE user_id = ?");
                    $stmt->execute([$user_id]);

                    // Delete from users
                    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                    $stmt->execute([$user_id]);

                    $pdo->commit();
                    sendResponse('success', 'User deleted successfully');
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Delete failed: " . $e->getMessage(), 3, 'error.log');
                    sendResponse('error', 'Failed to delete user: ' . $e->getMessage(), null, 500);
                }
            } else {
                sendResponse('error', 'Invalid endpoint', null, 404);
            }
            break;

        default:
            sendResponse('error', 'Method not allowed', null, 405);
    }
} catch (Exception $e) {
    error_log("Server error: " . $e->getMessage(), 3, 'error.log');
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}

// Clean output buffer
ob_end_flush();
?>