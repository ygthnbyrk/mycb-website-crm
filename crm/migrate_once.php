<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

header('Content-Type: text/plain; charset=utf-8');

$queries = [
    "ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci",
    "ALTER TABLE users ENGINE=InnoDB",
];

foreach ($queries as $sql) {
    if ($conn->query($sql)) {
        echo "OK: $sql\n";
    } else {
        echo "HATA: $sql -> " . $conn->error . "\n";
    }
}

echo "\nBitti.\n";
