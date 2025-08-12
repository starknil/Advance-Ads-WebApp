<?php
require 'config.php';
require 'init_data_check.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$user_id = (int)($data['user_id'] ?? 0);
$task_id = $data['task_id'] ?? '';
$init_data = $data['init_data'] ?? '';

if (!$user_id || !$task_id || !$init_data) {
    echo json_encode(['ok' => false, 'message' => 'Missing required fields.']);
    exit;
}

$verification = verifyTelegramWebApp($config['bot_token'], $init_data);
if (!$verification['ok']) {
    echo json_encode(['ok' => false, 'message' => 'Init data verification failed.']);
    exit;
}

$init_data_array = $verification['data'];
if ((int)($init_data_array['user']['id'] ?? 0) !== $user_id) {
    echo json_encode(['ok' => false, 'message' => 'User ID mismatch.']);
    exit;
}

// Fetch user and task
$stmt = $pdo->prepare("SELECT completed_task_ids, scratch_card FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['ok' => false, 'message' => 'User not found.']);
    exit;
}

$task_stmt = $pdo->prepare("SELECT *, (completed >= `limit` AND `limit` > 0) AS is_limit_reached FROM tasks WHERE task_id = ?");
$task_stmt->execute([$task_id]);
$task = $task_stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    echo json_encode(['ok' => false, 'message' => 'Task not found.']);
    exit;
}

// Check if task limit is reached
if ($task['is_limit_reached']) {
    $delete_task = $pdo->prepare("DELETE FROM tasks WHERE task_id = ?");
    $delete_task->execute([$task_id]);
    echo json_encode(['ok' => false, 'message' => 'Task no longer available due to completion limit.']);
    exit;
}

// Check if task already completed
$completed_ids = json_decode($user['completed_task_ids'] ?? '[]', true);
if (in_array($task_id, $completed_ids)) {
    echo json_encode(['ok' => false, 'message' => 'Task already claimed.']);
    exit;
}

// Telegram chat membership check (but allow claim even if fails)
if ($task['type'] === 'telegram_chat') {
    $url = $task['url'];
    if (preg_match('/t(?:elegram)?\.me\/(joinchat\/)?([a-zA-Z0-9_]+)/', $url, $matches)) {
        $channel = $matches[2];
        $check_url = "https://api.telegram.org/bot{$config['bot_token']}/getChatMember?chat_id=@$channel&user_id=$user_id";
        $resp = @file_get_contents($check_url);
        $result = json_decode($resp, true);

        // If ok = false or user not a member, just log but do not block
        if (!$result || !$result['ok'] || !in_array($result['result']['status'] ?? '', ['member', 'administrator', 'creator'])) {
            // Bot has no access or user not in chat -> allow to claim anyway
        }
    }
    // If invalid URL (regex fails), still allow
}

// Give scratch card reward
$new_card = ["card_id" => uniqid(), "card_type" => $task['reward']];
$cards = json_decode($user['scratch_card'] ?? '[]', true);
$cards[] = $new_card;

$completed_ids[] = $task_id;

// Update user data
$update = $pdo->prepare("UPDATE users SET completed_task_ids = ?, scratch_card = ? WHERE user_id = ?");
$update->execute([
    json_encode($completed_ids),
    json_encode($cards),
    $user_id
]);

// Update task completed count
$update_task = $pdo->prepare("UPDATE tasks SET completed = completed + 1 WHERE task_id = ?");
$update_task->execute([$task_id]);

// Check if task limit reached and delete if necessary
$task_stmt = $pdo->prepare("SELECT completed, `limit` FROM tasks WHERE task_id = ?");
$task_stmt->execute([$task_id]);
$updated_task = $task_stmt->fetch(PDO::FETCH_ASSOC);
if ($updated_task && $updated_task['limit'] > 0 && $updated_task['completed'] >= $updated_task['limit']) {
    $delete_task = $pdo->prepare("DELETE FROM tasks WHERE task_id = ?");
    $delete_task->execute([$task_id]);
}

// Send Telegram message
$telegram_message = "🎉 Congratulations! You have completed a task and won a <b>{$task['reward']}</b> scratch card! 🃏\nTap below to scratch it now!";
$telegram_url = "https://api.telegram.org/bot{$config['bot_token']}/sendMessage";
$telegram_data = [
    'chat_id' => $user_id,
    'text' => $telegram_message,
    'parse_mode' => 'HTML',
    'reply_markup' => json_encode([
        'inline_keyboard' => [[[ 'text' => 'Open WebApp', 'web_app' => ['url' => $config['web_app_url']] ]]]
    ])
];

$ch = curl_init($telegram_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($telegram_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);
curl_close($ch);

// Get currency
$currency_stmt = $pdo->query("SELECT currency FROM settings LIMIT 1");
$currency_row = $currency_stmt->fetch(PDO::FETCH_ASSOC);
$currency = $currency_row['currency'] ?? 'INR';

// Respond success
$response = [
    'ok' => true,
    'message' => "Congratulations! You won 1 {$task['reward']} scratch card.",
    'card_type' => $task['reward'],
    'currency' => $currency
];
echo json_encode($response);
?>
