<?php
/**
 * Database Connection Test
 * 
 * Test database connection and show detailed information
 * https://kdsc.fun/worshipTeam/test_db_connection.php
 */

require_once __DIR__ . '/vendor/autoload.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>اختبار الاتصال بقاعدة البيانات</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            direction: rtl;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 800px;
            margin: 0 auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { color: green; padding: 15px; background: #d4edda; margin: 10px 0; border-radius: 5px; }
        .error { color: red; padding: 15px; background: #fee; margin: 10px 0; border-radius: 5px; }
        .info { color: blue; padding: 15px; background: #e7f3ff; margin: 10px 0; border-radius: 5px; }
        .warning { color: orange; padding: 15px; background: #fff3cd; margin: 10px 0; border-radius: 5px; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 0.9em; }
        h2 { margin-top: 30px; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 اختبار الاتصال بقاعدة البيانات</h1>

        <?php
        // Load config
        $config = require __DIR__ . '/config/database.php';
        
        echo "<h2>1. إعدادات قاعدة البيانات</h2>";
        echo "<div class='info'>";
        echo "<strong>Host:</strong> " . htmlspecialchars($config['host'] ?? 'غير محدد') . "<br>";
        echo "<strong>Port:</strong> " . htmlspecialchars($config['port'] ?? 'غير محدد') . "<br>";
        echo "<strong>Database:</strong> " . htmlspecialchars($config['database'] ?? 'غير محدد') . "<br>";
        echo "<strong>Username:</strong> " . htmlspecialchars($config['username'] ?? 'غير محدد') . "<br>";
        echo "<strong>Charset:</strong> " . htmlspecialchars($config['charset'] ?? 'غير محدد') . "<br>";
        echo "</div>";

        // Test connection
        echo "<h2>2. اختبار الاتصال</h2>";
        try {
            $host = $config['host'] ?? 'localhost';
            $port = $config['port'] ?? 3306;
            $dbname = $config['database'];
            $username = $config['username'];
            $password = $config['password'];
            
            // Convert localhost to 127.0.0.1 for MAMP
            $tcpHost = ($host === 'localhost') ? '127.0.0.1' : $host;
            $dsn = "mysql:host={$tcpHost};port={$port};dbname={$dbname};charset=utf8mb4";
            
            echo "<div class='info'>";
            echo "جاري الاتصال بـ: <code>{$tcpHost}:{$port}</code><br>";
            echo "قاعدة البيانات: <code>{$dbname}</code><br>";
            echo "</div>";
            
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            
            echo "<div class='success'>";
            echo "✅ <strong>نجح الاتصال!</strong><br>";
            echo "تم الاتصال بنجاح بقاعدة البيانات.";
            echo "</div>";
            
            // Test query
            echo "<h2>3. اختبار الاستعلامات</h2>";
            try {
                $stmt = $pdo->query("SELECT DATABASE() as current_db, VERSION() as mysql_version");
                $info = $stmt->fetch();
                
                echo "<div class='success'>";
                echo "✅ الاستعلامات تعمل بشكل صحيح<br>";
                echo "<strong>قاعدة البيانات الحالية:</strong> " . htmlspecialchars($info['current_db']) . "<br>";
                echo "<strong>إصدار MySQL:</strong> " . htmlspecialchars($info['mysql_version']) . "<br>";
                echo "</div>";
                
                // Check if admins table exists
                echo "<h2>4. فحص الجداول</h2>";
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                
                if (in_array('admins', $tables)) {
                    echo "<div class='success'>";
                    echo "✅ جدول <code>admins</code> موجود<br>";
                    echo "</div>";
                    
                    // Count admins
                    $adminCount = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
                    echo "<div class='info'>";
                    echo "عدد المستخدمين المشرفين: <strong>{$adminCount}</strong><br>";
                    echo "</div>";
                } else {
                    echo "<div class='warning'>";
                    echo "⚠️ جدول <code>admins</code> غير موجود<br>";
                    echo "يرجى تشغيل <code>database/admin_setup.sql</code>";
                    echo "</div>";
                }
                
                if (count($tables) > 0) {
                    echo "<div class='info'>";
                    echo "<strong>الجداول الموجودة:</strong><br>";
                    echo "<pre>" . htmlspecialchars(implode("\n", $tables)) . "</pre>";
                    echo "</div>";
                }
                
            } catch (PDOException $e) {
                echo "<div class='error'>";
                echo "❌ خطأ في الاستعلام: " . htmlspecialchars($e->getMessage());
                echo "</div>";
            }
            
        } catch (PDOException $e) {
            echo "<div class='error'>";
            echo "❌ <strong>فشل الاتصال!</strong><br>";
            echo "الخطأ: " . htmlspecialchars($e->getMessage()) . "<br><br>";
            
            echo "<strong>خطوات الإصلاح:</strong><br>";
            echo "<ol>";
            echo "<li>تأكد من أن MAMP يعمل (افتح MAMP وتأكد من أن MySQL يعمل)</li>";
            echo "<li>تحقق من المنفذ في MAMP (افتراضي: 8889)</li>";
            echo "<li>تحقق من بيانات الاعتماد في <code>config/database.php</code></li>";
            echo "<li>تأكد من وجود قاعدة البيانات: <code>" . htmlspecialchars($config['database']) . "</code></li>";
            echo "</ol>";
            echo "</div>";
            
            // Additional troubleshooting
            echo "<h2>5. معلومات إضافية</h2>";
            echo "<div class='warning'>";
            echo "<strong>كود الخطأ:</strong> " . htmlspecialchars($e->getCode()) . "<br>";
            echo "<strong>ملف:</strong> " . htmlspecialchars($e->getFile()) . "<br>";
            echo "<strong>السطر:</strong> " . htmlspecialchars($e->getLine()) . "<br>";
            echo "</div>";
        }
        ?>

        <hr>
        <p style="text-align: center; margin-top: 30px;">
            <a href="admin/login.html">← العودة إلى تسجيل الدخول</a> | 
            <a href="admin/test_admin_setup.php">اختبار إعداد المشرف</a>
        </p>
    </div>
</body>
</html>

