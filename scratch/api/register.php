<?php
require 'config.php';
require 'init_data_check.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$user_id = (int)($data['user_id'] ?? 0);
$name = trim($data['name'] ?? '');
$ip = trim($data['ip'] ?? '');
$init_data = $data['init_data'] ?? '';
$ref = (int)($data['ref'] ?? 0);

// Validate init_data
$verification = verifyTelegramWebApp($config['bot_token'], $init_data);
if (!$verification['ok']) {
    echo json_encode(['ok' => false, 'message' => 'Verification failed.']);
    exit;
}
$init_data_array = $verification['data'];
if ((int)($init_data_array['user']['id'] ?? 0) !== $user_id) {
    echo json_encode(['ok' => false, 'message' => 'User ID mismatch.']);
    exit;
}

// Check if user already registered
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
if ($stmt->fetchColumn() > 0) {
    echo json_encode(['ok' => true, 'message' => 'User already registered.']);
    exit;
}

// Check channel membership
if (!isUserInChannel($config['bot_token'], '@ShibProfitNews', $user_id)) {
    echo json_encode(['ok' => false, 'message' => 'Please join our Telegram channel @ShibProfitNews and @CryptoAirdropRush to register.']);
    exit;
}

// Check duplicate IP for other users
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE ip_address = ? AND user_id != ?");
$stmt->execute([$ip, $user_id]);
if ($stmt->fetchColumn() > 0) {
    echo json_encode(['ok' => false, 'message' => 'Duplicate account detected. IP address already used by another user.']);
    exit;
}

// Create user
$scratch_card = json_encode([[
    "card_id" => uniqid(),
    "card_type" => "silver"
]]);
$insert = $pdo->prepare("INSERT INTO users (name, user_id, ip_address, scratch_card) VALUES (?, ?, ?, ?)");
$insert->execute([$name, $user_id, $ip, $scratch_card]);

// Send welcome message to user
$welcome_text = "🎉 Welcome, <b>$name</b>!\n\nYou've successfully joined our Refer & Earn program. Start earning by inviting your friends and completing simple tasks.\n\nYou've received 1 free <b>silver scratch card</b>! 🏱";
sendTelegramMessage($user_id, $welcome_text, $config['web_app_url']);

// Handle referral
if ($ref > 0) {
    $types = ['silver', 'gold', 'diamond'];
    $card_type = $types[array_rand($types)];
    $new_card = ["card_id" => uniqid(), "card_type" => $card_type];

    $q = $pdo->prepare("SELECT scratch_card, reflist, name FROM users WHERE user_id = ?");
    $q->execute([$ref]);
    $row = $q->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $cards = json_decode($row['scratch_card'] ?? '[]', true);
        $cards[] = $new_card;

        $list = json_decode($row['reflist'] ?? '[]', true);
        $list[] = [
            "name" => $name,
            "user_id" => $user_id,
            "date" => (new DateTime("now", new DateTimeZone("Asia/Kolkata")))->format("Y-m-d H:i:s")
        ];

        // Update scratch cards and reflist
        $update = $pdo->prepare("UPDATE users SET scratch_card = ?, reflist = ? WHERE user_id = ?");
        $update->execute([json_encode($cards), json_encode($list), $ref]);

        // 💵 Add 500 SHIB to balance
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE user_id = ?")
            ->execute([500, $ref]);

        // Send Telegram notification
        $ref_msg = "🎯 You have successfully invited <a href='tg://user?id=$user_id'>$name</a>!\n\n+1 <b>$card_type</b> scratch card & <b>500 SHIB</b>!\nTap below to claim rewards!";
        sendTelegramMessage($ref, $ref_msg, $config['web_app_url'], true);
    }
}
echo json_encode(['ok' => true, 'message' => 'User registered successfully.']);

// ========== Telegram Send Helper ==========
function sendTelegramMessage($chat_id, $text, $url, $is_html = true) {
    global $config;

    $payload = [
        'chat_id' => $chat_id,
        'text' => $text,
        'reply_markup' => json_encode([
            'inline_keyboard' => [[[
                'text' => 'Open WebApp',
                'web_app' => ['url' => $url]
            ]]]
        ])
    ];
    if ($is_html) $payload['parse_mode'] = 'HTML';

    $ch = curl_init("https://api.telegram.org/bot{$config['bot_token']}/sendMessage");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

// ========== Membership Check ==========
function isUserInChannel($bot_token, $channel_username, $user_id) {
    $url = "https://api.telegram.org/bot$bot_token/getChatMember?chat_id=$channel_username&user_id=$user_id";
    $result = file_get_contents($url);
    $data = json_decode($result, true);

    if (!$data || !isset($data['ok']) || !$data['ok']) {
        return false;
    }

    $status = $data['result']['status'] ?? '';
    return in_array($status, ['member', 'administrator', 'creator']);
}
?>