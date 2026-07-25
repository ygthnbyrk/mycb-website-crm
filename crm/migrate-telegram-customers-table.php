<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("Lütfen giriş yapın");
}

$sql = "CREATE TABLE IF NOT EXISTS telegram_pending_customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_chat_id BIGINT NOT NULL,
    telegram_file_id VARCHAR(255) NOT NULL,
    telegram_media_type ENUM('photo','document') NOT NULL,
    caption_raw TEXT NULL,
    ocr_entity_type VARCHAR(20) NULL,
    ocr_name VARCHAR(255) NULL,
    ocr_tax_number VARCHAR(20) NULL,
    ocr_address TEXT NULL,
    ocr_notes TEXT NULL,
    ocr_raw_response LONGTEXT NULL,
    error_message TEXT NULL,
    matched_customer_id INT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_customer_id INT NULL,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "OK: telegram_pending_customers tablosu hazır.\n";
} else {
    echo "HATA: " . $conn->error . "\n";
}
?>
