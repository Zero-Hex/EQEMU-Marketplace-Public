<?php
require_once '../config.php';

handleCORS();

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['username']) || !isset($data['password'])) {
    sendJSON(['error' => 'Username and password required'], 400);
}

$username = $data['username'];
$password = $data['password'];

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Check if user is registered in marketplace_users table (with hashed password)
    $stmt = $conn->prepare("
        SELECT mu.account_id, mu.account_name, mu.password_hash, mu.active_account_id, a.status
        FROM marketplace_users mu
        JOIN account a ON mu.account_id = a.id
        WHERE mu.account_name = :username
        AND mu.is_active = 1
        LIMIT 1
    ");

    $stmt->execute([':username' => $username]);
    $marketplace_user = $stmt->fetch();

    if ($marketplace_user) {
        // Verify hashed password
        if (!password_verify($password, $marketplace_user['password_hash'])) {
            sendJSON(['error' => 'Invalid username or password'], 401);
        }

        // Update last login
        $stmt = $conn->prepare("UPDATE marketplace_users SET last_login = NOW() WHERE account_id = :account_id");
        $stmt->execute([':account_id' => $marketplace_user['account_id']]);

        $account = [
            'id' => $marketplace_user['account_id'],
            'name' => $marketplace_user['account_name'],
            'status' => $marketplace_user['status']
        ];

        // Store the active_account_id for character fetching
        $active_account_id = $marketplace_user['active_account_id'] ?: $marketplace_user['account_id'];
    } else {
        // Fallback to legacy EQEMU account authentication (plaintext password)
        // This allows existing non-registered users to still log in
        $stmt = $conn->prepare("
            SELECT id, name, status
            FROM account
            WHERE name = :username
            AND password = :password
            LIMIT 1
        ");

        $stmt->execute([
            ':username' => $username,
            ':password' => $password
        ]);

        $account = $stmt->fetch();

        if (!$account) {
            sendJSON(['error' => 'Invalid username or password'], 401);
        }

        // For legacy accounts, active account is the same as primary account
        $active_account_id = $account['id'];
    }

    // Get user's characters from the active account
    $stmt = $conn->prepare("
        SELECT id, name, level, class, race
        FROM character_data
        WHERE account_id = :account_id
        AND deleted_at IS NULL
        ORDER BY name
    ");

    $stmt->execute([':account_id' => $active_account_id]);
    $characters = $stmt->fetchAll();
    
    // Create JWT token
    $payload = [
        'account_id' => $account['id'],
        'username' => $account['name'],
        'status' => $account['status'],
        'exp' => time() + JWT_EXPIRATION
    ];
    
    $token = JWT::encode($payload);
    
    sendJSON([
        'success' => true,
        'token' => $token,
        'user' => [
            'id' => $account['id'],
            'name' => $account['name'],
            'status' => $account['status']
        ],
        'characters' => $characters
    ]);
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    sendJSON(['error' => 'Login failed'], 500);
}
?>
