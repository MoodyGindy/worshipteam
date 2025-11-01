<?php
/**
 * Create/Update Admin User
 * 
 * This script ensures the admin user is created with correct password hash
 * Run: http://localhost:8888/worshipTeam/admin/create_admin.php
 * 
 * SECURITY: Delete this file after use!
 */

require_once __DIR__ . '/../vendor/autoload.php';
use QuizGame\Database;

$db = Database::getInstance();
$username = 'admin';
$password = 'admin123';
$email = 'moody.gindy@gmail.com';

// Generate correct password hash
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Check if admin exists
$existing = $db->fetchOne("SELECT id FROM admins WHERE username = ?", [$username]);

if ($existing) {
    // Update existing admin
    $db->query(
        "UPDATE admins SET password_hash = ?, email = ? WHERE username = ?",
        [$passwordHash, $email, $username]
    );
    $action = "تم تحديث";
} else {
    // Create new admin
    $db->query(
        "INSERT INTO admins (username, password_hash, email) VALUES (?, ?, ?)",
        [$username, $passwordHash, $email]
    );
    $action = "تم إنشاء";
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إنشاء/تحديث المشرف</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
            direction: rtl;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-width: 600px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 1.1em;
        }
        .info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
        }
        pre {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 0.9em;
        }
        a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✅ <?php echo $action; ?> المستخدم المشرف</h1>
        
        <div class="success">
            <strong>✅ نجح!</strong><br>
            <?php echo $action; ?> المستخدم المشرف بنجاح.
        </div>

        <div class="info">
            <strong>بيانات تسجيل الدخول:</strong><br>
            اسم المستخدم: <strong><?php echo htmlspecialchars($username); ?></strong><br>
            كلمة المرور: <strong><?php echo htmlspecialchars($password); ?></strong><br>
            البريد الإلكتروني: <strong><?php echo htmlspecialchars($email); ?></strong>
        </div>

        <div class="info">
            <strong>الهاش المُنشأ:</strong><br>
            <pre><?php echo htmlspecialchars($passwordHash); ?></pre>
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <a href="login.html">← العودة إلى تسجيل الدخول</a>
        </div>

        <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 10px; color: #856404;">
            ⚠️ <strong>تحذير أمني:</strong> احذف هذا الملف (<code>create_admin.php</code>) بعد الاستخدام!
        </div>
    </div>
</body>
</html>

