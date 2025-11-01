<?php
/**
 * Admin Login Endpoint - Standalone
 * 
 * Direct endpoint for admin login that works without .htaccess routing
 */

// Set session cookie parameters BEFORE starting session
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

use QuizGame\Database;

try {
    $db = Database::getInstance();
    
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    // Check for JSON decode errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid JSON: ' . json_last_error_msg()
        ]);
        exit;
    }
    
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    if (!$username || !$password) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'اسم المستخدم وكلمة المرور مطلوبان']);
        exit;
    }

    // Check if admins table exists
    try {
        $tableCheck = $db->fetchOne("SHOW TABLES LIKE 'admins'");
        if (!$tableCheck) {
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'error' => 'جدول المشرفين غير موجود. يرجى تشغيل admin_setup.sql أولاً.'
            ]);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'خطأ في الاتصال بقاعدة البيانات: ' . $e->getMessage()
        ]);
        exit;
    }

    // Get admin from database
    try {
        $admin = $db->fetchOne(
            "SELECT id, username, password_hash FROM admins WHERE username = ?",
            [$username]
        );
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'خطأ في قاعدة البيانات: ' . $e->getMessage()
        ]);
        exit;
    }

    if (!$admin) {
        // Auto-create admin user if it doesn't exist (for first-time setup)
        if ($username === 'admin' && $password === 'admin123') {
            try {
                // Create default admin with correct hash
                $defaultHash = password_hash('admin123', PASSWORD_DEFAULT);
                $db->query(
                    "INSERT INTO admins (username, password_hash, email) VALUES (?, ?, ?)",
                    ['admin', $defaultHash, 'moody.gindy@gmail.com']
                );
                
                // Retry login
                $admin = $db->fetchOne(
                    "SELECT id, username, password_hash FROM admins WHERE username = ?",
                    [$username]
                );
                
                if ($admin) {
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'تم إنشاء المستخدم المشرف تلقائياً',
                        'token' => session_id(),
                        'username' => $admin['username']
                    ]);
                    exit;
                }
            } catch (Exception $createError) {
                // Fall through to error message below
            }
        }
        
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'اسم المستخدم أو كلمة المرور غير صحيح']);
        exit;
    }

    // Check if password_hash is valid - fix if corrupted
    if (empty($admin['password_hash']) || strlen($admin['password_hash']) < 10) {
        // Auto-fix corrupted hash for default admin
        if ($username === 'admin' && $password === 'admin123') {
            try {
                $newHash = password_hash('admin123', PASSWORD_DEFAULT);
                $db->query(
                    "UPDATE admins SET password_hash = ? WHERE id = ?",
                    [$newHash, $admin['id']]
                );
                $admin['password_hash'] = $newHash;
            } catch (Exception $fixError) {
                http_response_code(500);
                echo json_encode([
                    'success' => false, 
                    'error' => 'خطأ في إصلاح كلمة المرور: ' . $fixError->getMessage()
                ]);
                exit;
            }
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'error' => 'كلمة المرور غير صحيحة في قاعدة البيانات. يرجى استخدام reset_admin_password.php لإصلاحها.'
            ]);
            exit;
        }
    }

    // Verify password
    if (!password_verify($password, $admin['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'اسم المستخدم أو كلمة المرور غير صحيح']);
        exit;
    }

    // Store admin info in session
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];

    echo json_encode([
        'success' => true,
        'token' => session_id(),
        'username' => $admin['username']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطأ في الخادم: ' . $e->getMessage()
    ]);
}
