<?php
require 'config.php';
header('Content-Type: application/json');

// Always take admin_pin from URL (GET or POST)
$admin_pin = $_GET['admin_pin'] ?? $_POST['admin_pin'] ?? null;

// Block if admin_pin is missing or incorrect
if ($admin_pin !== $config['admin_pin']) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized access. Admin PIN required.']);
    exit;
}

// Handle GET: fetch all withdrawal requests with user names
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("
            SELECT 
                w.withdraw_id, w.user_id, w.name, w.amount, w.method, w.account, w.status, w.created_at,
                u.name AS user_name 
            FROM withdraw_history w
            LEFT JOIN users u ON w.user_id = u.user_id
            ORDER BY w.created_at DESC
        ");
        $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'withdrawals' => $withdrawals]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle POST actions
$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? '';
$payload = $data['payload'] ?? [];

if (!in_array($action, ['update_status', 'delete'])) {
    echo json_encode(['ok' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    if ($action === 'update_status') {
        $withdraw_id = $payload['withdraw_id'] ?? '';
        $new_status = $payload['status'] ?? '';

        if (!in_array($new_status, ['pending', 'approved', 'rejected'])) {
            echo json_encode(['ok' => false, 'message' => 'Invalid status']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE withdraw_history SET status = ? WHERE withdraw_id = ?");
        $stmt->execute([$new_status, $withdraw_id]);
        echo json_encode(['ok' => true, 'message' => 'Withdraw status updated']);
        exit;
    }

    if ($action === 'delete') {
        $withdraw_id = $payload['withdraw_id'] ?? '';
        $stmt = $pdo->prepare("DELETE FROM withdraw_history WHERE withdraw_id = ?");
        $stmt->execute([$withdraw_id]);
        echo json_encode(['ok' => true, 'message' => 'Withdraw record deleted']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    exit;
}
