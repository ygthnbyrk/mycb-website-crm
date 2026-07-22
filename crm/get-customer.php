<?php
ob_start();
require_once 'config.php';
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['error' => 'Geçersiz ID']);
    exit;
}

$stmt = $conn->prepare("SELECT id, name, tax_number, email, phone, address FROM customers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['error' => 'Müşteri bulunamadı']);
}

$stmt->close();
$conn->close();
exit;