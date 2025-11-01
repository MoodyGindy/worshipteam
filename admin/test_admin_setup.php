<?php
/**
 * Admin Setup Test Script
 * 
 * Run this to check if admin setup is correct
 * https://kdsc.fun/worshipTeam/admin/test_admin_setup.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
use QuizGame\Database;

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>اختبار إعداد المشرف</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            direction: rtl;
        }
        .success { color: green; padding: 10px; background: #d4edda; margin: 10px 0; border-radius: 5px; }
        .error { color: red; padding: 10px; background: #fee; margin: 10px 0; border-radius: 5px; }
        .info { color: blue; padding: 10px; background: #e7f3ff; margin: 10px 0; border-radius: 5px; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 اختبار إعداد المشرف</h1>

    <?php
    $errors = [];
    $warnings = [];
    $success = [];

    // Test 1: Database connection
    echo "<h2>1. اختبار الاتصال بقاعدة البيانات</h2>";
    try {
        $db = Database::getInstance();
        $db->getConnection();
        echo "<div class='success'>✅ الاتصال بقاعدة البيانات نجح</div>";
        $success[] = 'Database connection';
    } catch (Exception $e) {
        echo "<div class='error'>❌ خطأ في الاتصال: " . htmlspecialchars($e->getMessage()) . "</div>";
        $errors[] = 'Database connection: ' . $e->getMessage();
    }

    // Test 2: Check if admins table exists
    echo "<h2>2. فحص جدول المشرفين</h2>";
    try {
        $tableExists = $db->fetchOne("SHOW TABLES LIKE 'admins'");
        if ($tableExists) {
            echo "<div class='success'>✅ جدول admins موجود</div>";
            $success[] = 'Admins table exists';
        } else {
            echo "<div class='error'>❌ جدول admins غير موجود! يرجى تشغيل admin_setup.sql</div>";
            $errors[] = 'Admins table missing';
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ خطأ: " . htmlspecialchars($e->getMessage()) . "</div>";
        $errors[] = 'Table check: ' . $e->getMessage();
    }

    // Test 3: Check admin user
    if (isset($tableExists) && $tableExists) {
        echo "<h2>3. فحص المستخدم المشرف</h2>";
        try {
            $admin = $db->fetchOne("SELECT id, username, email, password_hash, 
                                          CHAR_LENGTH(password_hash) as hash_length 
                                   FROM admins WHERE username = 'admin'");
            
            if ($admin) {
                echo "<div class='success'>✅ المستخدم 'admin' موجود</div>";
                echo "<div class='info'>";
                echo "<strong>تفاصيل:</strong><br>";
                echo "ID: " . htmlspecialchars($admin['id']) . "<br>";
                echo "Username: " . htmlspecialchars($admin['username']) . "<br>";
                echo "Email: " . htmlspecialchars($admin['email'] ?? 'غير محدد') . "<br>";
                echo "Hash Length: " . htmlspecialchars($admin['hash_length']) . " characters<br>";
                
                // Check hash format
                $hash = $admin['password_hash'];
                if (strpos($hash, '$2y$') === 0 || strpos($hash, '$2a$') === 0) {
                    echo "✅ Hash format صحيح (bcrypt)<br>";
                    $success[] = 'Password hash format';
                } else {
                    echo "⚠️ Hash format قد يكون غير صحيح<br>";
                    $warnings[] = 'Password hash format issue';
                }
                echo "</div>";
                $success[] = 'Admin user exists';
            } else {
                echo "<div class='error'>❌ المستخدم 'admin' غير موجود! يرجى تشغيل admin_setup.sql</div>";
                $errors[] = 'Admin user missing';
            }
        } catch (Exception $e) {
            echo "<div class='error'>❌ خطأ: " . htmlspecialchars($e->getMessage()) . "</div>";
            $errors[] = 'Admin check: ' . $e->getMessage();
        }
    }

    // Test 4: Test password verification
    if (isset($admin) && $admin) {
        echo "<h2>4. اختبار التحقق من كلمة المرور</h2>";
        $testPassword = 'admin123';
        $hash = $admin['password_hash'];
        
        if (password_verify($testPassword, $hash)) {
            echo "<div class='success'>✅ كلمة المرور 'admin123' تعمل بشكل صحيح</div>";
            $success[] = 'Password verification';
        } else {
            echo "<div class='error'>❌ كلمة المرور 'admin123' لا تعمل! الهاش قد يكون تالفاً.</div>";
            echo "<div class='info'>";
            echo "<strong>الحل:</strong> استخدم <a href='reset_admin_password.php'>reset_admin_password.php</a> لإعادة تعيين كلمة المرور";
            echo "</div>";
            $errors[] = 'Password verification failed';
        }
    }

    // Test 5: Session test
    echo "<h2>5. اختبار الجلسات (Sessions)</h2>";
    session_start();
    $_SESSION['test'] = 'working';
    if (isset($_SESSION['test']) && $_SESSION['test'] === 'working') {
        echo "<div class='success'>✅ الجلسات تعمل بشكل صحيح</div>";
        echo "<div class='info'>Session ID: " . session_id() . "</div>";
        $success[] = 'Sessions working';
    } else {
        echo "<div class='error'>❌ الجلسات لا تعمل! تحقق من إعدادات PHP session</div>";
        $errors[] = 'Sessions not working';
    }

    // Summary
    echo "<h2>📊 الملخص</h2>";
    echo "<div class='info'>";
    echo "<strong>نجح:</strong> " . count($success) . " اختبار<br>";
    echo "<strong>تحذيرات:</strong> " . count($warnings) . "<br>";
    echo "<strong>أخطاء:</strong> " . count($errors) . "<br>";
    echo "</div>";

    if (count($errors) === 0) {
        echo "<div class='success' style='font-size: 1.2em; font-weight: bold;'>";
        echo "✅ كل شيء يعمل بشكل صحيح! يمكنك تسجيل الدخول الآن.";
        echo "</div>";
    } else {
        echo "<div class='error' style='font-size: 1.2em; font-weight: bold;'>";
        echo "❌ يوجد " . count($errors) . " خطأ يجب إصلاحه قبل تسجيل الدخول.";
        echo "</div>";
        
        if (in_array('Admins table missing', $errors) || in_array('Admin user missing', $errors)) {
            echo "<div class='info'>";
            echo "<strong>📋 خطوات الإصلاح:</strong><br>";
            echo "1. افتح phpMyAdmin<br>";
            echo "2. اختر قاعدة البيانات: <code>worshipteam</code><br>";
            echo "3. استورد ملف: <code>database/admin_setup.sql</code><br>";
            echo "4. أو شغل: <code>mysql -u root -p worshipteam < database/admin_setup.sql</code><br>";
            echo "</div>";
        }
    }
    ?>

    <hr>
    <p>
        <a href="login.html">← العودة إلى تسجيل الدخول</a> | 
        <a href="reset_admin_password.php">إعادة تعيين كلمة المرور</a>
    </p>
</body>
</html>

