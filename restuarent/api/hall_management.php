<?php
/**
 * Hall Management API
 * Handles CRUD operations for halls table
 * 
 * CREATE: POST with { name, capacity, terminal, branch_id } (hall_id is empty or not provided)
 * UPDATE: POST with { hall_id, name, capacity, terminal, branch_id }
 * DELETE: DELETE with { hall_id }
 * 
 * Database Schema:
 * - hall_id (int, AUTO_INCREMENT, PRIMARY KEY)
 * - name (varchar(255), NOT NULL)
 * - capacity (int, DEFAULT 0)
 * - terminal (int, NOT NULL, DEFAULT 0)
 * - branch_id (int, NOT NULL, FOREIGN KEY to branches)
 * - created_at (timestamp, DEFAULT CURRENT_TIMESTAMP)
 * - updated_at (timestamp, DEFAULT CURRENT_TIMESTAMP)
 */

// Include CORS headers
require_once 'cors_headers.php';

// Include config for database constants
include("config.php");

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST; // Fallback to form data
}

// Database connection using config constants
$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$dbname = defined('DB_NAME') ? DB_NAME : 'chai-khas';
$username = defined('DB_USER') ? DB_USER : 'root';
$password = defined('DB_PASS') ? DB_PASS : '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($method === 'DELETE') {
        // ============================================
        // DELETE HALL
        // ============================================
        $hall_id = isset($input['hall_id']) ? intval($input['hall_id']) : 0;
        
        if ($hall_id <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Hall ID is required for deletion'
            ]);
            exit();
        }
        
        // Check if hall exists
        $checkStmt = $pdo->prepare("SELECT hall_id FROM halls WHERE hall_id = :hall_id");
        $checkStmt->execute(['hall_id' => $hall_id]);
        if ($checkStmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Hall not found'
            ]);
            exit();
        }
        
        // Check if hall has tables assigned
        $tablesStmt = $pdo->prepare("SELECT COUNT(*) as table_count FROM tables WHERE hall_id = :hall_id");
        $tablesStmt->execute(['hall_id' => $hall_id]);
        $tablesResult = $tablesStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tablesResult && $tablesResult['table_count'] > 0) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'Cannot delete hall. It has ' . $tablesResult['table_count'] . ' table(s) assigned. Please reassign or delete tables first.'
            ]);
            exit();
        }
        
        // Delete hall
        $deleteStmt = $pdo->prepare("DELETE FROM halls WHERE hall_id = :hall_id");
        $deleteStmt->execute(['hall_id' => $hall_id]);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Hall deleted successfully'
        ]);
        
    } else if ($method === 'POST') {
        // ============================================
        // CREATE OR UPDATE HALL
        // ============================================
        $hall_id = isset($input['hall_id']) && $input['hall_id'] !== '' ? intval($input['hall_id']) : 0;
        $name = isset($input['name']) ? trim($input['name']) : '';
        $capacity = isset($input['capacity']) ? intval($input['capacity']) : 0;
        $terminal = isset($input['terminal']) ? intval($input['terminal']) : 0;
        $branch_id_input = isset($input['branch_id']) ? $input['branch_id'] : null;
        $branch_id = null;
        
        // Validate required fields
        if (empty($name)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Hall name is required'
            ]);
            exit();
        }
        
        if ($terminal <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Terminal is required'
            ]);
            exit();
        }
        
        // Validate branch_id
        if ($branch_id_input !== null && $branch_id_input !== '' && $branch_id_input !== 'null' && $branch_id_input !== 'undefined') {
            $branch_id = intval($branch_id_input);
            if ($branch_id <= 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid branch_id. Must be a positive integer.'
                ]);
                exit();
            }
            
            // Validate branch exists
            $check_branch_stmt = $pdo->prepare("SELECT branch_id FROM branches WHERE branch_id = :branch_id");
            $check_branch_stmt->execute(['branch_id' => $branch_id]);
            if ($check_branch_stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Branch not found with ID: ' . $branch_id
                ]);
                exit();
            }
        }
        
        if ($hall_id > 0) {
            // ============================================
            // UPDATE EXISTING HALL
            // ============================================
            // Check if hall exists and get current branch_id
            $checkStmt = $pdo->prepare("SELECT hall_id, branch_id FROM halls WHERE hall_id = :hall_id");
            $checkStmt->execute(['hall_id' => $hall_id]);
            $existing_hall = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existing_hall) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Hall not found'
                ]);
                exit();
            }
            
            // If branch_id not provided in update, use existing branch_id (prevent branch-admin from changing it)
            if ($branch_id === null) {
                $branch_id = intval($existing_hall['branch_id']);
            }
            
            // Check for duplicate name in same branch and terminal (excluding current hall)
            $checkStmt = $pdo->prepare("SELECT hall_id FROM halls WHERE name = :name AND branch_id = :branch_id AND terminal = :terminal AND hall_id != :hall_id");
            $checkStmt->execute([
                'name' => $name, 
                'branch_id' => $branch_id,
                'terminal' => $terminal, 
                'hall_id' => $hall_id
            ]);
            if ($checkStmt->rowCount() > 0) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'Hall name already exists for this branch and terminal'
                ]);
                exit();
            }
            
            // Update hall
            $updateStmt = $pdo->prepare("
                UPDATE halls 
                SET name = :name, 
                    capacity = :capacity,
                    branch_id = :branch_id,
                    updated_at = CURRENT_TIMESTAMP
                WHERE hall_id = :hall_id
            ");
            $updateStmt->execute([
                'hall_id' => $hall_id,
                'name' => $name,
                'capacity' => $capacity,
                'branch_id' => $branch_id
            ]);
            
            // Fetch updated hall with branch info
            $getStmt = $pdo->prepare("
                SELECT h.*, 
                COALESCE(b.branch_name, CONCAT('Branch ', h.branch_id)) AS branch_name
                FROM halls h
                LEFT JOIN branches b ON h.branch_id = b.branch_id
                WHERE h.hall_id = :hall_id
            ");
            $getStmt->execute(['hall_id' => $hall_id]);
            $updated_hall = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            // Normalize branch_name
            if (!$updated_hall['branch_name'] || empty($updated_hall['branch_name'])) {
                $updated_hall['branch_name'] = $branch_id ? 'Branch ' . $branch_id : 'No Branch';
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Hall updated successfully',
                'data' => $updated_hall
            ]);
            
        } else {
            // ============================================
            // CREATE NEW HALL
            // ============================================
            // REQUIRE branch_id for CREATE
            if ($branch_id === null) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'branch_id is required for creating a new hall'
                ]);
                exit();
            }
            
            // Check for duplicate name in same branch and terminal
            $checkStmt = $pdo->prepare("SELECT hall_id FROM halls WHERE name = :name AND branch_id = :branch_id AND terminal = :terminal");
            $checkStmt->execute([
                'name' => $name, 
                'branch_id' => $branch_id,
                'terminal' => $terminal
            ]);
            if ($checkStmt->rowCount() > 0) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'Hall name already exists for this branch and terminal'
                ]);
                exit();
            }
            
            // Insert new hall with branch_id
            $insertStmt = $pdo->prepare("
                INSERT INTO halls (name, capacity, terminal, branch_id, created_at, updated_at)
                VALUES (:name, :capacity, :terminal, :branch_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $insertStmt->execute([
                'name' => $name,
                'capacity' => $capacity,
                'terminal' => $terminal,
                'branch_id' => $branch_id
            ]);
            
            $newId = $pdo->lastInsertId();
            
            // Fetch created hall with branch info
            $getStmt = $pdo->prepare("
                SELECT h.*, 
                COALESCE(b.branch_name, CONCAT('Branch ', h.branch_id)) AS branch_name
                FROM halls h
                LEFT JOIN branches b ON h.branch_id = b.branch_id
                WHERE h.hall_id = :hall_id
            ");
            $getStmt->execute(['hall_id' => $newId]);
            $created_hall = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            // Normalize branch_name
            if (!$created_hall['branch_name'] || empty($created_hall['branch_name'])) {
                $created_hall['branch_name'] = $branch_id ? 'Branch ' . $branch_id : 'No Branch';
            }
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Hall added successfully',
                'data' => $created_hall
            ]);
        }
        
    } else {
        // Method not allowed
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed. Use POST or DELETE.'
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

