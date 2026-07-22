<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Oturum yok']);
    exit;
}

$phone = $_GET['phone'] ?? '';

if (empty($phone)) {
    echo json_encode(['error' => 'Telefon numarası girin']);
    exit;
}

$stmt = $conn->prepare("SELECT id, phone_number, operator, total_cost, status FROM simcards WHERE phone_number = ?");
$stmt->bind_param("s", $phone);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $simcard = $result->fetch_assoc();
    
    if ($simcard['status'] === 'Satıldı') {
        echo json_encode(['error' => 'Bu sim kart zaten satılmış']);
    } else {
        echo json_encode($simcard, JSON_UNESCAPED_UNICODE);
    }
} else {
    echo json_encode(['error' => 'Sim kart bulunamadı']);
}

$stmt->close();
?>