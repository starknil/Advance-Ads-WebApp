<?php
// Include configuration
require 'config.php';

// Enable error logging (disable display in production)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Set headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: https://tgbro.link'); // Restrict to specific origin
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Validate admin pin
$admin_pin = $_GET['admin_pin'] ?? $_POST['admin_pin'] ?? null;
if ($admin_pin !== $config['admin_pin']) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized access. Invalid or missing admin PIN.']);
    exit;
}

// Check PDO connection
if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("Database connection failed: PDO not initialized", 3, 'error.log');
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Database connection failed']);
    exit;
}

// Handle GET: Fetch all tasks
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("SELECT task_id, name, url, type, icon, reward, `limit`, completed FROM tasks");
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'tasks' => $tasks]);
    } catch (PDOException $e) {
        error_log("GET tasks failed: " . $e->getMessage(), 3, 'error.log');
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle POST: Add, edit, delete
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? '';
$payload = $data['payload'] ?? [];

if (!in_array($action, ['add', 'edit', 'delete'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    if ($action === 'add') {
        $task_id = bin2hex(random_bytes(8));
        $stmt = $pdo->prepare("INSERT INTO tasks (task_id, name, url, type, icon, reward, `limit`, completed) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([
            $task_id,
            $payload['name'] ?? '',
            $payload['url'] ?? '',
            $payload['type'] ?? 'others',
            $payload['icon'] ?? '',
            $payload['reward'] ?? 'silver',
            (int)($payload['limit'] ?? 0)
        ]);
        echo json_encode(['ok' => true, 'message' => 'Task added', 'task_id' => $task_id]);
        exit;
    }

    if ($action === 'edit') {
        // Validate task_id
        if (empty($payload['task_id'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Task ID is required']);
            exit;
        }

        // Check if task exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE task_id = ?");
        $stmt->execute([$payload['task_id']]);
        if ($stmt->fetchColumn() == 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Task not found']);
            exit;
        }

        // Build dynamic UPDATE query to preserve completed if not provided
        $fields = [];
        $params = [];
        $allowed_fields = ['name', 'url', 'type', 'icon', 'reward', 'limit', 'completed'];
        
        foreach ($allowed_fields as $field) {
            if (isset($payload[$field])) {
                $fields[] = "`$field` = ?";
                $params[] = $field === 'limit' || $field === 'completed' ? (int)$payload[$field] : $payload[$field];
            }
        }

        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'No fields provided to update']);
            exit;
        }

        $query = "UPDATE tasks SET " . implode(', ', $fields) . " WHERE task_id = ?";
        $params[] = $payload['task_id'];

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        echo json_encode(['ok' => true, 'message' => 'Task updated']);
        exit;
    }

    if ($action === 'delete') {
        if (empty($payload['task_id'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Task ID is required']);
            exit;
        }

        // Check if task exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE task_id = ?");
        $stmt->execute([$payload['task_id']]);
        if ($stmt->fetchColumn() == 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Task not found']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM tasks WHERE task_id = ?");
        $stmt->execute([$payload['task_id']]);
        echo json_encode(['ok' => true, 'message' => 'Task deleted']);
        exit;
    }
} catch (Exception $e) {
    error_log("Action $action failed: " . $e->getMessage(), 3, 'error.log');
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    exit;
}
?>