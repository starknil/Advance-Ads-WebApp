<?php
require 'config.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: https://tgbro.link');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

http_response_code(200);

// Read URL parameters and request body
$params = $_GET ?: [];
$body = json_decode(file_get_contents("php://input"), true) ?: [];

// Validate input
$user_id = $params['user_id'] ?? '';
$status = $body['status'] ?? '';
$sent_amount = isset($body['txs'][0]['sent_amount']) ? (float)$body['txs'][0]['sent_amount'] : 0;

if (!$user_id || !in_array($status, ['Paying', 'Paid']) || $sent_amount <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid user_id, status, or sent_amount.']);
    exit;
}

// Validate user existence
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['ok' => false, 'message' => 'User not found.']);
        exit;
    }
    $user_internal_id = $user['id'];
} catch (PDOException $e) {
    error_log("User validation failed: " . $e->getMessage(), 3, 'error.log');
    echo json_encode(['ok' => false, 'message' => 'Database error validating user: ' . $e->getMessage()]);
    exit;
}

// Send Telegram notification
$telegram_message = '';
if ($status === 'Paying') {
    $telegram_message = urlencode("Your transaction (Track ID: {$body['track_id']}) of {$sent_amount} USDT is being processed on the blockchain.");
} elseif ($status === 'Paid') {
    $telegram_message = urlencode("Your transaction (Track ID: {$body['track_id']}) of {$sent_amount} USDT has been paid successfully.");
}

$telegram_url = "https://api.telegram.org/bot{$config['bot_token']}/sendMessage?chat_id={$user_id}&text={$telegram_message}";

$ch = curl_init($telegram_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
$response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    error_log("Telegram notification failed: " . $curl_error, 3, 'error.log');
    echo json_encode(['ok' => false, 'message' => 'Failed to send Telegram notification: ' . $curl_error]);
    exit;
}

$telegram_response = json_decode($response, true);
if (!$telegram_response['ok']) {
    error_log("Telegram API error: " . ($telegram_response['description'] ?? 'Unknown error'), 3, 'error.log');
    echo json_encode(['ok' => false, 'message' => 'Telegram API error: ' . ($telegram_response['description'] ?? 'Unknown error')]);
    exit;
}

// Update task_balance for Paid status
if ($status === 'Paid') {
    try {
        $stmt = $pdo->prepare("SELECT task_balance FROM mytask WHERE id = ?");
        $stmt->execute([$user_internal_id]);
        $mytask = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mytask) {
            $stmt = $pdo->prepare("INSERT INTO mytask (id, tasks, task_balance, deposit_address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_internal_id, '[]', 0.00, null]);
            $mytask = ['task_balance' => 0.00];
        }

        $new_task_balance = $mytask['task_balance'] + $sent_amount;
        $stmt = $pdo->prepare("UPDATE mytask SET task_balance = ? WHERE id = ?");
        $stmt->execute([$new_task_balance, $user_internal_id]);
    } catch (PDOException $e) {
        error_log("Task balance update failed: " . $e->getMessage(), 3, 'error.log');
        echo json_encode(['ok' => false, 'message' => 'Database error updating task balance: ' . $e->getMessage()]);
        exit;
    }
}

// Return success response
echo json_encode(['ok' => true, 'message' => "Notification sent for status: {$status}. Task balance updated for Paid status."]);
?>