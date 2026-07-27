<?php
require_once 'config.php';
header('Content-Type: text/plain; charset=utf-8');

// Ornek: az once ekranda gorulen "Iptal - kayit yok" IMEI'lerinden birkaci
$test_imeis = ['861100065083020', '860103069093616', '860103069085745', '861100065067197'];

foreach ($test_imeis as $imei) {
    echo "=== $imei ===\n";
    $stmt = $conn->prepare("SELECT id, model, status FROM products WHERE REPLACE(REPLACE(REPLACE(REPLACE(TRIM(imei_number),' ',''),CHAR(13),''),CHAR(10),''),CHAR(9),'') = ?");
    $stmt->bind_param("s", $imei);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        echo "  products'ta YOK\n";
    } else {
        while ($r = $res->fetch_assoc()) echo "  products: " . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\n=== Genel dagilim: Iptal excel'inde olup subscriptions'ta olmayan IMEI'lerin products status dagilimi ===\n";
// Bu kismi manuel test icin atliyoruz, sadece ornek IMEI'ler yeterli
