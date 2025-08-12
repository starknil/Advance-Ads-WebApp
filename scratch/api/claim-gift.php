<?php
require_once 'config.php';

header('Content-Type: application/json');

// Read JSON input from php://input
$input = json_decode(file_get_contents('php://input'), true);

$user_id = $input['user_id'] ?? '';
$gift_code = $input['gift_code'] ?? '';

$response = ['ok' => false, 'message' => '', 'result' => []];

try {
    // Validate input
    if (empty($user_id) || empty($gift_code)) {
        $response['message'] = 'User ID and gift code are required';
        echo json_encode($response);
        exit;
    }

    // Check if user exists
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $response['message'] = 'Invalid user ID';
        echo json_encode($response);
        exit;
    }

    // Check if gift code exists
    $stmt = $pdo->prepare("SELECT * FROM gift_codes WHERE gift_code = ?");
    $stmt->execute([$gift_code]);
    $gift = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$gift) {
        $response['message'] = 'Gift code not found';
        echo json_encode($response);
        exit;
    }

    // Parse userids JSON (array of claimed user IDs)
    $claimed_userids = $gift['userids'] ? json_decode($gift['userids'], true) : [];

    // Check if user has already claimed
    if (in_array($user_id, $claimed_userids)) {
        $response['message'] = 'User has already claimed this gift code';
        echo json_encode($response);
        exit;
    }

    // Check if max users limit is reached
    if ($gift['claimed'] >= $gift['users']) {
        $response['message'] = 'Gift code has reached maximum claims';
        echo json_encode($response);
        exit;
    }

    // Start transaction to ensure atomicity
    $pdo->beginTransaction();

    // Update user balance
    $new_balance = $user['balance'] + $gift['prize'];
    $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE user_id = ?");
    $stmt->execute([$new_balance, $user_id]);

    // Update gift code: increment claimed count and add user_id to userids
    $claimed_userids[] = $user_id;
    $new_claimed_count = $gift['claimed'] + 1;

    $stmt = $pdo->prepare("UPDATE gift_codes SET claimed = ?, userids = ? WHERE gift_code = ?");
    $stmt->execute([$new_claimed_count, json_encode($claimed_userids), $gift_code]);

    // Delete gift code if claimed count equals max users
    if ($new_claimed_count >= $gift['users']) {
        $stmt = $pdo->prepare("DELETE FROM gift_codes WHERE gift_code = ?");
        $stmt->execute([$gift_code]);
        $response['message'] = 'Gift code claimed successfully and deleted due to reaching max users';
    } else {
        $response['message'] = 'Gift code claimed successfully';
    }

    // Commit transaction
    $pdo->commit();

    $response['ok'] = true;
    $response['result'] = [
        'gift_code' => $gift_code,
        'prize' => $gift['prize'],
        'user_id' => $user_id,
        'new_balance' => $new_balance
    ];

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>