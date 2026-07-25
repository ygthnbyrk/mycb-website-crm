<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("Lütfen giriş yapın");
}

$sql = "CREATE TABLE IF NOT EXISTS telegram_pending_matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_chat_id BIGINT NOT NULL,
    telegram_media_group_id VARCHAR(64) NULL,
    photo_1_file_id VARCHAR(255) NULL,
    photo_2_file_id VARCHAR(255) NULL,
    caption_raw TEXT NULL,
    customer_name_raw VARCHAR(255) NULL,
    plate_raw VARCHAR(50) NULL,
    ocr_imei VARCHAR(50) NULL,
    ocr_serial VARCHAR(50) NULL,
    ocr_model_guess VARCHAR(100) NULL,
    ocr_phone_number VARCHAR(50) NULL,
    ocr_operator_guess VARCHAR(50) NULL,
    ocr_notes TEXT NULL,
    ocr_raw_response LONGTEXT NULL,
    error_message TEXT NULL,
    matched_product_id INT NULL,
    matched_simcard_id INT NULL,
    matched_customer_id INT NULL,
    status ENUM('collecting','pending','approved','rejected') NOT NULL DEFAULT 'collecting',
    sale_id INT NULL,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_media_group (telegram_media_group_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "OK: telegram_pending_matches tablosu hazır.\n";
} else {
    echo "HATA: " . $conn->error . "\n";
}
?>
