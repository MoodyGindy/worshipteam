<?php
/**
 * Password Hash Generator
 * 
 * Use this script to generate password hashes
 * Run: https://kdsc.fun/worshipTeam/admin/generate_password_hash.php
 */

$password = $_GET['password'] ?? $_POST['password'] ?? 'admin123';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مولد هاش كلمة المرور</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
            max-width: 600px;
        }
        h1 { color: #333; margin-bottom: 20px; text-align: center; }
        .form-group { margin-bottom: 20px; }
        label { display: block; color: #333; margin-bottom: 8px; font-weight: 600; }
        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            font-family: 'Cairo', sans-serif;
        }
        input:focus { outline: none; border-color: #667eea; }
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
        }
        .result {
            background: #f0f0f0;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            word-break: break-all;
        }
        .result strong { color: #667eea; }
        .sql-code {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔑 مولد هاش كلمة المرور</h1>
        
        <form method="POST">
            <div class="form-group">
                <label for="password">كلمة المرور</label>
                <input type="text" id="password" name="password" value="<?php echo htmlspecialchars($password); ?>" required>
            </div>
            <button type="submit">إنشاء الهاش</button>
        </form>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['password'])): ?>
            <?php
            $hash = password_hash($password, PASSWORD_DEFAULT);
            ?>
            <div class="result">
                <strong>الهاش:</strong><br>
                <code style="font-size: 0.9rem; color: #667eea;"><?php echo $hash; ?></code>
            </div>

            <div class="sql-code">
                <strong style="color: #ffd700;">SQL Command:</strong><br><br>
UPDATE admins <br>
SET password_hash = '<?php echo $hash; ?>'<br>
WHERE username = 'admin';
            </div>

            <div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-radius: 10px; font-size: 0.9rem;">
                <strong>خطوات الاستخدام:</strong><br>
                1. انسخ كود SQL أعلاه<br>
                2. الصقه في phpMyAdmin أو MySQL<br>
                3. نفذ الاستعلام<br>
                4. سجل الدخول بكلمة المرور: <strong><?php echo htmlspecialchars($password); ?></strong>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

