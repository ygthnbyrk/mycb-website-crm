<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

@ini_set('memory_limit', '512M');
@set_time_limit(120);

// ⬇️ KÜTÜPHANEYİ DOĞRU YOLDAN DAHİL ET
require_once __DIR__ . '/SimpleXLSXGen.php';
// Eğer dosya farklı klasördeyse yolu buna göre değiştir:
// require_once __DIR__ . '/libs/SimpleXLSXGen.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$stmt = $conn->prepare("
  SELECT model, product_name, serial_number, imei_number, cost_price, vat, total_cost, category, description
  FROM products
  ORDER BY created_at DESC
");
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
$rows[] = ['model','product_name','serial_number','imei_number','cost_price','vat','total_cost','category','description'];

while ($r = $res->fetch_assoc()) {
    foreach ($r as $k => $v) {
        if (is_string($v) && preg_match('/^[=\+\-\@]/', $v)) {
            $r[$k] = "'".$v;
        }
    }
    $rows[] = [
        $r['model'],
        $r['product_name'],
        $r['serial_number'],
        $r['imei_number'],
        (float)$r['cost_price'],
        (float)$r['vat'],
        (float)$r['total_cost'],
        $r['category'],
        $r['description'],
    ];
}

// ⬇️ BURADA İKİ İSİM ALANINI DA DESTEKLE
if (class_exists('\Shuchkin\SimpleXLSXGen')) {
    $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($rows);
} elseif (class_exists('\SimpleXLSXGen')) {
    $xlsx = \SimpleXLSXGen::fromArray($rows);
} else {
    die('SimpleXLSXGen sınıfı bulunamadı. SimpleXLSXGen.php yolunu ve sınıf adını kontrol edin.');
}

$xlsx->downloadAs('products_'.date('Y-m-d_His').'.xlsx');
exit;
