<?php
require_once 'config.php';
header('Content-Type: text/plain; charset=utf-8');

$phone = '0551 507 60 94';
echo "=== simcards status dagilimi ===\n";
$res = $conn->query("SELECT status, COUNT(*) c FROM simcards GROUP BY status");
while ($r = $res->fetch_assoc()) echo "[" . $r['status'] . "] (hex:" . bin2hex($r['status']) . ") : " . $r['c'] . "\n";

echo "\n=== bu telefon numarasi ===\n";
$search_norm = preg_replace('/[^0-9]/', '', $phone);
$stmt = $conn->prepare("SELECT id, phone_number, status FROM simcards WHERE REPLACE(REPLACE(REPLACE(phone_number,' ',''),'-',''),'(','') LIKE ?");
$like = "%$search_norm%";
$stmt->bind_param("s", $like);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) echo json_encode($r, JSON_UNESCAPED_UNICODE) . " statushex:" . bin2hex($r['status']) . "\n";

echo "\n=== products status dagilimi ===\n";
$res = $conn->query("SELECT status, COUNT(*) c FROM products GROUP BY status");
while ($r = $res->fetch_assoc()) echo "[" . $r['status'] . "] (hex:" . bin2hex($r['status']) . ") : " . $r['c'] . "\n";
