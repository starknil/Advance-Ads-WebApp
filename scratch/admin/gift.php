<?php
require_once 'config.php';

header('Content-Type: application/json');

// Read JSON input from php://input
$input = json_decode(file_get_contents('php://input'), true);

$action = $input['action'] ?? '';
$admin_password = $input['admin_password'] ?? '';

function validateAdminPassword($password) {
    global $config;
    return $password === $config['admin_pin'];
}

$response = ['ok' => false, 'message' => '', 'result' => []];

try {
    switch ($action) {
        case 'get':
            if (!validateAdminPassword($admin_password)) {
                $response['message'] = 'Invalid admin password';
                echo json_encode($response);
                exit;
            }

            $stmt = $pdo->query("SELECT * FROM gift_codes");
            $response['result'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['ok'] = true;
            $response['message'] = 'Gift codes retrieved successfully';
            break;

        case 'create':
            if (!validateAdminPassword($admin_password)) {
                $response['message'] = 'Invalid admin password';
                echo json_encode($response);
                exit;
            }

            $gift_code = $input['gift_code'] ?? '';
            $prize = $input['prize'] ?? 0;
            $users = $input['users'] ?? 0;

            if (empty($gift_code) || $prize <= 0 || $users <= 0) {
                $response['message'] = 'Invalid input parameters';
                echo json_encode($response);
                exit;
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO gift_codes (gift_code, prize, users) VALUES (?, ?, ?)");
                $stmt->execute([$gift_code, $prize, $users]);
                $response['ok'] = true;
                $response['message'] = 'Gift code created successfully';
                $response['result'] = ['gift_code' => $gift_code, 'prize' => $prize, 'users' => $users];
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Duplicate key violation
                    $response['message'] = 'Gift code already exists';
                } else {
                    $response['message'] = 'Database error: ' . $e->getMessage();
                }
                echo json_encode($response);
                exit;
            }
            break;

        case 'delete':
            if (!validateAdminPassword($admin_password)) {
                $response['message'] = 'Invalid admin password';
                echo json_encode($response);
                exit;
            }

            $gift_code = $input['gift_code'] ?? '';

            if (empty($gift_code)) {
                $response['message'] = 'Gift code is required';
                echo json_encode($response);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM gift_codes WHERE gift_code = ?");
            $stmt->execute([$gift_code]);
            $response['ok'] = $stmt->rowCount() > 0;
            $response['message'] = $response['ok'] ? 'Gift code deleted successfully' : 'Gift code not found';
            break;

        case 'edit':
            if (!validateAdminPassword($admin_password)) {
                $response['message'] = 'Invalid admin password';
                echo json_encode($response);
                exit;
            }

            $gift_code = $input['gift_code'] ?? '';
            $prize = isset($input['prize']) ? $input['prize'] : null;
            $users = isset($input['users']) ? $input['users'] : null;

            if (empty($gift_code) || ($prize === null && $users === null)) {
                $response['message'] = 'Gift code and at least one field to update are required';
                echo json_encode($response);
                exit;
            }

            $updates = [];
            $params = [];
            if ($prize !== null && $prize >= 0) {
                $updates[] = 'prize = ?';
                $params[] = $prize;
            }
            if ($users !== null && $users >= 0) {
                $updates[] = 'users = ?';
                $params[] = $users;
            }
            $params[] = $gift_code;

            if (!empty($updates)) {
                $stmt = $pdo->prepare("UPDATE gift_codes SET " . implode(', ', $updates) . " WHERE gift_code = ?");
                $stmt->execute($params);
                $response['ok'] = $stmt->rowCount() > 0;
                $response['message'] = $response['ok'] ? 'Gift code updated successfully' : 'Gift code not found';
            } else {
                $response['message'] = 'No valid fields to update';
            }
            break;

        default:
            $response['message'] = 'Invalid action';
            break;
    }
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>