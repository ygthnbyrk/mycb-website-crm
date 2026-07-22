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
$stmt = $conn->prepare("SELECT id, imei_number, model, total_cost FROM products WHERE (imei_number LIKE ? OR model LIKE ?) AND status = 'Stokta' LIMIT 10");
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