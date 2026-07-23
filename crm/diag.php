<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

header('Content-Type: text/plain; charset=utf-8');

$r = $conn->query("SELECT COUNT(*) c FROM sales WHERE YEAR(sale_date)=2025")->fetch_assoc();
echo "sales WHERE YEAR=2025: " . $r['c'] . "\n";

$r = $conn->query("SELECT COUNT(*) c, MIN(sale_date) mn, MAX(sale_date) mx FROM sales")->fetch_assoc();
echo "sales TOPLAM: {$r['c']}  min={$r['mn']}  max={$r['mx']}\n\n";

echo "--- yil bazinda dagilim ---\n";
$res = $conn->query("SELECT YEAR(sale_date) y, COUNT(*) c FROM sales GROUP BY y ORDER BY y");
while ($row = $res->fetch_assoc()) {
    echo "{$row['y']}: {$row['c']}\n";
}

echo "\n--- ayni (customer_id, sale_date) icin birden fazla satis var mi ---\n";
$res = $conn->query("SELECT customer_id, sale_date, COUNT(*) c FROM sales WHERE YEAR(sale_date)=2025 GROUP BY customer_id, sale_date HAVING c > 1 ORDER BY c DESC LIMIT 20");
$dupCount = 0;
while ($row = $res->fetch_assoc()) {
    $dupCount++;
    echo "customer_id={$row['customer_id']} date={$row['sale_date']} adet={$row['c']}\n";
}
echo "toplam boyle grup: $dupCount\n";

echo "\n--- created_at'e gore son 350 satisin tarih dagilimi (bugun eklenenler) ---\n";
$res = $conn->query("SELECT DATE(created_at) d, COUNT(*) c FROM sales GROUP BY d ORDER BY d DESC LIMIT 10");
while ($row = $res->fetch_assoc()) {
    echo "{$row['d']}: {$row['c']} satis olusturulmus\n";
}

echo "\n--- customers tablosunda BILINMIYOR ile baslayan kac musteri var ---\n";
$r = $conn->query("SELECT COUNT(*) c FROM customers WHERE tax_number LIKE 'BILINMIYOR-%'")->fetch_assoc();
echo "BILINMIYOR-... musteri sayisi: {$r['c']}\n";

echo "\n--- bugun (created_at) olusan ama sale_date yili 2025 OLMAYAN satislar ---\n";
$res = $conn->query("SELECT s.id, s.sale_date, s.customer_id, c.name, s.total FROM sales s LEFT JOIN customers c ON s.customer_id=c.id WHERE DATE(s.created_at)='2026-07-23' AND YEAR(s.sale_date) != 2025");
while ($row = $res->fetch_assoc()) {
    echo "id={$row['id']} sale_date={$row['sale_date']} customer={$row['name']} total={$row['total']}\n";
}

echo "\n--- customer_id=571, 2025-01-24 detaylari (neden 7 ayri satis) ---\n";
$res = $conn->query("SELECT id, sale_date, total, created_at FROM sales WHERE customer_id=571 AND sale_date='2025-01-24'");
while ($row = $res->fetch_assoc()) {
    echo "sale_id={$row['id']} total={$row['total']} created_at={$row['created_at']}\n";
}
