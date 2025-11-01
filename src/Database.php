<?php

namespace QuizGame;

use PDO;

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $config = require __DIR__ . '/../config/database.php';

            $host = $config['host'] ?? 'localhost';
            $port = $config['port'] ?? 3306;
            $socket = $config['socket'] ?? null;

            // For MAMP on macOS, localhost tries to use socket which may not work
            // Use 127.0.0.1 to force TCP/IP connection, or use socket path if provided
            if ($socket && ($host === 'localhost' || $host === '127.0.0.1')) {
                // Use Unix socket connection
                $dsn = sprintf(
                    "mysql:unix_socket=%s;dbname=%s;charset=%s",
                    $socket,
                    $config['database'],
                    $config['charset']
                );
            } else {
                // Use TCP/IP connection - convert localhost to 127.0.0.1 to avoid socket issues
                $tcpHost = ($host === 'localhost') ? '127.0.0.1' : $host;
                $dsn = sprintf(
                    "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                    $tcpHost,
                    $port,
                    $config['database'],
                    $config['charset']
                );
            }

            $this->connection = new PDO($dsn, $config['username'], $config['password'], $config['options']);
        } catch (\PDOException $e) {
            // Provide helpful error message
            $errorMsg = "Database connection failed:\n";
            $errorMsg .= "Host: " . ($host ?? 'unknown') . "\n";
            $errorMsg .= "Port: " . ($port ?? 'unknown') . "\n";
            $errorMsg .= "Database: " . ($config['database'] ?? 'unknown') . "\n";
            $errorMsg .= "Error: " . $e->getMessage() . "\n\n";
            $errorMsg .= "Troubleshooting:\n";
            $errorMsg .= "1. Make sure MySQL/MAMP is running\n";
            $errorMsg .= "2. Check database credentials in config/database.php\n";
            $errorMsg .= "3. Verify the database exists\n";
            
            throw new \RuntimeException($errorMsg, 0, $e);
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
}
