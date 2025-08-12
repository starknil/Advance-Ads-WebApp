<?php
require 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id  = $_GET['user_id'] ?? 0;
    $password = $_GET['password'] ?? '';

    if ($password !== $config['admin_pin']) {
        echo json_encode([
            'ok' => false,
            'message' => 'Admin password incorrect.'
        ]);
        exit;
    }

    // Select only needed fields
    $stmt = $pdo->prepare("SELECT user_id, name, balance, ip_address FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode([
            'ok' => false,
            'message' => 'User not found.'
        ]);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'user' => $user
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $user_id  = $data['user_id'] ?? 0;
    $amount   = isset($data['amount']) ? (float)$data['amount'] : null;
    $password = $data['password'] ?? '';

    if ($password !== $config['admin_pin']) {
        echo json_encode([
            'ok' => false,
            'message' => 'Admin password incorrect.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT user_id, name, balance, ip_address FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode([
            'ok' => false,
            'message' => 'User not found.'
        ]);
        exit;
    }

    if ($amount === null) {
        echo json_encode([
            'ok' => true,
            'user' => $user
        ]);
        exit;
    }

    // Update balance
    $new_balance = round(((float)$user['balance']) + $amount, 2);
    $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE user_id = ?");
    $success = $stmt->execute([$new_balance, $user_id]);

    if (!$success) {
        echo json_encode([
            'ok' => false,
            'message' => 'Failed to update balance.'
        ]);
        exit;
    }

    $user['balance'] = $new_balance;
    echo json_encode([
        'ok' => true,
        'message' => 'Balance updated.',
        'user' => $user
    ]);
    exit;
}

echo json_encode([
    'ok' => false,
    'message' => 'Invalid request method.'
]);
exit;
