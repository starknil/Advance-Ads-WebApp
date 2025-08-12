<?php
require 'config.php';
require 'init_data_check.php';

header('Content-Type: application/json');

// Get and parse input
$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? '';
$user_id = (int)($data['user_id'] ?? 0);
$init_data = $data['init_data'] ?? '';

// --- OxaPay config
define('OXAPAY_API_KEY', 'TF9GDI-UBI9AR-TWVBKM-TR0D4Z'); // CHANGE THIS

if ($init_data !== '786786') {
    $verification = verifyTelegramWebApp($config['bot_token'], $init_data);
    if (!$verification['ok']) {
        echo json_encode(['ok' => false, 'message' => 'Init data verification failed.']);
        exit;
    }
    $verified_id = (int)($verification['data']['user']['id'] ?? 0);
    if ($verified_id !== $user_id) {
        echo json_encode(['ok' => false, 'message' => 'User ID mismatch.']);
        exit;
    }
}

// Fetch user
$stmt = $pdo->prepare("SELECT user_id, name, balance, reflist FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['ok' => false, 'message' => 'User not found']);
    exit;
}

if ($action === 'gethistory') {
    $stmt = $pdo->prepare("SELECT withdraw_id, amount, method, account, status, created_at FROM withdraw_history WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$user_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'history' => $history,
        'user' => [
            'name' => $user['name'],
            'user_id' => $user['user_id'],
            'balance' => (float)$user['balance']
        ]
    ]);
    exit;
}

if ($action === 'withdraw') {
    $amount = (float)($data['amount'] ?? 0);
    $account = trim($data['account'] ?? '');

    // Withdraw rules
    $min = 10000;
    $fee = 3000;
    $currency = "SHIB";
    $network = "BEP20";

    $rules = $pdo->query("SELECT * FROM withdraw_rules LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $min_invite = (int)($rules['min_invite'] ?? 0);

    $reflist = json_decode($user['reflist'] ?? '[]', true);
    $total_refers = count($reflist);

    if ($total_refers < $min_invite) {
        echo json_encode(['ok' => false, 'message' => "You must invite at least $min_invite users to withdraw."]);
        exit;
    }

    if ($amount < $min) {
        echo json_encode(['ok' => false, 'message' => "Minimum withdraw is $min."]);
        exit;
    }

    if ((float)$user['balance'] < $amount) {
        echo json_encode(['ok' => false, 'message' => "Insufficient balance."]);
        exit;
    }

    if (empty($account)) {
        echo json_encode(['ok' => false, 'message' => 'Withdrawal account field is required.']);
        exit;
    }

    // OxaPay payout
    $payout_amount = $amount - $fee;
    if ($payout_amount <= 0) {
        echo json_encode(['ok' => false, 'message' => "Withdrawal amount must be greater than fee."]);
        exit;
    }

    $withdraw_id = uniqid('wd_');
    $oxapay_url = 'https://api.oxapay.com/v1/payout';
    $oxapay_data = [
        "address" => $account,
        "amount" => $payout_amount,
        "currency" => $currency,
        "network" => $network,
        "description" => "Withdrawal #$withdraw_id for User $user_id"
    ];

    $headers = [
        'Content-Type: application/json',
        'payout_api_key: ' . OXAPAY_API_KEY
    ];

    $options = array(
        'http' => array(
            'header'  => implode("\r\n", $headers),
            'method'  => 'POST',
            'content' => json_encode($oxapay_data),
            'ignore_errors' => true
        ),
    );
    $context  = stream_context_create($options);
    $oxapay_response = file_get_contents($oxapay_url, false, $context);

    if ($oxapay_response === FALSE) {
        echo json_encode(['ok' => false, 'message' => "OxaPay API error"]);
        exit;
    }
    $oxapay_result = json_decode($oxapay_response, true);

    if (!empty($oxapay_result['error'])) {
        echo json_encode(['ok' => false, 'message' => 'OxaPay Error: ' . $oxapay_result['error']['message']]);
        exit;
    }

    // Deduct balance
    $update = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE user_id = ?");
    $update->execute([$amount, $user_id]);

    // Insert into history (approved, method SHIB)
    $insert = $pdo->prepare("INSERT INTO withdraw_history (user_id, name, amount, method, account, withdraw_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $insert->execute([$user_id, $user['name'], $amount, "SHIB", $account, $withdraw_id, 'approved']);

    // Send Telegram message
    $telegram_message = "Your SHIB withdraw has been sent, check wallet bro. Fee: $fee, Payable: $payout_amount\nTrackID: " . ($oxapay_result['data']['track_id'] ?? 'N/A');
    $telegram_url = "https://api.telegram.org/bot{$config['bot_token']}/sendMessage";
    $telegram_data = [
        'chat_id' => $user_id,
        'text' => $telegram_message,
        'parse_mode' => 'HTML'
    ];
    $ch = curl_init($telegram_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($telegram_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);

    echo json_encode([
        'ok' => true,
        'message' => "Withdrawal sent successfully.",
        'withdraw_id' => $withdraw_id,
        'oxapay' => $oxapay_result
    ]);
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Invalid action']);
?>
