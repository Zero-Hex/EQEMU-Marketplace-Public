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
// Configure in .env file:
// - USE_ALT_CURRENCY=false (platinum-only, default)
// - USE_ALT_CURRENCY=true (enable alternate currency)

// Load from .env or default to false (platinum-only)
$useAltCurrency = env('USE_ALT_CURRENCY', 'false');
$useAltCurrency = filter_var($useAltCurrency, FILTER_VALIDATE_BOOLEAN);
define('USE_ALT_CURRENCY', $useAltCurrency);

// Load alternate currency settings from .env (with defaults)
define('ALT_CURRENCY_ITEM_ID', env('ALT_CURRENCY_ITEM_ID', 147623));
define('ALT_CURRENCY_VALUE_PLATINUM', env('ALT_CURRENCY_VALUE_PLATINUM', 1000000));
define('ALT_CURRENCY_NAME', env('ALT_CURRENCY_NAME', 'Alt Currency'));

// Calculated values (do not modify)
define('ALT_CURRENCY_VALUE_COPPER', ALT_CURRENCY_VALUE_PLATINUM * 1000);

// ============================================================================
// Frontend Configuration (from .env)
// ============================================================================
define('ICON_BASE_URL', env('ICON_BASE_URL', ''));
define('DEFAULT_ICON', env('DEFAULT_ICON', '🎒'));
define('ITEMS_PER_PAGE', env('ITEMS_PER_PAGE', 20));
define('COPPER_TO_PLATINUM', env('COPPER_TO_PLATINUM', 1000));
define('REFRESH_INTERVAL_SECONDS', env('REFRESH_INTERVAL_SECONDS', 30));

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
    header('Access-Control-Max-Age: 3600');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// Set modern security headers (backup if .htaccess doesn't set them)
function setSecurityHeaders() {
    // Only set if not already set by Apache
    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: http: https:; connect-src 'self'; frame-src http: https:; frame-ancestors 'self';");
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
// Note: Currency constants (ALT_CURRENCY_ITEM_ID, etc.) are configured at top of file
// These functions work with alternate currency if USE_ALT_CURRENCY is enabled

/**
 * Get alternate currency count from character inventory
 * @param PDO $conn Database connection
 * @param int $charId Character ID
 * @return int Number of alt currency in inventory
 */
function getAltCurrencyFromInventory($conn, $charId) {
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(charges), 0) as altcurrency_count
        FROM inventory
        WHERE character_id = :char_id AND item_id = :altcurrency_id
    ");
    $stmt->execute([
        ':char_id' => $charId,
        ':altcurrency_id' => ALT_CURRENCY_ITEM_ID
    ]);
    $result = $stmt->fetch();
    return intval($result['altcurrency_count']);
}

/**
 * Get alt currency count from character alternate currency
 * @param PDO $conn Database connection
 * @param int $charId Character ID
 * @return int Number of alt currency in alternate currency
 */
function getAltCurrencyFromAlternateCurrency($conn, $charId) {
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
        SELECT COALESCE(amount, 0) as altcurrency_count
        FROM character_currency_alternate
        WHERE char_id = :char_id AND currency_id = :altcurrency_id
    ");
    $stmt->execute([
        ':char_id' => $charId,
        ':altcurrency_id' => ALT_CURRENCY_ITEM_ID
    ]);
    $result = $stmt->fetch();
    return $result ? intval($result['altcurrency_count']) : 0;
}

/**
 * Get total alt currency available for character
 * @param PDO $conn Database connection
 * @param int $charId Character ID
 * @return array ['inventory' => int, 'alternate' => int, 'total' => int]
 */
function getTotalAltCurrency($conn, $charId) {
    // If alternate currency is disabled, return zeros
    if (!USE_ALT_CURRENCY) {
        return [
            'inventory' => 0,
            'alternate' => 0,
            'total' => 0
        ];
    }

    $inventory = getAltCurrencyFromInventory($conn, $charId);
    $alternate = getAltCurrencyFromAlternateCurrency($conn, $charId);

    return [
        'inventory' => $inventory,
        'alternate' => $alternate,
        'total' => $inventory + $alternate
    ];
}

/**
 * Deduct alt currency from character inventory
 * @param PDO $conn Database connection
 * @param int $charId Character ID
 * @param int $amount Amount to deduct
 * @return bool Success
 */
