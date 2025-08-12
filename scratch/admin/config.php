<?php
$config = [
    'admin_pin' => 'demo1234',
    'db' => [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => 'root',
        'name' => 'ads_webapp'
    ],
    'bot_token' => '8106838212:AAH3TXNeZBSMIJOLYmcjHmSyl-V3My0wCGM',
    'web_app_url' => 'https://0a48dec5e37a.ngrok-free.app/scratch', // Update this
];

$pdo = new PDO(
    "mysql:host={$config['db']['host']};dbname={$config['db']['name']}",
    $config['db']['user'],
    $config['db']['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
?>