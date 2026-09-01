<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$search = $_GET['q'] ?? '';

if (strlen($search) < 3) {
    echo json_encode([]);
    exit;
}

$search_param = "%$search%";
// imei_number artik bazi kategorilerde (Aksesuar/Hizmet/Kamera) NULL olabiliyor;
// bu arac takip cihazi arama kutusu, IMEI'si olmayan katalog kalemlerini (Montaj, kablo vb.) hic gostermemeli.
$stmt = $conn->prepare("SELECT id, imei_number, model, total_cost FROM products WHERE (imei_number LIKE ? OR model LIKE ?) AND status = 'Stokta' AND imei_number IS NOT NULL AND imei_number != '' LIMIT 10");
$stmt->bind_param("ss", $search_param, $search_param);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode($products, JSON_UNESCAPED_UNICODE);
$stmt->close();
?>