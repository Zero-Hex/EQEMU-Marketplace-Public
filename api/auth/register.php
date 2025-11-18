<?php
require_once '../config.php';

handleCORS();

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['account_name']) || !isset($data['character_id']) ||
    !isset($data['password']) || !isset($data['confirm_password'])) {
    sendJSON(['error' => 'Account name, character ID, password, and password confirmation are required'], 400);
}

$account_name = trim($data['account_name']);
$character_id = intval($data['character_id']);
$password = $data['password'];
$confirm_password = $data['confirm_password'];
$email = isset($data['email']) ? trim($data['email']) : null;

// Validate password match
if ($password !== $confirm_password) {
    sendJSON(['error' => 'Passwords do not match'], 400);
}

// Validate password strength
if (strlen($password) < 6) {
    sendJSON(['error' => 'Password must be at least 6 characters long'], 400);
}

// Validate email format if provided
if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJSON(['error' => 'Invalid email address'], 400);
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Step 1: Verify the account exists
    $stmt = $conn->prepare("SELECT id, name FROM account WHERE name = :account_name LIMIT 1");
    $stmt->execute([':account_name' => $account_name]);
    $account = $stmt->fetch();

    if (!$account) {
        sendJSON(['error' => 'Account not found. Please check your account name.'], 404);
    }

    $account_id = $account['id'];

    // Step 2: Verify the character belongs to this account
    $stmt = $conn->prepare("
        SELECT id, name
        FROM character_data
        WHERE id = :character_id
        AND account_id = :account_id
        AND deleted_at IS NULL
        LIMIT 1
    ");

    $stmt->execute([
        ':character_id' => $character_id,
        ':account_id' => $account_id
    ]);

    $character = $stmt->fetch();

    if (!$character) {
        sendJSON(['error' => 'Character verification failed. This character does not belong to the specified account.'], 403);
    }

    // Step 3: Check if account is already registered
    $stmt = $conn->prepare("SELECT id FROM marketplace_users WHERE account_id = :account_id LIMIT 1");
    $stmt->execute([':account_id' => $account_id]);

    if ($stmt->fetch()) {
        sendJSON(['error' => 'This account is already registered. Please use the login form.'], 409);
    }

    // Step 4: Check if account name is already registered (double-check for uniqueness)
    $stmt = $conn->prepare("SELECT id FROM marketplace_users WHERE account_name = :account_name LIMIT 1");
    $stmt->execute([':account_name' => $account_name]);

    if ($stmt->fetch()) {
        sendJSON(['error' => 'This account name is already registered.'], 409);
    }

    // Step 5: Hash the password using bcrypt
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Step 6: Insert into marketplace_users table
    $stmt = $conn->prepare("
        INSERT INTO marketplace_users (account_id, account_name, password_hash, email, registered_date)
        VALUES (:account_id, :account_name, :password_hash, :email, NOW())
    ");

    $stmt->execute([
        ':account_id' => $account_id,
        ':account_name' => $account_name,
        ':password_hash' => $password_hash,
        ':email' => $email
    ]);

    // Step 7: Return success response
    sendJSON([
        'success' => true,
        'message' => 'Registration successful! You can now log in with your credentials.',
        'account_name' => $account_name
    ], 201);

} catch (PDOException $e) {
    error_log("Registration error: " . $e->getMessage());

    // Check for duplicate entry error
    if ($e->getCode() == 23000) {
        sendJSON(['error' => 'This account is already registered.'], 409);
    }

    sendJSON(['error' => 'Registration failed. Please try again.'], 500);
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    sendJSON(['error' => 'Registration failed. Please try again.'], 500);
}
?>
