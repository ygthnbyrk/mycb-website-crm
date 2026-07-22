<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

header('Content-Type: text/plain; charset=utf-8');

$queries = [
    // email üzerindeki unique index, utf8mb4 (4 byte/karakter) ile 255 karakterde
    // index boyut limitini aşıyor; önce 191'e düşürüyoruz (191*4=764 bayt, güvenli sınır içinde).
    "ALTER TABLE users MODIFY `email` VARCHAR(191) NOT NULL",
    "ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci",
    "ALTER TABLE users ENGINE=InnoDB",
];

foreach ($queries as $sql) {
    try {
        $conn->query($sql);
        echo "OK: $sql\n";
    } catch (mysqli_sql_exception $e) {
        echo "HATA: $sql -> " . $e->getMessage() . "\n";
    }
}

echo "\nBitti.\n";
