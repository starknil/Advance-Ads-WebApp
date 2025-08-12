<?php
$botToken = "8170457076:AAFBVQA2KhOV-tVymLKF7Gj8eY7qNzwP_hQ";
$apiURL = "https://api.telegram.org/bot$botToken/";

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit;

$chatId = $update['message']['chat']['id'] ?? $update['callback_query']['from']['id'] ?? null;
$userId = $update['message']['from']['id'] ?? null;

if ($chatId && $userId) {
    // Photo and caption
    $photo = "https://i.ibb.co/FbD4y5zy/image.png";
    $caption = "🎉 Welcome to SHIBAdEarner! 🐶💸\n\n".
        "Earn SHIB INU per ad watched — instantly!\n".
        "Get 5% commission per invited friend! 🧑‍🤝‍🧑\n".
        "100% free, no deposits, just earn. 🚀\n\n".
        "Turn screen time into SHIB! 🔥";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => 'Open WebApp', 'web_app' => ['url' => 'https://0a48dec5e37a.ngrok-free.app/scratch/index.html']]
            ],
            [
                ['text' => 'Join Channel', 'url' => 'https://t.me/ShibProfitNews']
            ]
        ]
    ];

    // Send photo
    sendRequest($apiURL . "sendPhoto", [
        'chat_id' => $chatId,
        'photo' => $photo,
        'caption' => $caption,
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode($keyboard)
    ]);

    // API Request
    $url = 'https://broadcast.logicalhuman.link/add-new.php';
    $payload = [
        'group_id' => 'ship123',
        'bot_name' => 'diamond_card',
        'bot_token' => $botToken,
        'user_id' => $userId
    ];

    sendCurl($url, $payload);
}

function sendRequest($url, $data) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $data
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function sendCurl($url, $data) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);
    curl_exec($ch);
    curl_close($ch);
}
