<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Oturum yok']);
    exit;
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['error' => 'Geçersiz ID']);
    exit;
}

$stmt = $conn->prepare("SELECT id, phone_number, operator, company, category, status, cost_price, vat, total_cost, description FROM simcards WHERE id = ?");

if (!$stmt) {
    echo json_encode(['error' => 'Sorgu hatası']);
    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode($row, JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['error' => 'Bulunamadı']);
}

$stmt->close();
$conn->close();
exit;