<?php
/**
 * Database Configuration
 * Auto-detects local (WAMP) vs live environment cleanly and securely
 */

// Safe server variable extraction (avoids PHP Undefined index notices in CLI or proxy setups)
$http_host = $_SERVER['HTTP_HOST'] ?? '';
$server_name = $_SERVER['SERVER_NAME'] ?? '';

// Auto-detect environment
$is_local = (
    $http_host === 'localhost' || 
    $http_host === '127.0.0.1' ||
    strpos($http_host, 'localhost') !== false ||
    strpos($http_host, '127.0.0.1') !== false ||
    strpos($server_name, 'localhost') !== false
);

if (!defined('DB_HOST')) {
    if ($is_local) {
        define('DB_HOST', 'localhost');
        define('DB_USER', 'root'); 
        define('DB_PASS', ''); 
        define('DB_NAME', 'restuarent'); 
    } else {
        define('DB_HOST', '187.77.121.23:3306'); 
        define('DB_USER', 'restdb'); 
        define('DB_PASS', 'DildilPakistan786_786_waqas'); 
        define('DB_NAME', 'restuser'); 
    }
}

// Global connection holders (Starts as null, populated ONLY when needed)
$conn = null;
$connection = null;
$GLOBALS['db_connection_error'] = null;

/**
 * Lazy-load Object-Oriented Connection ($conn)
 */
if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        global $conn;
        
        // If connection already exists and is valid, return it
        if ($conn instanceof mysqli && !@$conn->connect_error) {
            return $conn;
        }

        $hosts_to_try = [DB_HOST];
        if (DB_HOST !== 'localhost') {
            $hosts_to_try[] = 'localhost';
            $hosts_to_try[] = '127.0.0.1';
        }

        $last_err = '';
        foreach ($hosts_to_try as $h) {
            $port = 3306;
            $host_only = $h;

            if (strpos($h, ':') !== false) {
                list($h_name, $h_port) = explode(':', $h, 2);
                $host_only = $h_name;
                $port = (int)$h_port;
            }

            try {
                $test_conn = ($host_only === 'localhost') 
                    ? @new mysqli('localhost', DB_USER, DB_PASS, DB_NAME)
                    : @new mysqli($host_only, DB_USER, DB_PASS, DB_NAME, $port);

                if (!$test_conn->connect_error) {
                    $test_conn->set_charset("utf8mb4");
                    $conn = $test_conn;
                    return $conn;
                } else {
                    $last_err = "Host {$h}: " . $test_conn->connect_error;
                }
            } catch (Throwable $e) {
                $last_err = "Host {$h}: " . $e->getMessage();
            }
        }

        $conn = null;
        throw new Exception("Database connection error: " . $last_err);
    }
}

/**
 * Lazy-load Procedural Connection ($connection)
 */
if (!function_exists('getProceduralConnection')) {
    function getProceduralConnection() {
        global $connection;

        if ($connection && @mysqli_ping($connection)) {
            return $connection;
        }

        try {
            $connection = getDBConnection();
            return $connection;
        } catch (Throwable $e) {
            $connection = null;
            return null;
        }
    }
}

// --- BACKWARD COMPATIBILITY BRIDGE & AUTOMATIC FAILURE RESPONSE ---
try {
    $conn = getDBConnection();
    $connection = $conn; 
} catch (Throwable $e) {
    $conn = null;
    $connection = null;
    $err_msg = $e->getMessage();
    $GLOBALS['db_connection_error'] = $err_msg;

    // Clear output buffer if any
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code(500);
    }
    
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed: " . $err_msg
    ]);
    exit();
}

/**
 * Generate a unique token
 */
if (!function_exists('generateToken')) {
    function generateToken() {
        return bin2hex(random_bytes(32)); 
    }
}
?>