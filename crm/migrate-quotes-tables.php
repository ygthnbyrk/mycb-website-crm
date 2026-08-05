<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("Lütfen giriş yapın");
}

$sql1 = "CREATE TABLE IF NOT EXISTS quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_tax_number VARCHAR(50) NULL,
    customer_phone VARCHAR(50) NULL,
    customer_email VARCHAR(100) NULL,
    customer_address TEXT NULL,
    quote_date DATE NOT NULL,
    valid_until DATE NULL,
    notes TEXT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    vat_total DECIMAL(12,2) NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    status ENUM('Beklemede','Olumlu','Olumsuz') NOT NULL DEFAULT 'Beklemede',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_quote_date (quote_date),
    KEY idx_status (status),
    KEY idx_customer_id (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$sql2 = "CREATE TABLE IF NOT EXISTS quote_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description VARCHAR(500) NULL,
    qty DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    vat_rate DECIMAL(5,2) NOT NULL DEFAULT 20,
    line_total DECIMAL(12,2) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql1)) {
    echo "OK: quotes tablosu hazır.\n";
} else {
    echo "HATA (quotes): " . $conn->error . "\n";
}

if ($conn->query($sql2)) {
    echo "OK: quote_items tablosu hazır.\n";
} else {
    echo "HATA (quote_items): " . $conn->error . "\n";
}
?>
