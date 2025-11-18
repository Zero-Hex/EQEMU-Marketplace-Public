<?php
// Prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die('Direct access not permitted');
}

// ============================================================================
// Environment Configuration
// ============================================================================
// Load environment variables from .env file in application root (parent of public/)
// The .env file should be located at: /path/to/eqemu-marketplace/.env
// This keeps sensitive configuration outside the web-accessible public/ directory

$envFile = dirname(__DIR__, 2) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) continue;

        // Parse KEY=VALUE pairs
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Remove quotes if present
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

// Helper function to get environment variable with fallback
function env($key, $default = null) {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// ============================================================================
// Database Configuration
// ============================================================================
// IMPORTANT: All credentials MUST be defined in .env file
// NO default credentials are provided for security reasons

$dbHost = env('DB_HOST');
$dbName = env('DB_NAME');
$dbUser = env('DB_USER');
$dbPass = env('DB_PASS');

// Validate required database configuration
if (empty($dbHost) || empty($dbName) || empty($dbUser) || $dbPass === null) {
    die('CONFIGURATION ERROR: Database credentials not found. Please configure .env file in the application root directory.');
}

define('DB_HOST', $dbHost);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_CHARSET', 'utf8mb4');

// ============================================================================
// JWT Configuration
// ============================================================================
// IMPORTANT: JWT_SECRET must be defined in .env file for security

$jwtSecret = env('JWT_SECRET');
if (empty($jwtSecret)) {
    die('CONFIGURATION ERROR: JWT_SECRET not found. Please configure .env file with a secure random string.');
}

define('JWT_SECRET', $jwtSecret);
define('JWT_EXPIRATION', env('JWT_EXPIRATION', 86400)); // 24 hours in seconds

// ============================================================================
// CORS Settings
// ============================================================================

define('ALLOWED_ORIGIN', env('ALLOWED_ORIGIN', '*')); // Change to your domain in production

// ============================================================================
// Error Reporting
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', env('DEBUG_MODE', '1'));

// Timezone
date_default_timezone_set('UTC');

// ============================================================================
// Alternate Currency Configuration (Optional High-Value Currency System)
// ============================================================================
// Set USE_ALT_CURRENCY to false to use platinum-only marketplace (default)
// Set to true to enable custom alternate currency for high-value transactions

define('USE_ALT_CURRENCY', false); // Default: platinum-only marketplace

// If USE_ALT_CURRENCY is true, configure these settings:
define('ALT_CURRENCY_ITEM_ID', 147623); // Item ID for your alternate currency
define('ALT_CURRENCY_VALUE_PLATINUM', 1000000); // How much platinum = 1 alt currency
define('ALT_CURRENCY_NAME', 'Bitcoin'); // Display name for your alternate currency

// Calculated values (do not modify)
define('ALT_CURRENCY_VALUE_COPPER', ALT_CURRENCY_VALUE_PLATINUM * 1000);

// Legacy constants for backwards compatibility
define('BITCOIN_ID', ALT_CURRENCY_ITEM_ID);
define('BITCOIN_VALUE_PLATINUM', ALT_CURRENCY_VALUE_PLATINUM);
define('BITCOIN_VALUE_COPPER', ALT_CURRENCY_VALUE_COPPER);

// Database connection class with schema caching
class Database {
    private $conn = null;
    private static $schemaCache = [];

    public function getConnection() {
        if ($this->conn === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch(PDOException $e) {
                error_log("Connection Error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database connection failed']);
                exit();
            }
        }

        return $this->conn;
    }

    /**
     * Check if a table exists (cached for performance)
     * @param string $tableName The table name to check
     * @return bool True if table exists, false otherwise
     */
    public function tableExists($tableName) {
        $cacheKey = 'table_' . $tableName;

        if (!isset(self::$schemaCache[$cacheKey])) {
            $conn = $this->getConnection();
            $stmt = $conn->prepare("
                SELECT TABLE_NAME
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table_name
            ");
            $stmt->execute([':table_name' => $tableName]);
            self::$schemaCache[$cacheKey] = (bool)$stmt->fetch();
        }

        return self::$schemaCache[$cacheKey];
    }

    /**
     * Check if a column exists in a table (cached for performance)
     * @param string $tableName The table name
     * @param string $columnName The column name to check
     * @return bool True if column exists, false otherwise
     */
    public function columnExists($tableName, $columnName) {
        $cacheKey = 'column_' . $tableName . '_' . $columnName;

        if (!isset(self::$schemaCache[$cacheKey])) {
            $conn = $this->getConnection();
            $stmt = $conn->prepare("
                SELECT COLUMN_NAME
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table_name
                AND COLUMN_NAME = :column_name
            ");
            $stmt->execute([
                ':table_name' => $tableName,
                ':column_name' => $columnName
            ]);
            self::$schemaCache[$cacheKey] = (bool)$stmt->fetch();
        }

        return self::$schemaCache[$cacheKey];
    }
}

// Helper function to send JSON response
function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    echo json_encode($data);
    exit();
}

// Helper function to handle CORS preflight
function handleCORS() {
    header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// Helper function to get request data from POST body
function getRequestData() {
    $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

    if (stripos($contentType, 'application/json') !== false) {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);
        return $data ?: [];
    }

    return $_POST;
}

// Simple JWT implementation
class JWT {
    public static function encode($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);
        
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
    
    public static function decode($jwt) {
        $tokenParts = explode('.', $jwt);
        
        if (count($tokenParts) != 3) {
            return null;
        }
        
        $header = base64_decode($tokenParts[0]);
        $payload = base64_decode($tokenParts[1]);
        $signatureProvided = $tokenParts[2];
        
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        if ($base64UrlSignature !== $signatureProvided) {
            return null;
        }
        
        $payloadData = json_decode($payload, true);
        
        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
            return null;
        }
        
        return $payloadData;
    }
}

// Get authenticated user from token
function getAuthenticatedUser() {
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;
    
    if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return null;
    }
    
    $token = $matches[1];
    $payload = JWT::decode($token);
    
    return $payload;
}

// Require authentication
function requireAuth() {
    $user = getAuthenticatedUser();

    if (!$user) {
        sendJSON(['error' => 'Unauthorized'], 401);
    }

    return $user;
}

// ============================================================================
// Alternate Currency Helper Functions
// ============================================================================
// Note: Currency constants (BITCOIN_ID, etc.) are configured at top of file
// These functions work with alternate currency if USE_ALT_CURRENCY is enabled

/**
 * Get alternate currency count from character inventory
 * @param PDO $conn Database connection
 * @param int $charId Character ID
 * @return int Number of Bitcoin in inventory
 */
function getBitcoinFromInventory($conn, $charId) {
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(charges), 0) as bitcoin_count
        FROM inventory
        WHERE character_id = :char_id AND item_id = :bitcoin_id
    ");
    $stmt->execute([
        ':char_id' => $charId,
        ':bitcoin_id' => BITCOIN_ID
    ]);
    $result = $stmt->fetch();
    return intval($result['bitcoin_count']);
}

