<?php
require_once 'config.php';
require_once 'partials/telegram-helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($id <= 0 || !in_array($action, ['process', 'delete'], true)) {
    header('Location: telegram-review.php');
    exit;
}

$stmt = $conn->prepare("SELECT id FROM telegram_pending_matches WHERE id = ? AND status = 'collecting'");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $_SESSION['error'] = 'Kayıt bulunamadı ya da zaten işlenmiş.';
    header('Location: telegram-review.php');
    exit;
}

if ($action === 'delete') {
    $del = $conn->prepare("DELETE FROM telegram_pending_matches WHERE id = ?");
    $del->bind_param("i", $id);
    $del->execute();
    $del->close();
    $_SESSION['success'] = 'Kayıt silindi.';
} else {
    // 2. fotoğraf hiç gelmedi, elimizdeki tek fotoğrafla yine de işle
    process_pending_telegram_row($conn, $id);
    $_SESSION['success'] = 'Kayıt tek fotoğrafla işlendi, onay kuyruğunda görünecek.';
}

header('Location: telegram-review.php');
exit;
?>
