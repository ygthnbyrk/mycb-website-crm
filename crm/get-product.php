<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Oturum bulunamadı']);
    exit;
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['error' => 'Geçersiz ID']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['error' => 'Ürün bulunamadı']);
}

$stmt->close();
?>