<?php

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $pdo = new PDO(
        'mysql:host=' . $_ENV['DB_HOST'] .
        ';port=' . $_ENV['DB_PORT'] .
        ';dbname=' . $_ENV['DB_DATABASE'],
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD'] ?? ''
    );

    echo "DATABASE CONNECTION OK";
} catch (Throwable $e) {
    echo "DATABASE CONNECTION FAILED: " . $e->getMessage();
}