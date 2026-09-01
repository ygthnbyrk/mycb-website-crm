<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("Lütfen giriş yapın");
}

$min = isset($_GET['min']) ? (int)$_GET['min'] : 5;

$sql = "SELECT c.id, c.name, c.tax_number, c.phone, c.address,
               COUNT(*) as arac_sayisi
        FROM subscriptions s
        JOIN customers c ON s.customer_id = c.id
        WHERE s.item_type = 'product' AND s.status != 'İptal'
        GROUP BY c.id, c.name, c.tax_number, c.phone, c.address
        HAVING COUNT(*) >= ?
        ORDER BY arac_sayisi DESC, c.name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $min);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Araç Takip Abonelik Raporu (min <?= $min ?>)</title>
<style>
body{font-family:Arial,sans-serif;padding:20px;}
table{border-collapse:collapse;width:100%;}
th,td{border:1px solid #ccc;padding:6px 10px;text-align:left;font-size:14px;}
th{background:#f0f0f0;}
</style>
</head>
<body>
<h2>En az <?= $min ?> araç takip aboneliği olan firmalar</h2>
<table>
<tr><th>#</th><th>Firma</th><th>Vergi No</th><th>Telefon</th><th>Adres</th><th>Araç Sayısı</th></tr>
<?php $i=1; $total=0; while ($row = $result->fetch_assoc()): $total++; ?>
<tr>
<td><?= $i++ ?></td>
<td><?= htmlspecialchars($row['name']) ?></td>
<td><?= htmlspecialchars($row['tax_number']) ?></td>
<td><?= htmlspecialchars($row['phone'] ?? '') ?></td>
<td><?= htmlspecialchars($row['address'] ?? '') ?></td>
<td><?= $row['arac_sayisi'] ?></td>
</tr>
<?php endwhile; ?>
</table>
<p>Toplam firma: <?= $total ?></p>
</body>
</html>
