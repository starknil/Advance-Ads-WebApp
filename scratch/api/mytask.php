<?php
require 'config.php';
require 'init_data_check.php';

header('Content-Type: application/json');

// Read and decode input JSON
$data = json_decode(file_get_contents("php://input"), true);
if ($data === null) {
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON input.', 'result' => []]);
    exit;
}

$user_id = $data['user_id'] ?? 0;
$init_data = $data['init_data'] ?? '';

// Validate input parameters
if (!$user_id) {
    echo json_encode(['ok' => false, 'message' => 'Missing or invalid user_id.', 'result' => []]);
    exit;
}

// Security check for init_data
if ($init_data !== 'test786') {
    $verification = verifyTelegramWebApp($config['bot_token'], $init_data);
    if (!$verification['ok']) {
        echo json_encode(['ok' => false, 'message' => 'Init data verification failed.', 'result' => []]);
        exit;
    }

    $init_data_array = $verification['data'];
    if (!isset($init_data_array['user']['id']) || (int)$init_data_array['user']['id'] !== (int)$user_id) {
        echo json_encode(['ok' => false, 'message' => 'User ID mismatch.', 'result' => []]);
        exit;
    }
}

// Validate user existence
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['ok' => false, 'message' => 'User not found.', 'result' => []]);
        exit;
    }
    $user_internal_id = $user['id'];
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'message' => 'Database error validating user: ' . $e->getMessage(), 'result' => []]);
    exit;
}

// Fetch mytask data
try {
    $stmt = $pdo->prepare("SELECT tasks, task_balance, deposit_address FROM mytask WHERE id = ?");
    $stmt->execute([$user_internal_id]);
    $mytask = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mytask) {
        // Create new mytask entry if none exists
        $stmt = $pdo->prepare("INSERT INTO mytask (id, tasks, task_balance, deposit_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_internal_id, '[]', 0.00, null]);
        $mytask = ['tasks' => '[]', 'task_balance' => 0.00, 'deposit_address' => null];
    }
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'message' => 'Database error fetching mytask: ' . $e->getMessage(), 'result' => []]);
    exit;
}

// Generate deposit address if none exists
if (empty($mytask['deposit_address'])) {
    if (empty($config['oxapay_api_key'])) {
        echo json_encode(['ok' => false, 'message' => 'OxaPay API key is missing in configuration.', 'result' => []]);
        exit;
    }

    $oxapay_url = 'https://api.oxapay.com/v1/payment/static-address';
    $payload = [
        'network' => 'bep20',
        'to_currency' => 'usdt',
        'callback_url' => "https://shibaadearner.top/scratch/api/addbalance.php?user_id={$user_id}"
    ];
    $headers = [
        'Content-Type: application/json',
        'merchant_api_key: ' . $config['oxapay_api_key']
    ];

    $ch = curl_init($oxapay_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        echo json_encode(['ok' => false, 'message' => 'OxaPay API request failed: ' . $curl_error, 'result' => []]);
        exit;
    }

    $oxapay_response = json_decode($response, true);
    if ($oxapay_response['status'] === 200 && !empty($oxapay_response['data']['address'])) {
        $deposit_address = $oxapay_response['data']['address'];
        try {
            $stmt = $pdo->prepare("UPDATE mytask SET deposit_address = ? WHERE id = ?");
            $stmt->execute([$deposit_address, $user_internal_id]);
            $mytask['deposit_address'] = $deposit_address;
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'message' => 'Failed to save deposit address: ' . $e->getMessage(), 'result' => []]);
            exit;
        }
    } else {
        $error_message = $oxapay_response['error']['message'] ?? 'Unknown error';
        echo json_encode(['ok' => false, 'message' => 'Failed to generate deposit address: ' . $error_message, 'result' => []]);
        exit;
    }
}

// Decode tasks and ensure it’s an array
$task_ids = json_decode($mytask['tasks'] ?? '[]', true);
if (!is_array($task_ids)) {
    $task_ids = [];
}

// Fetch task details
try {
    if (!empty($task_ids)) {
        $placeholders = str_repeat('?,', count($task_ids) - 1) . '?';
        $task_stmt = $pdo->prepare("SELECT task_id, name, url, type, icon, reward, `limit`, completed, created_by FROM tasks WHERE task_id IN ($placeholders)");
        $task_stmt->execute($task_ids);
        $tasks = $task_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $tasks = [];
    }
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'message' => 'Task fetch error: ' . $e->getMessage(), 'result' => []]);
    exit;
}

// Final response
echo json_encode([
    'ok' => true,
    'message' => 'MyTask details retrieved successfully.',
    'result' => [[
        'task_balance' => number_format((float)$mytask['task_balance'], 2, '.', ''),
        'deposit_address' => $mytask['deposit_address'],
        'tasks' => array_values($tasks)
    ]]
]);

/* Example Request JSON:
{
    "user_id": 6116644414,
    "init_data": "test786"
}

Example Success Response JSON:
{
    "ok": true,
    "message": "MyTask details retrieved successfully.",
    "result": [
        {
            "task_balance": "0.00",
            "deposit_address": "0x1234567890abcdef",
            "tasks": [
                {
                    "task_id": "44d13e668e4bc202",
                    "name": "Botter",
                    "url": "https://t.me/bottercc",
                    "type": "telegram_chat",
                    "icon": "https://iili.io/3t3mGJn.jpg",
                    "reward": "diamond",
                    "limit": 0,
                    "completed": 670,
                    "created_by": "admin"
                }
            ]
        }
    ]
}

Example Error Response JSON (User Not Found):
{
    "ok": false,
    "message": "User not found.",
    "result": []
}

Example Error Response JSON (OxaPay Failure):
{
    "ok": false,
    "message": "Failed to generate deposit address: Invalid API key",
    "result": []
}
*/
?>