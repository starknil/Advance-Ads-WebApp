<?php
require 'config.php';
require 'init_data_check.php';

header('Content-Type: application/json');

// Read and decode input JSON
$data = json_decode(file_get_contents("php://input"), true);
if ($data === null) {
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON input.']);
    exit;
}

$user_id = $data['user_id'] ?? 0;
$init_data = $data['init_data'] ?? '';

// Security check
$verification = verifyTelegramWebApp($config['bot_token'], $init_data);
if (!$verification['ok']) {
    echo json_encode(['ok' => false, 'message' => 'Init data verification failed.']);
    exit;
}

$init_data_array = $verification['data'];
if (!isset($init_data_array['user']['id']) || (int)$init_data_array['user']['id'] !== (int)$user_id) {
    echo json_encode(['ok' => false, 'message' => 'User ID mismatch.']);
    exit;
}

// Fetch user
try {
    $stmt = $pdo->prepare("SELECT user_id, name, balance, scratch_card, completed_task_ids FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['ok' => false, 'message' => 'User not found']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Get completed task IDs
$completed_task_ids = json_decode($user['completed_task_ids'] ?? '[]', true);
if (!is_array($completed_task_ids)) {
    $completed_task_ids = [];
}

// Fetch all tasks
try {
    $task_stmt = $pdo->query("SELECT task_id, name, url, type, icon, reward, `limit`, completed FROM tasks");
    $all_tasks = $task_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'message' => 'Task fetch error: ' . $e->getMessage()]);
    exit;
}

// Filter tasks: only show tasks not completed by user
$filtered_tasks = array_filter($all_tasks, function ($task) use ($completed_task_ids) {
    return !in_array($task['task_id'], $completed_task_ids);
});

// Fetch currency from settings
try {
    $setting_stmt = $pdo->query("SELECT currency FROM settings LIMIT 1");
    $currency_row = $setting_stmt->fetch(PDO::FETCH_ASSOC);
    $currency = $currency_row['currency'] ?? 'INR';
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'message' => 'Settings fetch error: ' . $e->getMessage()]);
    exit;
}

// Decode scratch_card and ensure it’s an array
$scratch_card = json_decode($user['scratch_card'] ?? '[]', true);
if (!is_array($scratch_card)) {
    $scratch_card = [];
}

// Final response
echo json_encode([
    'ok' => true,
    'user_details' => [
        'name' => $user['name'],
        'user_id' => $user['user_id'],
        'balance' => number_format((float)$user['balance'], 2, '.', '') // Ensure decimal with 2 places
    ],
    'tasks' => array_values($filtered_tasks),
    'scratch_card' => $scratch_card,
    'currency' => $currency
]);
?>