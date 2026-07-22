<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$search = $_GET['q'] ?? '';

if (strlen($search) < 2) {
    echo json_encode([]);
    exit;
}

$search_param = "%$search%";
$stmt = $conn->prepare("SELECT id, name, tax_number, phone FROM customers WHERE name LIKE ? OR tax_number LIKE ? LIMIT 10");
$stmt->bind_param("ss", $search_param, $search_param);
$stmt->execute();
$result = $stmt->get_result();

$customers = [];
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}

echo json_encode($customers, JSON_UNESCAPED_UNICODE);
$stmt->close();
?>