/**
 * Get Bitcoin count from character alternate currency
 * @param PDO $conn Database connection
 * @param int $charId Character ID
 * @return int Number of Bitcoin in alternate currency
 */
function getBitcoinFromAlternateCurrency($conn, $charId) {
    // Check if character_currency_alternate table exists
    $stmt = $conn->prepare("
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'character_currency_alternate'
    ");
    $stmt->execute();

    if (!$stmt->fetch()) {
        return 0; // Table doesn't exist
    }

    $stmt = $conn->prepare("
        SELECT COALESCE(amount, 0) as bitcoin_count
        FROM character_currency_alternate
        WHERE char_id = :char_id AND currency_id = :bitcoin_id
    ");
    $stmt->execute([
        ':char_id' => $charId,
        ':bitcoin_id' => BITCOIN_ID
    ]);
    $result = $stmt->fetch();
    return $result ? intval($result['bitcoin_count']) : 0;
}

/**
 * Get total Bitcoin available for character
 * @param PDO $conn Database connection
 * @param int $charId Character ID
 * @return array ['inventory' => int, 'alternate' => int, 'total' => int]
 */
function getTotalBitcoin($conn, $charId) {
    // If alternate currency is disabled, return zeros
    if (!USE_ALT_CURRENCY) {
        return [
            'inventory' => 0,
            'alternate' => 0,
            'total' => 0
        ];
    }

    $inventory = getBitcoinFromInventory($conn, $charId);
    $alternate = getBitcoinFromAlternateCurrency($conn, $charId);

    return [
        'inventory' => $inventory,
        'alternate' => $alternate,
        'total' => $inventory + $alternate
    ];
}

/**
 * Deduct Bitcoin from character inventory
 * @param PDO $conn Database connection
 * @param int $charId Character ID
 * @param int $amount Amount to deduct
 * @return bool Success
 */
function deductBitcoinFromInventory($conn, $charId, $amount) {
    if ($amount <= 0) return true;

    // Get all Bitcoin items in inventory
    $stmt = $conn->prepare("
        SELECT slot_id, charges
        FROM inventory
        WHERE character_id = :char_id AND item_id = :bitcoin_id
        ORDER BY slot_id ASC
        FOR UPDATE
    ");
    $stmt->execute([
        ':char_id' => $charId,
        ':bitcoin_id' => BITCOIN_ID
    ]);

    $items = $stmt->fetchAll();
    $remaining = $amount;

    foreach ($items as $item) {
        if ($remaining <= 0) break;

        $charges = intval($item['charges']);
        $slotId = $item['slot_id'];

        if ($charges <= $remaining) {
            // Delete entire stack
            $deleteStmt = $conn->prepare("
                DELETE FROM inventory
                WHERE character_id = :char_id AND slot_id = :slot_id
            ");
            $deleteStmt->execute([
                ':char_id' => $charId,
                ':slot_id' => $slotId
            ]);
            $remaining -= $charges;
        } else {
            // Reduce charges
            $updateStmt = $conn->prepare("
                UPDATE inventory
                SET charges = charges - :amount
                WHERE character_id = :char_id AND slot_id = :slot_id
            ");
            $updateStmt->execute([
                ':amount' => $remaining,
                ':char_id' => $charId,
                ':slot_id' => $slotId
            ]);
            $remaining = 0;
        }
    }

    return $remaining == 0;
}

/**
 * Deduct Bitcoin from character alternate currency
 * @param PDO $conn Database connection
 * @param int $charId Character ID
 * @param int $amount Amount to deduct
 * @return bool Success
 */
function deductBitcoinFromAlternateCurrency($conn, $charId, $amount) {
    if ($amount <= 0) return true;

    // Check if table exists
    $stmt = $conn->prepare("
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'character_currency_alternate'
    ");
    $stmt->execute();

    if (!$stmt->fetch()) {
        return false; // Table doesn't exist
    }

    $stmt = $conn->prepare("
        UPDATE character_currency_alternate
        SET amount = amount - :amount
        WHERE char_id = :char_id AND currency_id = :bitcoin_id AND amount >= :amount
    ");

    $stmt->execute([
        ':amount' => $amount,
        ':char_id' => $charId,
        ':bitcoin_id' => BITCOIN_ID
    ]);

    return $stmt->rowCount() > 0;
}

/**
 * Calculate payment breakdown for purchases
 * Over 1M platinum: Uses Bitcoin first, then platinum for remainder
 * Under 1M platinum: Uses platinum first, then Bitcoin if needed
 *
 * @param int $priceCopper Total price in copper
 * @param int $availablePlatinum Available platinum
 * @param int $availableBitcoin Available Bitcoin count
 * @param bool $bitcoinFirst If true, prioritize Bitcoin (for >1M purchases), else prioritize platinum
 * @return array Payment breakdown details
 */
function calculateBitcoinPayment($priceCopper, $availablePlatinum, $availableBitcoin, $bitcoinFirst = false) {
    // If alternate currency is disabled, return platinum-only calculation
    if (!USE_ALT_CURRENCY) {
        $pricePlatinum = $priceCopper / 1000;
        $canAfford = $availablePlatinum >= $pricePlatinum;
        return [
            'bitcoin_to_deduct' => 0,
            'platinum_to_deduct' => $canAfford ? $pricePlatinum : $availablePlatinum,
            'platinum_to_refund' => 0,
            'total_sufficient' => $canAfford,
            'payment_method' => 'platinum_only'
        ];
    }

    $pricePlatinum = $priceCopper / 1000;

    if ($bitcoinFirst) {
        // Bitcoin-first logic (for purchases > 1M platinum)
        // Calculate how much Bitcoin we need to cover the price
        $bitcoinNeeded = floor($pricePlatinum / BITCOIN_VALUE_PLATINUM);
        $remainingAfterBitcoin = $pricePlatinum - ($bitcoinNeeded * BITCOIN_VALUE_PLATINUM);

        // Check if we have enough Bitcoin
        if ($availableBitcoin < $bitcoinNeeded) {
            // Not enough Bitcoin, calculate what we can do
            $bitcoinValueProvided = $availableBitcoin * BITCOIN_VALUE_PLATINUM;
            $platinumStillNeeded = $pricePlatinum - $bitcoinValueProvided;

            return [
                'bitcoin_to_deduct' => $availableBitcoin,
                'platinum_to_deduct' => $platinumStillNeeded,
                'platinum_to_refund' => 0,
                'total_sufficient' => ($availablePlatinum >= $platinumStillNeeded),
                'bitcoin_available' => $availableBitcoin,
                'payment_method' => 'bitcoin_first'
            ];
        }

        // We have enough Bitcoin, check if platinum covers remainder
        if ($availablePlatinum >= $remainingAfterBitcoin) {
            return [
                'bitcoin_to_deduct' => $bitcoinNeeded,
                'platinum_to_deduct' => $remainingAfterBitcoin,
                'platinum_to_refund' => 0,
                'total_sufficient' => true,
                'bitcoin_available' => $availableBitcoin,
                'payment_method' => 'bitcoin_first'
            ];
        } else {
            // Not enough platinum for remainder, need one more Bitcoin
            $bitcoinNeeded++;
            if ($availableBitcoin < $bitcoinNeeded) {
                return [
                    'bitcoin_to_deduct' => $availableBitcoin,
                    'platinum_to_deduct' => $availablePlatinum,
                    'platinum_to_refund' => 0,
                    'total_sufficient' => false,
                    'bitcoin_available' => $availableBitcoin,
                    'payment_method' => 'bitcoin_first'
                ];
            }

            // Calculate refund from extra Bitcoin
            $totalPaid = ($bitcoinNeeded * BITCOIN_VALUE_PLATINUM);
            $platinumToRefund = $totalPaid - $pricePlatinum;

            return [
                'bitcoin_to_deduct' => $bitcoinNeeded,
                'platinum_to_deduct' => 0,
                'platinum_to_refund' => $platinumToRefund,
                'total_sufficient' => true,
                'bitcoin_available' => $availableBitcoin,
                'payment_method' => 'bitcoin_first'
            ];
        }
    } else {
        // Platinum-first logic (for purchases < 1M platinum)
        // If enough platinum, no Bitcoin needed
        if ($availablePlatinum >= $pricePlatinum) {
            return [
                'bitcoin_to_deduct' => 0,
                'platinum_to_deduct' => $pricePlatinum,
                'platinum_to_refund' => 0,
                'total_sufficient' => true,
                'bitcoin_available' => $availableBitcoin,
                'payment_method' => 'platinum_first'
            ];
        }

        // Calculate how much we need after platinum
        $platinumShortfall = $pricePlatinum - $availablePlatinum;
        $bitcoinNeeded = ceil($platinumShortfall / BITCOIN_VALUE_PLATINUM);

        // Check if we have enough Bitcoin
        if ($availableBitcoin < $bitcoinNeeded) {
            return [
                'bitcoin_to_deduct' => $availableBitcoin,
                'platinum_to_deduct' => $availablePlatinum,
                'platinum_to_refund' => 0,
                'total_sufficient' => false,
                'bitcoin_available' => $availableBitcoin,
                'payment_method' => 'platinum_first'
            ];
        }

        // Calculate refund
        $bitcoinValueUsed = $bitcoinNeeded * BITCOIN_VALUE_PLATINUM;
        $platinumToRefund = ($availablePlatinum + $bitcoinValueUsed) - $pricePlatinum;

        return [
            'bitcoin_to_deduct' => $bitcoinNeeded,
            'platinum_to_deduct' => $availablePlatinum,
            'platinum_to_refund' => $platinumToRefund,
            'total_sufficient' => true,
            'bitcoin_available' => $availableBitcoin,
            'payment_method' => 'platinum_first'
        ];
    }
}

/**
 * Execute Bitcoin payment (deduct Bitcoin from inventory and alternate currency)
 * @param PDO $conn Database connection
 * @param int $charId Character ID
 * @param int $bitcoinAmount Amount of Bitcoin to deduct
 * @return array ['success' => bool, 'from_inventory' => int, 'from_alternate' => int]
 */
function executeBitcoinPayment($conn, $charId, $bitcoinAmount) {
    $bitcoin = getTotalBitcoin($conn, $charId);

    if ($bitcoin['total'] < $bitcoinAmount) {
        return [
            'success' => false,
            'error' => 'Insufficient Bitcoin'
        ];
    }

    $fromInventory = 0;
    $fromAlternate = 0;
    $remaining = $bitcoinAmount;

    // Try to take from alternate currency first
    if ($bitcoin['alternate'] > 0 && $remaining > 0) {
        $takeFromAlternate = min($bitcoin['alternate'], $remaining);
        if (deductBitcoinFromAlternateCurrency($conn, $charId, $takeFromAlternate)) {
            $fromAlternate = $takeFromAlternate;
            $remaining -= $takeFromAlternate;
        }
    }

    // Take remaining from inventory
    if ($remaining > 0 && $bitcoin['inventory'] > 0) {
        if (deductBitcoinFromInventory($conn, $charId, $remaining)) {
            $fromInventory = $remaining;
            $remaining = 0;
        }
    }

    return [
        'success' => $remaining == 0,
        'from_inventory' => $fromInventory,
        'from_alternate' => $fromAlternate,
        'total_deducted' => $fromInventory + $fromAlternate
    ];
}

/**
 * Add Bitcoin to character alternate currency
 * @param PDO $conn Database connection
 * @param int $charId Character ID
 * @param int $amount Amount of Bitcoin to add
 * @return bool Success
 */
function addBitcoinToAlternateCurrency($conn, $charId, $amount) {
    if ($amount <= 0) return true;

    // Check if table exists
    $stmt = $conn->prepare("
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'character_currency_alternate'
    ");
    $stmt->execute();

    if (!$stmt->fetch()) {
        return false; // Table doesn't exist
    }

    // Check if record exists
    $stmt = $conn->prepare("
        SELECT amount
        FROM character_currency_alternate
        WHERE char_id = :char_id AND currency_id = :bitcoin_id
    ");
    $stmt->execute([
        ':char_id' => $charId,
        ':bitcoin_id' => BITCOIN_ID
    ]);

    $existing = $stmt->fetch();

    if ($existing) {
        // Update existing record
        $stmt = $conn->prepare("
            UPDATE character_currency_alternate
            SET amount = amount + :amount
            WHERE char_id = :char_id AND currency_id = :bitcoin_id
        ");
        $stmt->execute([
            ':amount' => $amount,
            ':char_id' => $charId,
            ':bitcoin_id' => BITCOIN_ID
        ]);
    } else {
        // Insert new record
        $stmt = $conn->prepare("
            INSERT INTO character_currency_alternate (char_id, currency_id, amount)
            VALUES (:char_id, :bitcoin_id, :amount)
        ");
        $stmt->execute([
            ':char_id' => $charId,
            ':bitcoin_id' => BITCOIN_ID,
            ':amount' => $amount
        ]);
    }

    return true;
}

/**
 * Convert earnings to Bitcoin and platinum
 * For amounts over 1M platinum, convert millions to Bitcoin and remainder to platinum
 * @param int $totalCopper Total earnings in copper
 * @return array ['bitcoin' => int, 'platinum_remainder' => int]
 */
function convertEarningsToBitcoin($totalCopper) {
    $totalPlatinum = $totalCopper / 1000;

    // If alternate currency is disabled, return all as platinum
    if (!USE_ALT_CURRENCY) {
        return [
            'bitcoin' => 0,
            'platinum_remainder' => $totalPlatinum,
            'copper_remainder' => $totalCopper
        ];
    }

    if ($totalPlatinum <= BITCOIN_VALUE_PLATINUM) {
        return [
            'bitcoin' => 0,
            'platinum_remainder' => $totalPlatinum,
            'copper_remainder' => $totalCopper
        ];
    }

    // Calculate Bitcoin (millions/billions)
    $bitcoin = floor($totalPlatinum / BITCOIN_VALUE_PLATINUM);
    $platinumRemainder = $totalPlatinum - ($bitcoin * BITCOIN_VALUE_PLATINUM);
    $copperRemainder = $platinumRemainder * 1000;

    return [
        'bitcoin' => $bitcoin,
        'platinum_remainder' => $platinumRemainder,
        'copper_remainder' => $copperRemainder
    ];
}
?>
