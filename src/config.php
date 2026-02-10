<?php
// Minimal configuration - adjust for your environment
return [
    'db' => [
        'dsn' => 'mysql:host=127.0.0.1;dbname=bookrent_db;charset=utf8mb4',
        'user' => 'root',
        'pass' => ''
    ],
    'settings' => [
        'max_active_rentals_per_user' => 3,
        'penalties_enabled' => true
    ]
];
