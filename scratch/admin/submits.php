<?php
require 'config.php';

// Admin security — add your own authentication here if needed
// Example: if (!isAdmin()) { http_response_code(403); exit; }

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // List all submissions
    $stmt = $pdo->query("SELECT id, user_id, platform_name, video_link, additional_message, email, submitted_at FROM video_contest_submissions ORDER BY submitted_at DESC");
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'submissions' => $submissions
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';

    if ($action === 'reset') {
        // Delete all submissions
        $stmt = $pdo->query("TRUNCATE TABLE video_contest_submissions");
        echo json_encode([
            'ok' => true,
            'message' => 'All submissions deleted.'
        ]);
        exit;
    }

    echo json_encode([
        'ok' => false,
        'message' => 'Invalid action.'
    ]);
    exit;
}

echo json_encode([
    'ok' => false,
    'message' => 'Invalid request method.'
]);
exit;
