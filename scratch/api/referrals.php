<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set content type to JSON
header('Content-Type: application/json');

// Include configuration and initialization files
try {
    require 'config.php'; // Assumes $pdo and $config['bot_token'] are defined
    require 'init_data_check.php'; // Assumes verifyTelegramWebApp function exists
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'message' => 'Failed to load required files: ' . $e->getMessage()]);
    exit;
}

// Read and validate input data
$raw_input = file_get_contents("php://input");
if (empty($raw_input)) {
    echo json_encode(['ok' => false, 'message' => 'No input data received.']);
    exit;
}

$data = json_decode($raw_input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

// Extract and validate user_id and init_data
$user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;
$init_data = $data['init_data'] ?? '';
if ($user_id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid or missing user_id.']);
    exit;
}
if (empty($init_data)) {
    echo json_encode(['ok' => false, 'message' => 'Missing init_data.']);
    exit;
}

// Bypass or Verify init_data
if ($init_data !== '786786') {
    try {
        $verification = verifyTelegramWebApp($config['bot_token'], $init_data);
        if (!isset($verification['ok']) || !$verification['ok']) {
            echo json_encode(['ok' => false, 'message' => 'Init data verification failed.']);
            exit;
        }
        $init_data_array = $verification['data'];
        if ((int)($init_data_array['user']['id'] ?? 0) !== $user_id) {
            echo json_encode(['ok' => false, 'message' => 'User ID mismatch.']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'message' => 'Verification error: ' . $e->getMessage()]);
        exit;
    }
}

// Fetch user
try {
    $stmt = $pdo->prepare("SELECT user_id, name, balance, reflist FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        echo json_encode(['ok' => false, 'message' => 'User not found.']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'message' => 'Database error (user fetch): ' . $e->getMessage()]);
    exit;
}

// Decode referral list
$referrals = json_decode($user['reflist'] ?? '[]', true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['ok' => false, 'message' => 'Invalid referral list JSON: ' . json_last_error_msg()]);
    exit;
}
$total_refer = count($referrals);

// Fetch top 10 referrers
try {
    $top_stmt = $pdo->query("
        SELECT user_id, name, JSON_LENGTH(reflist) AS total_refer
        FROM users
        WHERE reflist IS NOT NULL
        ORDER BY total_refer DESC
        LIMIT 10
    ");
    $top_referrers = $top_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'message' => 'Database error (top referrers): ' . $e->getMessage()]);
    exit;
}

// Fetch bonus structure
try {
    $bonus_stmt = $pdo->query("SELECT * FROM bonuses LIMIT 1");
    $bonus = $bonus_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bonus) {
        $bonus = []; // Fallback to empty array if no bonuses found
    }
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'message' => 'Database error (bonuses): ' . $e->getMessage()]);
    exit;
}

// Attach bonuses to top referrers
foreach ($top_referrers as $index => &$ref) {
    $rank = $index + 1;
    $key = match($rank) {
        1 => 'first_prize',
        2 => 'second_prize',
        3 => 'third_prize',
        4 => 'fourth_prize',
        5 => 'fifth_prize',
        6 => 'sixth_prize',
        7 => 'seventh_prize',
        8 => 'eighth_prize',
        9 => 'ninth_prize',
        10 => 'tenth_prize',
        default => null
    };
    $ref['bonus'] = $bonus[$key] ?? 0;
}

// Fetch bot username
try {
    $setting_stmt = $pdo->query("SELECT bot_username FROM settings LIMIT 1");
    $setting = $setting_stmt->fetch(PDO::FETCH_ASSOC);
    $bot_username = $setting['bot_username'] ?? '@diamond_card_bot';
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'message' => 'Database error (settings): ' . $e->getMessage()]);
    exit;
}

// Output response
echo json_encode([
    'ok' => true,
    'user_details' => [
        'user_id' => $user['user_id'],
        'name' => $user['name'],
        'balance' => (int)$user['balance'],
        'total_refer' => $total_refer,
        'bot_username' => $bot_username
    ],
    'referrals' => $referrals,
    'top_referrers' => $top_referrers
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>