<?php
require 'config.php';
require 'init_data_check.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$user_id = $data['user_id'] ?? 0;
$card_id = $data['card_id'] ?? '';
$init_data = $data['init_data'] ?? '';

// Validate user via Telegram WebApp
$verification = verifyTelegramWebApp($config['bot_token'], $init_data);
if (!$verification['ok']) {
    echo json_encode(['ok' => false, 'message' => 'Init data verification failed.']);
    exit;
}

$init_user_id = $verification['data']['user']['id'] ?? 0;
if ((int)$init_user_id !== (int)$user_id) {
    echo json_encode(['ok' => false, 'message' => 'User ID mismatch.']);
    exit;
}

// Fetch user and validate scratch card
$stmt = $pdo->prepare("SELECT balance, scratch_card FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['ok' => false, 'message' => 'User not found.']);
    exit;
}

$scratch_cards = json_decode($user['scratch_card'] ?? '[]', true);
$found_index = null;
$card_type = null;

foreach ($scratch_cards as $index => $card) {
    if ($card['card_id'] === $card_id) {
        $found_index = $index;
        $card_type = $card['card_type'];
        break;
    }
}

if ($found_index === null || !$card_type) {
    echo json_encode(['ok' => false, 'message' => 'Card not found or unauthorized.']);
    exit;
}

// Get reward amount from settings
$setting_stmt = $pdo->query("SELECT currency, silver_reward, gold_reward, diamond_reward FROM settings LIMIT 1");
$settings = $setting_stmt->fetch(PDO::FETCH_ASSOC);

$currency = $settings['currency'] ?? 'INR';

$rewards = [
    'silver' => (float)($settings['silver_reward'] ?? 5.00),
    'gold' => (float)($settings['gold_reward'] ?? 10.00),
    'diamond' => (float)($settings['diamond_reward'] ?? 15.00),
];

$reward_amount = round($rewards[$card_type] ?? 0.00, 2);

if ($reward_amount <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid reward configuration.']);
    exit;
}

// Update balance and scratch card
$current_balance = (float)$user['balance'];
$new_balance = round($current_balance + $reward_amount, 2);

unset($scratch_cards[$found_index]);
$scratch_cards = array_values($scratch_cards); // Reindex array

$update_stmt = $pdo->prepare("UPDATE users SET balance = ?, scratch_card = ? WHERE user_id = ?");
$success = $update_stmt->execute([
    $new_balance,
    json_encode($scratch_cards),
    $user_id
]);

if (!$success) {
    echo json_encode(['ok' => false, 'message' => 'Failed to update user balance.']);
    exit;
}

// Confirm updated balance
$stmt = $pdo->prepare("SELECT balance FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$updated_user = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'ok' => true,
    'amount' => $reward_amount,
    'balance' => (float)$updated_user['balance'],
    'message' => "🎉 Congratulations, you won {$reward_amount} {$currency}!"
]);
