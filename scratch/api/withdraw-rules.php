<?php
require 'config.php';

header('Content-Type: application/json');

// Fetch withdraw methods
$method_stmt = $pdo->query("SELECT method_name, field_name FROM withdraw_methods");
$methods = $method_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch withdraw rules
$rules_stmt = $pdo->query("SELECT min_withdraw, max_withdraw, min_invite FROM withdraw_rules LIMIT 1");
$rules = $rules_stmt->fetch(PDO::FETCH_ASSOC);

// Fallback if no rules are defined
if (!$rules) {
    $rules = [
        'min_withdraw' => 10,
        'max_withdraw' => 10000,
        'min_invite' => 0
    ];
}

// Response
echo json_encode([
    'ok' => true,
    'rules' => [
        'min_withdraw' => (float)$rules['min_withdraw'],
        'max_withdraw' => (float)$rules['max_withdraw'],
        'min_invite' => (int)$rules['min_invite']
    ],
    'methods' => $methods
]);
