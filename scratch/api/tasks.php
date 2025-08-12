<?php
require 'config.php';
require 'init_data_check.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: https://tgbro.link');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Read and decode input JSON
$data = json_decode(file_get_contents("php://input"), true);
if ($data === null) {
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON input.', 'result' => []]);
    exit;
}

$user_id = $data['user_id'] ?? 0;
$init_data = $data['init_data'] ?? '';
$action = $data['action'] ?? '';
$payload = $data['payload'] ?? [];

// Validate input parameters
if (!$user_id || !in_array($action, ['add', 'edit', 'remove'])) {
    echo json_encode(['ok' => false, 'message' => 'Missing or invalid user_id or action.', 'result' => []]);
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

// Fetch members_per_dollar from settings
try {
    $stmt = $pdo->query("SELECT members_per_dollar FROM settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    $members_per_dollar = $settings['members_per_dollar'] ?? 0;
    if ($members_per_dollar <= 0) {
        echo json_encode(['ok' => false, 'message' => 'Invalid members_per_dollar in settings.', 'result' => []]);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'message' => 'Database error fetching settings: ' . $e->getMessage(), 'result' => []]);
    exit;
}

// Fetch mytask data
try {
    $stmt = $pdo->prepare("SELECT tasks, task_balance FROM mytask WHERE id = ?");
    $stmt->execute([$user_internal_id]);
    $mytask = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mytask) {
        $stmt = $pdo->prepare("INSERT INTO mytask (id, tasks, task_balance, deposit_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_internal_id, '[]', 0.00, null]);
        $mytask = ['tasks' => '[]', 'task_balance' => 0.00];
    }
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'message' => 'Database error fetching mytask: ' . $e->getMessage(), 'result' => []]);
    exit;
}

$task_ids = json_decode($mytask['tasks'] ?? '[]', true);
if (!is_array($task_ids)) {
    $task_ids = [];
}

