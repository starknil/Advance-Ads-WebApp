<?php
header('Content-Type: application/json');
require_once 'config.php';

// Function to send JSON response
function sendResponse($status, $message, $data = null) {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Verify admin pin
function verifyAdminPin($pin) {
    global $config;
    return $pin === $config['admin_pin'];
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'GET':
            // Fetch all settings (no admin pin required)
            $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If no settings found, return default values
            if (!$settings) {
                $settings = [
                    'id' => 1,
                    'currency' => 'INR',
                    'silver_reward' => 5,
                    'gold_reward' => 10,
                    'diamond_reward' => 15,
                    'bot_username' => '@diamond_card_bot'
                ];
                // Optionally, insert default settings into the database
                $pdo->prepare("INSERT INTO settings (id, currency, silver_reward, gold_reward, diamond_reward, bot_username) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([
                        $settings['id'],
                        $settings['currency'],
                        $settings['silver_reward'],
                        $settings['gold_reward'],
                        $settings['diamond_reward'],
                        $settings['bot_username']
                    ]);
            }
            
            $stmt = $pdo->query("SELECT * FROM withdraw_methods");
            $withdraw_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->query("SELECT * FROM withdraw_rules LIMIT 1");
            $withdraw_rules = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If no withdraw rules found, return default values
            if (!$withdraw_rules) {
                $withdraw_rules = [
                    'id' => 1,
                    'min_withdraw' => '10.00',
                    'max_withdraw' => '10000.00',
                    'min_invite' => 0
                ];
                // Optionally, insert default withdraw rules
                $pdo->prepare("INSERT INTO withdraw_rules (id, min_withdraw, max_withdraw, min_invite) VALUES (?, ?, ?, ?)")
                    ->execute([
                        $withdraw_rules['id'],
                        $withdraw_rules['min_withdraw'],
                        $withdraw_rules['max_withdraw'],
                        $withdraw_rules['min_invite']
                    ]);
            }
            
            sendResponse('success', 'Settings fetched successfully', [
                'settings' => $settings,
                'withdraw_methods' => $withdraw_methods,
                'withdraw_rules' => $withdraw_rules
            ]);
            break;

        case 'POST':
            // Check if admin pin is provided for POST requests
            if (!isset($input['admin_pin']) || !verifyAdminPin($input['admin_pin'])) {
                sendResponse('error', 'Invalid admin pin');
            }

            // Handle different actions
            if (!isset($input['action'])) {
                sendResponse('error', 'Action not specified');
            }

            switch ($input['action']) {
                case 'update_settings':
                    // Update settings (currency, rewards, bot_username)
                    if (!isset($input['settings']) || !is_array($input['settings'])) {
                        sendResponse('error', 'Invalid settings data');
                    }

                    $settings = $input['settings'];
                    $allowed_fields = ['currency', 'silver_reward', 'gold_reward', 'diamond_reward', 'bot_username'];
                    $update_data = array_intersect_key($settings, array_flip($allowed_fields));
                    
                    if (empty($update_data)) {
                        sendResponse('error', 'No valid settings to update');
                    }

                    // Prepare update query
                    $set_clause = [];
                    $params = [];
                    foreach ($update_data as $key => $value) {
                        $set_clause[] = "$key = ?";
                        $params[] = $value;
                    }
                    
                    $query = "UPDATE settings SET " . implode(', ', $set_clause) . " WHERE id = 1";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    
                    // If no rows affected (settings table empty), insert new row
                    if ($stmt->rowCount() === 0) {
                        $default_settings = [
                            'id' => 1,
                            'currency' => 'INR',
                            'silver_reward' => 5,
                            'gold_reward' => 10,
                            'diamond_reward' => 15,
                            'bot_username' => '@diamond_card_bot'
                        ];
                        $update_data = array_merge($default_settings, $update_data);
                        $pdo->prepare("INSERT INTO settings (id, currency, silver_reward, gold_reward, diamond_reward, bot_username) VALUES (?, ?, ?, ?, ?, ?)")
                            ->execute([
                                $update_data['id'],
                                $update_data['currency'],
                                $update_data['silver_reward'],
                                $update_data['gold_reward'],
                                $update_data['diamond_reward'],
                                $update_data['bot_username']
                            ]);
                    }
                    
                    sendResponse('success', 'Settings updated successfully');
                    break;

                case 'add_withdraw_method':
                    // Add new withdraw method
                    if (!isset($input['method_name']) || !isset($input['field_name'])) {
                        sendResponse('error', 'Method name and field name required');
                    }

                    $stmt = $pdo->prepare("INSERT INTO withdraw_methods (method_name, field_name) VALUES (?, ?)");
                    $stmt->execute([$input['method_name'], $input['field_name']]);
                    
                    sendResponse('success', 'Withdraw method added successfully');
                    break;

                case 'edit_withdraw_method':
                    // Edit existing withdraw method
                    if (!isset($input['id']) || !isset($input['method_name']) || !isset($input['field_name'])) {
                        sendResponse('error', 'ID, method name, and field name required');
                    }

                    $stmt = $pdo->prepare("UPDATE withdraw_methods SET method_name = ?, field_name = ? WHERE id = ?");
                    $stmt->execute([$input['method_name'], $input['field_name'], $input['id']]);
                    
                    if ($stmt->rowCount() === 0) {
                        sendResponse('error', 'Withdraw method not found');
                    }
                    
                    sendResponse('success', 'Withdraw method updated successfully');
                    break;

                case 'remove_withdraw_method':
                    // Remove withdraw method
                    if (!isset($input['id'])) {
                        sendResponse('error', 'Withdraw method ID required');
                    }

                    $stmt = $pdo->prepare("DELETE FROM withdraw_methods WHERE id = ?");
                    $stmt->execute([$input['id']]);
                    
                    if ($stmt->rowCount() === 0) {
                        sendResponse('error', 'Withdraw method not found');
                    }
                    
                    sendResponse('success', 'Withdraw method removed successfully');
                    break;

                case 'update_withdraw_rules':
                    // Update withdraw rules
                    if (!isset($input['rules']) || !is_array($input['rules'])) {
                        sendResponse('error', 'Invalid withdraw rules data');
                    }

                    $rules = $input['rules'];
                    $allowed_fields = ['min_withdraw', 'max_withdraw', 'min_invite'];
                    $update_data = array_intersect_key($rules, array_flip($allowed_fields));
                    
                    if (empty($update_data)) {
                        sendResponse('error', 'No valid rules to update');
                    }

                    // Prepare update query
                    $set_clause = [];
                    $params = [];
                    foreach ($update_data as $key => $value) {
                        $set_clause[] = "$key = ?";
                        $params[] = $value;
                    }
                    
                    $query = "UPDATE withdraw_rules SET " . implode(', ', $set_clause) . " WHERE id = 1";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    
                    // If no rows affected (withdraw_rules table empty), insert new row
                    if ($stmt->rowCount() === 0) {
                        $default_rules = [
                            'id' => 1,
                            'min_withdraw' => '10.00',
                            'max_withdraw' => '10000.00',
                            'min_invite' => 0
                        ];
                        $update_data = array_merge($default_rules, $update_data);
                        $pdo->prepare("INSERT INTO withdraw_rules (id, min_withdraw, max_withdraw, min_invite) VALUES (?, ?, ?, ?)")
                            ->execute([
                                $update_data['id'],
                                $update_data['min_withdraw'],
                                $update_data['max_withdraw'],
                                $update_data['min_invite']
                            ]);
                    }
                    
                    sendResponse('success', 'Withdraw rules updated successfully');
                    break;

                default:
                    sendResponse('error', 'Invalid action'); 
            }
            break;

        default:
            sendResponse('error', 'Invalid request method');
    }
} catch (Exception $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage());
}
?>