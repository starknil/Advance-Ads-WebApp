<?php
require 'config.php';
require 'init_data_check.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$user_id     = $data['user_id'] ?? 0;
$email       = $data['email'] ?? '';
$video_link  = $data['video_link'] ?? '';
$message     = $data['message'] ?? '';
$platform    = $data['platform'] ?? '';
$init_data   = $data['init_data'] ?? '';

// Step 1: Telegram WebApp Verification
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

// Step 2: Date Restriction (Only 1-20 allowed har mahine)
$today_day = (int)date('d');
if ($today_day < 1 || $today_day > 20) {
    echo json_encode([
        'ok' => false,
        'message' => 'Submission window is closed. Allowed only from 1st to 20th of every month!'
    ]);
    exit;
}

// Step 3: Check Duplicate Submission (One user, one chance bro)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM video_contest_submissions WHERE user_id = ?");
$stmt->execute([$user_id]);
$already_submitted = $stmt->fetchColumn();

if ($already_submitted > 0) {
    echo json_encode([
        'ok' => false,
        'message' => 'Hey! You have already submitted. Only one entry is allowed.'
    ]);
    exit;
}

// Step 4: Insert Submission
$stmt = $pdo->prepare("INSERT INTO video_contest_submissions 
    (user_id, platform_name, video_link, additional_message, email) 
    VALUES (?, ?, ?, ?, ?)");
$success = $stmt->execute([
    $user_id,
    $platform,
    $video_link,
    $message,
    $email
]);

if (!$success) {
    echo json_encode([
        'ok' => false,
        'message' => 'Submission failed, try again or tag an admin.'
    ]);
    exit;
}

// Step 5: Response
echo json_encode([
    'ok' => true,
    'message' => "Entry submitted! All the best for contest 🚀"
]);