try {
    $pdo->beginTransaction();

    if ($action === 'add') {
        $amount = (float)($payload['amount'] ?? 0);
        if ($amount <= 0) {
            echo json_encode(['ok' => false, 'message' => 'Invalid or missing amount.', 'result' => []]);
            exit;
        }

        if ($mytask['task_balance'] < $amount) {
            echo json_encode(['ok' => false, 'message' => 'Insufficient task balance.', 'result' => []]);
            exit;
        }

        $limit = (int)($amount * $members_per_dollar);
        $task_id = bin2hex(random_bytes(8));

        $stmt = $pdo->prepare("INSERT INTO tasks (task_id, name, url, type, icon, reward, `limit`, completed, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)");
        $stmt->execute([
            $task_id,
            $payload['name'] ?? '',
            $payload['url'] ?? '',
            $payload['type'] ?? 'others',
            $payload['icon'] ?? '',
            'silver',
            $limit,
            $user_id
        ]);

        $task_ids[] = $task_id;
        $new_task_balance = $mytask['task_balance'] - $amount;

        $stmt = $pdo->prepare("UPDATE mytask SET tasks = ?, task_balance = ? WHERE id = ?");
        $stmt->execute([json_encode($task_ids), $new_task_balance, $user_internal_id]);

        $pdo->commit();

        $task_details = [
            'task_id' => $task_id,
            'name' => $payload['name'] ?? '',
            'url' => $payload['url'] ?? '',
            'type' => $payload['type'] ?? 'others',
            'icon' => $payload['icon'] ?? '',
            'reward' => 'silver',
            'limit' => $limit,
            'completed' => 0,
            'created_by' => $user_id
        ];

        echo json_encode([
            'ok' => true,
            'message' => 'Task added successfully.',
            'result' => [$task_details]
        ]);
        exit;
    }

    if ($action === 'edit') {
        $task_id = $payload['task_id'] ?? '';
        if (empty($task_id)) {
            echo json_encode(['ok' => false, 'message' => 'Task ID is required.', 'result' => []]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT name, url, type, icon, `limit`, completed FROM tasks WHERE task_id = ? AND created_by = ?");
        $stmt->execute([$task_id, $user_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            echo json_encode(['ok' => false, 'message' => 'Task not found or not owned by user.', 'result' => []]);
            exit;
        }

        $fields = [];
        $params = [];
        $allowed_fields = ['name', 'url', 'icon'];

        foreach ($allowed_fields as $field) {
            if (isset($payload[$field])) {
                $fields[] = "`$field` = ?";
                $params[] = $payload[$field];
            }
        }

        if (empty($fields)) {
            echo json_encode(['ok' => false, 'message' => 'No fields provided to update.', 'result' => []]);
            exit;
        }

        $query = "UPDATE tasks SET " . implode(', ', $fields) . " WHERE task_id = ? AND created_by = ?";
        $params[] = $task_id;
        $params[] = $user_id;

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $stmt = $pdo->prepare("SELECT task_id, name, url, type, icon, reward, `limit`, completed, created_by FROM tasks WHERE task_id = ?");
        $stmt->execute([$task_id]);
        $task_details = $stmt->fetch(PDO::FETCH_ASSOC);

        $pdo->commit();

        echo json_encode([
            'ok' => true,
            'message' => 'Task updated successfully.',
            'result' => [$task_details]
        ]);
        exit;
    }

    if ($action === 'remove') {
        $task_id = $payload['task_id'] ?? '';
        if (empty($task_id)) {
            echo json_encode(['ok' => false, 'message' => 'Task ID is required.', 'result' => []]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE task_id = ? AND created_by = ?");
        $stmt->execute([$task_id, $user_id]);
        if ($stmt->fetchColumn() == 0) {
            echo json_encode(['ok' => false, 'message' => 'Task not found or not owned by user.', 'result' => []]);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM tasks WHERE task_id = ? AND created_by = ?");
        $stmt->execute([$task_id, $user_id]);

        $task_ids = array_diff($task_ids, [$task_id]);
        $stmt = $pdo->prepare("UPDATE mytask SET tasks = ? WHERE id = ?");
        $stmt->execute([json_encode($task_ids), $user_internal_id]);

        $pdo->commit();

        echo json_encode([
            'ok' => true,
            'message' => 'Task removed successfully.',
            'result' => []
        ]);
        exit;
    }
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Action $action failed: " . $e->getMessage(), 3, 'error.log');
    echo json_encode(['ok' => false, 'message' => 'Database error: ' . $e->getMessage(), 'result' => []]);
    exit;
}

/* Example Request JSON (Add):
{
    "user_id": 6116644414,
    "init_data": "test786",
    "action": "add",
    "payload": {
        "amount": 1.5,
        "name": "Join Telegram",
        "url": "https://t.me/example",
        "type": "telegram_chat",
        "icon": "https://example.com/icon.png"
    }
}

Example Success Response JSON (Add):
{
    "ok": true,
    "message": "Task added successfully.",
    "result": [
        {
            "task_id": "a1b2c3d4e5f67890",
            "name": "Join Telegram",
            "url": "https://t.me/example",
            "type": "telegram_chat",
            "icon": "https://example.com/icon.png",
            "reward": "silver",
            "limit": 1500,
            "completed": 0,
            "created_by": 6116644414
        }
    ]
}

Example Request JSON (Edit):
{
    "user_id": 6116644414,
    "init_data": "test786",
    "action": "edit",
    "payload": {
        "task_id": "a1b2c3d4e5f67890",
        "name": "Updated Task",
        "url": "https://t.me/newlink",
        "icon": "https://example.com/newicon.png"
    }
}

Example Success Response JSON (Edit):
{
    "ok": true,
    "message": "Task updated successfully.",
    "result": [
        {
            "task_id": "a1b2c3d4e5f67890",
            "name": "Updated Task",
            "url": "https://t.me/newlink",
            "type": "telegram_chat",
            "icon": "https://example.com/newicon.png",
            "reward": "silver",
            "limit": 1500,
            "completed": 0,
            "created_by": 6116644414
        }
    ]
}

Example Request JSON (Remove):
{
    "user_id": 6116644414,
    "init_data": "test786",
    "action": "remove",
    "payload": {
        "task_id": "a1b2c3d4e5f67890"
    }
}

Example Success Response JSON (Remove):
{
    "ok": true,
    "message": "Task removed successfully.",
    "result": []
}

Example Error Response JSON:
{
    "ok": false,
    "message": "Insufficient task balance.",
    "result": []
}
*/
?>