<?php
/**
 * Admin Password Reset Tool
 * 
 * This script allows you to reset the admin password directly
 * Run this file in your browser: https://kdsc.fun/worshipTeam/admin/reset_admin_password.php
 * 
 * SECURITY: Delete this file after use in production!
 */

require_once __DIR__ . '/../vendor/autoload.php';
use QuizGame\Database;

// Simple password reset form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $username = $_POST['username'] ?? 'admin';
    $newPassword = $_POST['password'] ?? '';
    
    if (empty($newPassword)) {
        $error = 'كلمة المرور مطلوبة';
    } elseif (strlen($newPassword) < 6) {
        $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    } else {
        // Hash the password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update in database
        try {
            $result = $db->query(
                "UPDATE admins SET password_hash = ? WHERE username = ?",
                [$passwordHash, $username]
            );
            
            $success = "تم تحديث كلمة المرور بنجاح!<br>";
            $success .= "اسم المستخدم: <strong>$username</strong><br>";
            $success .= "كلمة المرور الجديدة: <strong>$newPassword</strong><br><br>";
            $success .= "<small style='color: red;'>⚠️ احذف هذا الملف بعد الاستخدام!</small>";
        } catch (Exception $e) {
            $error = 'خطأ: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة مرور المشرف</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            width: 100%;
            max-width: 500px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        .warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #856404;
            font-size: 0.9rem;
        }
        .success {
            background: #d4edda;
            border: 2px solid #28a745;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #155724;
        }
        .error {
            background: #fee;
            border: 2px solid #dc3545;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #721c24;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            color: #333;
            margin-bottom: 8px;
            font-weight: 600;
        }
        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            font-family: 'Cairo', sans-serif;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            font-family: 'Cairo', sans-serif;
            cursor: pointer;
            transition: transform 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
        }
        .info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #004085;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 إعادة تعيين كلمة المرور</h1>
        
        <div class="warning">
            ⚠️ <strong>تنبيه أمني:</strong> احذف هذا الملف بعد الاستخدام في بيئة الإنتاج!
        </div>

        <?php if (isset($success)): ?>
            <div class="success">
                <?php echo $success; ?>
            </div>
            <a href="login.html" style="display: block; text-align: center; margin-top: 20px; color: #667eea; text-decoration: none; font-weight: 600;">← العودة إلى تسجيل الدخول</a>
        <?php else: ?>
            <?php if (isset($error)): ?>
                <div class="error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="username">اسم المستخدم</label>
                    <input type="text" id="username" name="username" value="admin" required>
                </div>

                <div class="form-group">
                    <label for="password">كلمة المرور الجديدة</label>
                    <input type="password" id="password" name="password" required minlength="6" placeholder="أدخل كلمة مرور جديدة">
                </div>

                <button type="submit">تحديث كلمة المرور</button>
            </form>

            <div class="info">
                <strong>معلومات:</strong><br>
                • سيتم تحديث كلمة المرور مباشرة في قاعدة البيانات<br>
                • يمكنك استخدام كلمة المرور الجديدة فوراً<br>
                • تأكد من حذف هذا الملف بعد الاستخدام
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

