<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("Lütfen giriş yapın");
}

$sql = "CREATE TABLE IF NOT EXISTS subscription_renewal_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscription_id INT NOT NULL,
    threshold_days INT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_sub_threshold (subscription_id, threshold_days)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "OK: subscription_renewal_alerts tablosu hazır.\n";
} else {
    echo "HATA: " . $conn->error . "\n";
}
?>