function deductAltCurrencyFromInventory($conn, $charId, $amount) {
    if ($amount <= 0) return true;

    // Get all alt currency items in inventory
    $stmt = $conn->prepare("
        SELECT slot_id, charges
        FROM inventory
        WHERE character_id = :char_id AND item_id = :altcurrency_id
        ORDER BY slot_id ASC
        FOR UPDATE
    ");
    $stmt->execute([
        ':char_id' => $charId,
        ':altcurrency_id' => ALT_CURRENCY_ITEM_ID
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
 * Deduct alt currency from character alternate currency
 * @param PDO $conn Database connection
 * @param int $charId Character ID
 * @param int $amount Amount to deduct
 * @return bool Success
 */
function deductAltCurrencyFromAlternateCurrency($conn, $charId, $amount) {
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
        WHERE char_id = :char_id AND currency_id = :altcurrency_id AND amount >= :amount
    ");

    $stmt->execute([
        ':amount' => $amount,
        ':char_id' => $charId,
        ':altcurrency_id' => ALT_CURRENCY_ITEM_ID
    ]);

    return $stmt->rowCount() > 0;
}

/**
 * Calculate payment breakdown for purchases
 * Over 1M platinum: Uses alt currency first, then platinum for remainder
 * Under 1M platinum: Uses platinum first, then alt currency if needed
 *
 * @param int $priceCopper Total price in copper
 * @param int $availablePlatinum Available platinum
 * @param int $availableAltCurrency Available alt currency count
 * @param bool $altCurrencyFirst If true, prioritize alt currency (for >1M purchases), else prioritize platinum
 * @return array Payment breakdown details
 */
function calculateAltCurrencyPayment($priceCopper, $availablePlatinum, $availableAltCurrency, $altCurrencyFirst = false) {
    // If alternate currency is disabled, return platinum-only calculation
    if (!USE_ALT_CURRENCY) {
        $pricePlatinum = $priceCopper / 1000;
        $canAfford = $availablePlatinum >= $pricePlatinum;
        return [
            'alt_currency_to_deduct' => 0,
            'platinum_to_deduct' => $canAfford ? $pricePlatinum : $availablePlatinum,
            'platinum_to_refund' => 0,
            'total_sufficient' => $canAfford,
            'payment_method' => 'platinum_only'
        ];
    }

    $pricePlatinum = $priceCopper / 1000;

    if ($altCurrencyFirst) {
        // alt currency-first logic (for purchases > 1M platinum)
        // Calculate how much alt currency we need to cover the price
        $altCurrencyNeeded = floor($pricePlatinum / ALT_CURRENCY_VALUE_PLATINUM);
        $remainingAfterAltCurrency = $pricePlatinum - ($altCurrencyNeeded * ALT_CURRENCY_VALUE_PLATINUM);

        // Check if we have enough alt currency
        if ($availableAltCurrency < $altCurrencyNeeded) {
            // Not enough alt currency, calculate what we can do
            $altCurrencyValueProvided = $availableAltCurrency * ALT_CURRENCY_VALUE_PLATINUM;
            $platinumStillNeeded = $pricePlatinum - $altCurrencyValueProvided;

            return [
                'alt_currency_to_deduct' => $availableAltCurrency,
                'platinum_to_deduct' => $platinumStillNeeded,
                'platinum_to_refund' => 0,
                'total_sufficient' => ($availablePlatinum >= $platinumStillNeeded),
                'alt_currency_available' => $availableAltCurrency,
                'payment_method' => 'altcurrency_first'
            ];
        }

        // We have enough alt currency, check if platinum covers remainder
        if ($availablePlatinum >= $remainingAfterAltCurrency) {
            return [
                'alt_currency_to_deduct' => $altCurrencyNeeded,
                'platinum_to_deduct' => $remainingAfterAltCurrency,
                'platinum_to_refund' => 0,
                'total_sufficient' => true,
                'alt_currency_available' => $availableAltCurrency,
                'payment_method' => 'altcurrency_first'
            ];
        } else {
            // Not enough platinum for remainder, need one more alt currency
            $altCurrencyNeeded++;
            if ($availableAltCurrency < $altCurrencyNeeded) {
                return [
                    'alt_currency_to_deduct' => $availableAltCurrency,
                    'platinum_to_deduct' => $availablePlatinum,
                    'platinum_to_refund' => 0,
                    'total_sufficient' => false,
                    'alt_currency_available' => $availableAltCurrency,
                    'payment_method' => 'altcurrency_first'
                ];
            }

            // Calculate refund from extra alt currency
            $totalPaid = ($altCurrencyNeeded * ALT_CURRENCY_VALUE_PLATINUM);
            $platinumToRefund = $totalPaid - $pricePlatinum;

            return [
                'alt_currency_to_deduct' => $altCurrencyNeeded,
                'platinum_to_deduct' => 0,
                'platinum_to_refund' => $platinumToRefund,
                'total_sufficient' => true,
                'alt_currency_available' => $availableAltCurrency,
                'payment_method' => 'altcurrency_first'
            ];
        }
    } else {
        // Platinum-first logic (for purchases < 1M platinum)
        // If enough platinum, no alt currency needed
        if ($availablePlatinum >= $pricePlatinum) {
            return [
                'alt_currency_to_deduct' => 0,
                'platinum_to_deduct' => $pricePlatinum,
                'platinum_to_refund' => 0,
                'total_sufficient' => true,
                'alt_currency_available' => $availableAltCurrency,
                'payment_method' => 'platinum_first'
            ];
        }

        // Calculate how much we need after platinum
        $platinumShortfall = $pricePlatinum - $availablePlatinum;
        $altCurrencyNeeded = ceil($platinumShortfall / ALT_CURRENCY_VALUE_PLATINUM);

        // Check if we have enough alt currency
        if ($availableAltCurrency < $altCurrencyNeeded) {
            return [
                'alt_currency_to_deduct' => $availableAltCurrency,
                'platinum_to_deduct' => $availablePlatinum,
                'platinum_to_refund' => 0,
                'total_sufficient' => false,
                'alt_currency_available' => $availableAltCurrency,
                'payment_method' => 'platinum_first'
            ];
        }

        // Calculate refund
        $altCurrencyValueUsed = $altCurrencyNeeded * ALT_CURRENCY_VALUE_PLATINUM;
        $platinumToRefund = ($availablePlatinum + $altCurrencyValueUsed) - $pricePlatinum;

        return [
            'alt_currency_to_deduct' => $altCurrencyNeeded,
            'platinum_to_deduct' => $availablePlatinum,
            'platinum_to_refund' => $platinumToRefund,
            'total_sufficient' => true,
            'alt_currency_available' => $availableAltCurrency,
            'payment_method' => 'platinum_first'
        ];
    }
}

/**
 * Execute alt currency payment (deduct alt currency from inventory and alternate currency)
 * @param PDO $conn Database connection
 * @param int $charId Character ID
 * @param int $altCurrencyAmount Amount of alt currency to deduct
 * @return array ['success' => bool, 'from_inventory' => int, 'from_alternate' => int]
 */
function executeAltCurrencyPayment($conn, $charId, $altCurrencyAmount) {
    $altCurrency = getTotalAltCurrency($conn, $charId);

    if ($altCurrency['total'] < $altCurrencyAmount) {
        return [
            'success' => false,
            'error' => 'Insufficient alt currency'
        ];
    }

    $fromInventory = 0;
    $fromAlternate = 0;
    $remaining = $altCurrencyAmount;

    // Try to take from alternate currency first
    if ($altCurrency['alternate'] > 0 && $remaining > 0) {
        $takeFromAlternate = min($altCurrency['alternate'], $remaining);
        if (deductAltCurrencyFromAlternateCurrency($conn, $charId, $takeFromAlternate)) {
            $fromAlternate = $takeFromAlternate;
            $remaining -= $takeFromAlternate;
        }
    }

    // Take remaining from inventory
    if ($remaining > 0 && $altCurrency['inventory'] > 0) {
        if (deductAltCurrencyFromInventory($conn, $charId, $remaining)) {
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
 * Add alt currency to character alternate currency
 * @param PDO $conn Database connection
 * @param int $charId Character ID
 * @param int $amount Amount of alt currency to add
 * @return bool Success
 */
function addAltCurrencyToAlternateCurrency($conn, $charId, $amount) {
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
        WHERE char_id = :char_id AND currency_id = :altcurrency_id
    ");
    $stmt->execute([
        ':char_id' => $charId,
        ':altcurrency_id' => ALT_CURRENCY_ITEM_ID
    ]);

    $existing = $stmt->fetch();

    if ($existing) {
        // Update existing record
        $stmt = $conn->prepare("
            UPDATE character_currency_alternate
            SET amount = amount + :amount
            WHERE char_id = :char_id AND currency_id = :altcurrency_id
        ");
        $stmt->execute([
            ':amount' => $amount,
            ':char_id' => $charId,
            ':altcurrency_id' => ALT_CURRENCY_ITEM_ID
        ]);
    } else {
        // Insert new record
        $stmt = $conn->prepare("
            INSERT INTO character_currency_alternate (char_id, currency_id, amount)
            VALUES (:char_id, :altcurrency_id, :amount)
        ");
        $stmt->execute([
            ':char_id' => $charId,
            ':altcurrency_id' => ALT_CURRENCY_ITEM_ID,
            ':amount' => $amount
        ]);
    }

    return true;
}

/**
 * Convert earnings to alt currency and platinum
 * For amounts over 1M platinum, convert millions to alt currency and remainder to platinum
 * @param int $totalCopper Total earnings in copper
 * @return array ['alt_currency' => int, 'platinum_remainder' => int]
 */
function convertEarningsToAltCurrency($totalCopper) {
    $totalPlatinum = $totalCopper / 1000;

    // If alternate currency is disabled, return all as platinum
    if (!USE_ALT_CURRENCY) {
        return [
            'alt_currency' => 0,
            'platinum_remainder' => $totalPlatinum,
            'copper_remainder' => $totalCopper
        ];
    }

    if ($totalPlatinum <= ALT_CURRENCY_VALUE_PLATINUM) {
        return [
            'alt_currency' => 0,
            'platinum_remainder' => $totalPlatinum,
            'copper_remainder' => $totalCopper
        ];
    }

    // Calculate alt currency (millions/billions)
    $altCurrency = floor($totalPlatinum / ALT_CURRENCY_VALUE_PLATINUM);
    $platinumRemainder = $totalPlatinum - ($altCurrency * ALT_CURRENCY_VALUE_PLATINUM);
    $copperRemainder = $platinumRemainder * 1000;

    return [
        'alt_currency' => $altCurrency,
        'platinum_remainder' => $platinumRemainder,
        'copper_remainder' => $copperRemainder
    ];
}

// ============================================================================
// Rate Limiting Class (File-based)
// ============================================================================
/**
 * Simple file-based rate limiter to prevent API abuse
 * Usage: RateLimiter::check('login', 5, 60); // 5 attempts per 60 seconds
 */
class RateLimiter {
    private static $cacheDir = null;

    /**
     * Initialize cache directory
     */
    private static function init() {
        if (self::$cacheDir === null) {
            self::$cacheDir = sys_get_temp_dir() . '/eqemu_rate_limit';
            if (!is_dir(self::$cacheDir)) {
                mkdir(self::$cacheDir, 0700, true);
            }
        }
    }

    /**
     * Check if rate limit is exceeded
     * @param string $identifier Unique identifier (e.g., IP address, user ID)
     * @param int $maxRequests Maximum requests allowed
     * @param int $windowSeconds Time window in seconds
     * @return bool True if limit exceeded, false otherwise
     */
    public static function check($identifier, $maxRequests = 60, $windowSeconds = 60) {
        self::init();

        $key = md5($identifier);
        $filepath = self::$cacheDir . '/' . $key . '.json';

        $now = time();
        $requests = [];

        // Load existing requests
        if (file_exists($filepath)) {
            $data = json_decode(file_get_contents($filepath), true);
            if ($data && isset($data['requests'])) {
                $requests = $data['requests'];
            }
        }

        // Filter out old requests outside the time window
        $requests = array_filter($requests, function($timestamp) use ($now, $windowSeconds) {
            return ($now - $timestamp) < $windowSeconds;
        });

        // Check if limit exceeded
        if (count($requests) >= $maxRequests) {
            return true; // Rate limit exceeded
        }

        // Add current request
        $requests[] = $now;

        // Save updated requests
        file_put_contents($filepath, json_encode(['requests' => $requests]));

        return false; // Within rate limit
    }

    /**
     * Get identifier from request (IP + User Agent)
     * @return string Unique identifier
     */
    public static function getIdentifier() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return $ip . ':' . md5($userAgent);
    }

    /**
     * Clean up old rate limit files (call periodically)
     */
    public static function cleanup() {
        self::init();

        $files = glob(self::$cacheDir . '/*.json');
        $now = time();

        foreach ($files as $file) {
            if (($now - filemtime($file)) > 3600) { // 1 hour old
                unlink($file);
            }
        }
    }
}
?>
