<?php
require_once 'config.php';
require_once 'partials/telegram-helpers.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$id = intval($_GET['id'] ?? 0);
$slot = intval($_GET['slot'] ?? 1);
$column = $slot === 2 ? 'photo_2_file_id' : 'photo_1_file_id';

$stmt = $conn->prepare("SELECT $column AS file_id FROM telegram_pending_matches WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['file_id'])) {
    http_response_code(404);
    exit;
}

$bytes = tg_download_file_bytes($row['file_id']);
if (!$bytes) {
    http_response_code(502);
    exit;
}

header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=3600');
echo $bytes;
?>
