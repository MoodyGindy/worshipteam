<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Check - Quiz Game</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .check-item {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .status {
            padding: 5px 15px;
            border-radius: 3px;
            font-weight: bold;
        }
        .success {
            background: #48bb78;
            color: white;
        }
        .error {
            background: #fc8181;
            color: white;
        }
        .warning {
            background: #f6ad55;
            color: white;
        }
        h1 {
            color: #333;
        }
        .info {
            background: #e6f7ff;
            padding: 15px;
            border-left: 4px solid #1890ff;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <h1>🔍 Quiz Game Installation Check</h1>

    <?php
    $checks = [];

    // Check PHP version
    $phpVersion = phpversion();
    $checks[] = [
        'name' => 'PHP Version',
        'status' => version_compare($phpVersion, '7.4', '>=') ? 'success' : 'error',
        'message' => "PHP $phpVersion " . (version_compare($phpVersion, '7.4', '>=') ? '✓' : '✗ (Requires 7.4+)')
    ];

    // Check PDO MySQL
    $checks[] = [
        'name' => 'PDO MySQL Extension',
        'status' => extension_loaded('pdo_mysql') ? 'success' : 'error',
        'message' => extension_loaded('pdo_mysql') ? 'Installed ✓' : 'Not installed ✗'
    ];

    // Check MySQLi
    $checks[] = [
        'name' => 'MySQLi Extension',
        'status' => extension_loaded('mysqli') ? 'success' : 'warning',
        'message' => extension_loaded('mysqli') ? 'Installed ✓' : 'Not installed'
    ];

    // Check Composer autoload
    $composerCheck = file_exists(__DIR__ . '/vendor/autoload.php');
    $checks[] = [
        'name' => 'Composer Dependencies',
        'status' => $composerCheck ? 'success' : 'error',
        'message' => $composerCheck ? 'Installed ✓' : 'Run: composer install'
    ];

    // Check database config
    $dbConfigCheck = file_exists(__DIR__ . '/config/database.php');
    $checks[] = [
        'name' => 'Database Configuration',
        'status' => $dbConfigCheck ? 'success' : 'error',
        'message' => $dbConfigCheck ? 'Found ✓' : 'Missing config/database.php'
    ];

    // Try database connection
    if ($dbConfigCheck) {
        try {
            $config = require __DIR__ . '/config/database.php';
            $dsn = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );
            $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);

            $checks[] = [
                'name' => 'Database Connection',
                'status' => 'success',
                'message' => 'Connected ✓'
            ];

            // Check tables
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $requiredTables = ['games', 'questions', 'players', 'answers'];
            $missingTables = array_diff($requiredTables, $tables);

            if (empty($missingTables)) {
                $checks[] = [
                    'name' => 'Database Tables',
                    'status' => 'success',
                    'message' => 'All tables exist ✓'
                ];

                // Check if questions exist
                $stmt = $pdo->query("SELECT COUNT(*) FROM questions");
                $questionCount = $stmt->fetchColumn();

                $checks[] = [
                    'name' => 'Sample Questions',
                    'status' => $questionCount > 0 ? 'success' : 'warning',
                    'message' => "$questionCount questions found" . ($questionCount > 0 ? ' ✓' : ' - Import sample_questions.sql')
                ];
            } else {
                $checks[] = [
                    'name' => 'Database Tables',
                    'status' => 'error',
                    'message' => 'Missing tables: ' . implode(', ', $missingTables)
                ];
            }

        } catch (PDOException $e) {
            $checks[] = [
                'name' => 'Database Connection',
                'status' => 'error',
                'message' => 'Failed: ' . $e->getMessage()
            ];
        }
    }

    // Check required files
    $requiredFiles = [
        'host.html' => 'Host View',
        'player.html' => 'Player View',
        'js/host.js' => 'Host JavaScript',
        'js/player.js' => 'Player JavaScript',
        'api/index.php' => 'API Endpoints',
        'server.php' => 'WebSocket Server'
    ];

    foreach ($requiredFiles as $file => $name) {
        $exists = file_exists(__DIR__ . '/' . $file);
        $checks[] = [
            'name' => $name,
            'status' => $exists ? 'success' : 'error',
            'message' => $exists ? 'Found ✓' : "Missing: $file"
        ];
    }

    // Check writable directories
    $dirs = ['database', 'config'];
    foreach ($dirs as $dir) {
        $writable = is_writable(__DIR__ . '/' . $dir);
        $checks[] = [
            'name' => "$dir/ writable",
            'status' => $writable ? 'success' : 'warning',
            'message' => $writable ? 'Yes ✓' : 'No - Check permissions'
        ];
    }

    // Display results
    foreach ($checks as $check) {
        echo '<div class="check-item">';
        echo '<span>' . htmlspecialchars($check['name']) . '</span>';
        echo '<span class="status ' . $check['status'] . '">' . htmlspecialchars($check['message']) . '</span>';
        echo '</div>';
    }

    // Count statuses
    $successCount = count(array_filter($checks, fn($c) => $c['status'] === 'success'));
    $errorCount = count(array_filter($checks, fn($c) => $c['status'] === 'error'));
    $warningCount = count(array_filter($checks, fn($c) => $c['status'] === 'warning'));

    ?>

    <div class="info">
        <strong>Summary:</strong><br>
        ✓ Passed: <?php echo $successCount; ?><br>
        ✗ Failed: <?php echo $errorCount; ?><br>
        ⚠ Warnings: <?php echo $warningCount; ?><br>
        <br>
        <?php if ($errorCount === 0): ?>
            <strong style="color: #48bb78;">✓ All critical checks passed! You're ready to start.</strong><br>
            <br>
            Next steps:<br>
            1. Open Terminal and run: <code>php server.php</code><br>
            2. Open host view: <a href="host.html">host.html</a><br>
            3. Share player view: <a href="player.html">player.html</a>
        <?php else: ?>
            <strong style="color: #fc8181;">✗ Please fix the errors above before starting.</strong><br>
            <br>
            Check <code>QUICK_START.md</code> for installation instructions.
        <?php endif; ?>
    </div>

    <p style="text-align: center; color: #999; margin-top: 40px;">
        Quiz Game v1.0 | Made with ❤️
    </p>
</body>
</html>
