<?php
require_once('../lib/wejv.php');

// Set header to return JSON response
header('Content-Type: application/json');

// Get request body
$request = json_decode(file_get_contents('php://input'), true);
$action = isset($request['action']) ? $request['action'] : '';

// Initialize Wejv class with database credentials
try {
    $wejv = new Wejv('../db_creds.json');
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection error'
    ]);
    exit;
}

// Handle different actions
switch ($action) {
    case 'login':
        handleLogin($wejv, $request);
        break;
    case 'register':
        handleRegister($wejv, $request);
        break;
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
}

/**
 * Handle user login
 *
 * @param Wejv $wejv Wejv instance
 * @param array $data Request data
 */
function handleLogin($wejv, $data) {
    // Validate required fields
    if (empty($data['username']) || empty($data['password'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Username and password are required'
        ]);
        return;
    }

    // Attempt login
    $userId = $wejv->login($data['username'], $data['password']);

    if ($userId) {
        // Login successful, get user info
        $userInfo = $wejv->fetchUserInfo($userId);

        if ($userInfo) {
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Store user ID in session
            $_SESSION['user_id'] = $userId;

            // Return success with user info
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => $userInfo
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error retrieving user information'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid username or password'
        ]);
    }
}

/**
 * Handle user registration
 *
 * @param Wejv $wejv Wejv instance
 * @param array $data Request data
 */
function handleRegister($wejv, $data) {
    // Validate required fields
    $requiredFields = ['username', 'email', 'password', 'first_name', 'last_name'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            echo json_encode([
                'success' => false,
                'message' => 'All fields are required'
            ]);
            return;
        }
    }

    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format'
        ]);
        return;
    }

    // Attempt registration
    try {
        $result = $wejv->register(
            $data['username'],
            $data['email'],
            $data['password'],
            $data['first_name'],
            $data['last_name']
        );

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Registration successful'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Registration failed. The username or email may already be in use.'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred during registration: ' . $e->getMessage()
        ]);
    }
}
