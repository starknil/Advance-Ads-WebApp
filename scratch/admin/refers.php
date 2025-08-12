<?php
require 'config.php';

header('Content-Type: application/json');

// Get admin_pin from URL parameter
$admin_pin = $_GET['admin_pin'] ?? '';
if ($admin_pin !== $config['admin_pin']) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized. Invalid admin pin.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Fetch top 10 referrers by count of referrals
    $topRefStmt = $pdo->query("
        SELECT user_id, name, JSON_LENGTH(reflist) as total_refer
        FROM users
        ORDER BY total_refer DESC
        LIMIT 10
    ");
    $topReferrers = $topRefStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch bonuses or provide default zeroes
    $bonus = $pdo->query("SELECT * FROM bonuses WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    if (!$bonus) {
        $bonus = [
            'first_prize' => 0,
            'second_prize' => 0,
            'third_prize' => 0,
            'fourth_prize' => 0,
            'fifth_prize' => 0,
            'sixth_prize' => 0,
            'seventh_prize' => 0,
            'eighth_prize' => 0,
            'ninth_prize' => 0,
            'tenth_prize' => 0,
        ];
    }

    // Map bonuses to array indexed by position
    $rewards = [
        $bonus['first_prize'],
        $bonus['second_prize'],
        $bonus['third_prize'],
        $bonus['fourth_prize'],
        $bonus['fifth_prize'],
        $bonus['sixth_prize'],
        $bonus['seventh_prize'],
        $bonus['eighth_prize'],
        $bonus['ninth_prize'],
        $bonus['tenth_prize'],
    ];

    echo json_encode([
        'ok' => true,
        'top_referrers' => $topReferrers,
        'rewards' => $rewards
    ]);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';

    if ($action === 'setreward') {
        // Update the rewards
        $fields = ['first_prize', 'second_prize', 'third_prize', 'fourth_prize', 'fifth_prize', 'sixth_prize', 'seventh_prize', 'eighth_prize', 'ninth_prize', 'tenth_prize'];
        $values = [];
        foreach ($fields as $field) {
            $values[$field] = isset($data[$field]) ? (float)$data[$field] : 0;
        }

        // Check if bonuses row exists
        $exists = $pdo->query("SELECT COUNT(*) FROM bonuses WHERE id = 1")->fetchColumn();
        if ($exists) {
            // Update existing row
            $stmt = $pdo->prepare("UPDATE bonuses SET 
                first_prize = :first_prize, second_prize = :second_prize, third_prize = :third_prize, 
                fourth_prize = :fourth_prize, fifth_prize = :fifth_prize, sixth_prize = :sixth_prize, 
                seventh_prize = :seventh_prize, eighth_prize = :eighth_prize, ninth_prize = :ninth_prize, 
                tenth_prize = :tenth_prize WHERE id = 1");
        } else {
            // Insert new row
            $stmt = $pdo->prepare("INSERT INTO bonuses 
                (id, first_prize, second_prize, third_prize, fourth_prize, fifth_prize, sixth_prize, seventh_prize, eighth_prize, ninth_prize, tenth_prize) 
                VALUES 
                (1, :first_prize, :second_prize, :third_prize, :fourth_prize, :fifth_prize, :sixth_prize, :seventh_prize, :eighth_prize, :ninth_prize, :tenth_prize)");
        }

        $stmt->execute($values);
        echo json_encode(['ok' => true, 'message' => 'Rewards updated successfully.']);
        exit;
    }

    if ($action === 'distribute') {
        // Fetch top 10 referrers
        $topRefStmt = $pdo->query("
            SELECT user_id, JSON_LENGTH(reflist) as total_refer
            FROM users
            ORDER BY total_refer DESC
            LIMIT 10
        ");
        $topReferrers = $topRefStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch bonuses
        $bonus = $pdo->query("SELECT * FROM bonuses WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        if (!$bonus) {
            echo json_encode(['ok' => false, 'message' => 'No reward amounts set.']);
            exit;
        }

        $fields = ['first_prize', 'second_prize', 'third_prize', 'fourth_prize', 'fifth_prize', 'sixth_prize', 'seventh_prize', 'eighth_prize', 'ninth_prize', 'tenth_prize'];

        // Distribute rewards and reset reflist for all users
        foreach ($topReferrers as $index => $user) {
            $rewardKey = $fields[$index] ?? null;
            if (!$rewardKey) continue;

            $rewardAmount = (float) $bonus[$rewardKey];
            if ($rewardAmount <= 0) continue;

            // Add reward to balance
            $updateBalance = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE user_id = ?");
            $updateBalance->execute([$rewardAmount, $user['user_id']]);

            // Reset reflist
            $resetReflist = $pdo->prepare("UPDATE users SET reflist = '[]' WHERE user_id = ?");
            $resetReflist->execute([$user['user_id']]);
        }

        echo json_encode(['ok' => true, 'message' => 'Rewards distributed and referral lists reset.']);
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Invalid action.']);
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Unsupported method.']);
