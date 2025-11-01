<?php
/**
 * Quick Password Hash Fix
 * 
 * Directly fixes the admin password hash in the database
 * Run this once: https://kdsc.fun/worshipTeam/admin/fix_password_hash.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
use QuizGame\Database;

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إصلاح الهاش</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            direction: rtl;
            max-width: 600px;
            margin: 0 auto;
        }
        .success { 
            color: green; 
            padding: 15px; 
            background: #d4edda; 
            margin: 20px 0; 
            border-radius: 5px; 
            border: 2px solid #28a745;
        }
        .error { 
            color: red; 
            padding: 15px; 
            background: #fee; 
            margin: 20px 0; 
            border-radius: 5px; 
            border: 2px solid #dc3545;
        }
        .info { 
            color: blue; 
            padding: 15px; 
            background: #e7f3ff; 
            margin: 20px 0; 
            border-radius: 5px; 
            border: 2px solid #007bff;
        }
        button {
            background: #28a745;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 0;
        }
        button:hover {
            background: #218838;
        }
        pre {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <h1>🔧 إصلاح هاش كلمة المرور</h1>

    <?php
    try {
        $db = Database::getInstance();
        
        // Check if admins table exists
        $tableCheck = $db->fetchOne("SHOW TABLES LIKE 'admins'");
        if (!$tableCheck) {
            echo "<div class='error'>❌ جدول admins غير موجود! يرجى تشغيل admin_setup.sql أولاً.</div>";
            exit;
        }
        
        // Get current admin
        $admin = $db->fetchOne("SELECT id, username, password_hash FROM admins WHERE username = 'admin'");
        
        if (!$admin) {
            echo "<div class='error'>❌ المستخدم 'admin' غير موجود!</div>";
            exit;
        }
        
        echo "<div class='info'>";
        echo "<strong>المستخدم الحالي:</strong><br>";
        echo "ID: " . htmlspecialchars($admin['id']) . "<br>";
        echo "Username: " . htmlspecialchars($admin['username']) . "<br>";
        echo "Hash Length: " . strlen($admin['password_hash']) . " characters<br>";
        echo "</div>";
        
        // Generate new hash
        $newPassword = 'admin123';
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update in database
        $db->query(
            "UPDATE admins SET password_hash = ? WHERE username = ?",
            [$newHash, 'admin']
        );
        
        // Verify it worked
        $updated = $db->fetchOne("SELECT password_hash FROM admins WHERE username = 'admin'");
        if ($updated && password_verify($newPassword, $updated['password_hash'])) {
            echo "<div class='success'>";
            echo "✅ <strong>تم إصلاح الهاش بنجاح!</strong><br><br>";
            echo "اسم المستخدم: <strong>admin</strong><br>";
            echo "كلمة المرور: <strong>admin123</strong><br><br>";
            echo "يمكنك الآن تسجيل الدخول بنجاح!";
            echo "</div>";
            
            echo "<div class='info'>";
            echo "<strong>الخطوات التالية:</strong><br>";
            echo "1. احذف هذا الملف بعد الاستخدام<br>";
            echo "2. <a href='login.html' style='color: blue;'>انتقل إلى صفحة تسجيل الدخول</a>";
            echo "</div>";
        } else {
            echo "<div class='error'>❌ فشل التحقق من الهاش بعد التحديث!</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>";
        echo "❌ <strong>خطأ:</strong><br>";
        echo htmlspecialchars($e->getMessage());
        echo "</div>";
        
        echo "<div class='info'>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        echo "</div>";
    }
    ?>
    
    <hr>
    <p style="text-align: center;">
        <a href="login.html">← العودة إلى تسجيل الدخول</a>
    </p>
</body>
</html>

