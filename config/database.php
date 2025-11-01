<?php

// Database Configuration
// Check if running on production server (detect by domain or hostname)
$isProduction = isset($_SERVER['HTTP_HOST']) && (
    strpos($_SERVER['HTTP_HOST'], 'kdsc.fun') !== false ||
    strpos($_SERVER['HTTP_HOST'], 'worldwar3') !== false
);

if ($isProduction) {
    // Production server settings
    return [
        'host' => 'localhost', // Usually localhost on shared hosting
        'port' => 3306, // Default MySQL port
        'database' => 'u986938982_worldwar3_game',
        'username' => 'u986938982_worldwar3_game',
        'password' => 'Warldwar3_game@1234',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ];
} else {
    // Local MAMP development settings
    return [
        'host' => 'localhost',
        'port' => 8889, // MAMP default MySQL port
        'database' => 'worshipteam',
        'username' => 'root',
        'password' => 'root', // Default MAMP password
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ];
}
