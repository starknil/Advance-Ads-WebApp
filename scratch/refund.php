<?php
header('Content-Type: application/json');

// Database connection configuration
$host = 'localhost:3306';
$dbname = 'ads_webapp';
$username = 'root'; // Replace with your database username
$password = 'root'; // Replace with your database password

try {
    // Establish PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Begin transaction
    $pdo->beginTransaction();

    // Fetch all pending withdrawals
    $stmt = $pdo->prepare("SELECT id, user_id, amount FROM withdraw_history WHERE status = 'pending'");
    $stmt->execute();
    $pendingWithdrawals = $stmt->fetchAll();

    if (empty($pendingWithdrawals)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'No pending withdrawals found.'
        ]);
        exit;
    }

    $refundCount = 0;
    $errors = [];

    foreach ($pendingWithdrawals as $withdrawal) {
        $withdrawId = $withdrawal['id'];
        $userId = $withdrawal['user_id'];
        $amount = $withdrawal['amount'];

        // Fetch user to ensure they exist and get current balance
        $userStmt = $pdo->prepare("SELECT balance FROM users WHERE user_id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();

        if (!$user) {
            $errors[] = "User with user_id $userId not found for withdrawal ID $withdrawId";
            continue;
        }

        // Calculate new balance
        $newBalance = $user['balance'] + $amount;

        // Update user balance
        $updateStmt = $pdo->prepare("UPDATE users SET balance = ? WHERE user_id = ?");
        $updateStmt->execute([$newBalance, $userId]);

        // Update withdrawal status to 'rejected'
        $withdrawStmt = $pdo->prepare("UPDATE withdraw_history SET status = 'rejected' WHERE id = ?");
        $withdrawStmt->execute([$withdrawId]);

        $refundCount++;
    }

    // Commit transaction
    $pdo->commit();

    // Prepare response
    $response = [
        'status' => 'success',
        'message' => "Successfully refunded $refundCount pending withdrawals.",
        'errors' => $errors
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    // Roll back transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log error (you can customize logging mechanism)
    error_log("Database error: " . $e->getMessage());

    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while processing refunds.',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    // Roll back transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log error
    error_log("General error: " . $e->getMessage());

    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'An unexpected error occurred.',
        'error' => $e->getMessage()
    ]);
}
?>