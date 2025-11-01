<?php

// Database Configuration
return [
    'host' => 'localhost',
    'port' => 8889, // MAMP default MySQL port
    'database' => 'u986938982_worldwar3_game',
    'username' => 'u986938982_worldwar3_game',
    'password' => 'Warldwar3_game@1234', // Default MAMP password
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
