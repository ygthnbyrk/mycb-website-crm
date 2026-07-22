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
$stmt = $conn->prepare("SELECT id, phone_number, operator, company, total_cost FROM simcards WHERE (phone_number LIKE ? OR operator LIKE ?) AND status = 'Stokta' LIMIT 10");
$stmt->bind_param("ss", $search_param, $search_param);
$stmt->execute();
$result = $stmt->get_result();

$simcards = [];
while ($row = $result->fetch_assoc()) {
    $simcards[] = $row;
}

echo json_encode($simcards, JSON_UNESCAPED_UNICODE);
$stmt->close();
?>