<?php
$config = [
    'admin_pin' => '786786',
    'oxapay_api_key' => 'EU1FPZ-DN2NAB-DTENIJ-BK2TBR',
    'telegram_chat_id' => '7378059553',
    'db' => [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => 'root',
        'name' => 'ads_webapp'
    ],
    'bot_token' => '8170457076:AAFBVQA2KhOV-tVymLKF7Gj8eY7qNzwP_hQ',
    'web_app_url' => 'https://0a48dec5e37a.ngrok-free.app/scratch', // Update this
];

$pdo = new PDO(
    "mysql:host={$config['db']['host']};dbname={$config['db']['name']}",
    $config['db']['user'],
    $config['db']['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
?>
