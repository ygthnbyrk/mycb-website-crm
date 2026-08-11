<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: hub.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: duplicate-customers.php');
    exit;
}

$keep_id = intval($_POST['keep_id'] ?? 0);
$all_ids = array_map('intval', $_POST['all_ids'] ?? []);
$all_ids = array_values(array_unique(array_filter($all_ids, fn($id) => $id > 0)));

if ($keep_id <= 0 || !in_array($keep_id, $all_ids, true) || count($all_ids) < 2) {
    $_SESSION['error'] = 'Geçersiz birleştirme isteği.';
    header('Location: duplicate-customers.php');
    exit;
}

$merge_ids = array_values(array_diff($all_ids, [$keep_id]));
$merge_ids_list = implode(',', $merge_ids); // sadece intval'den geçmiş değerler, injection riski yok

$conn->begin_transaction();
try {
    $conn->query("UPDATE sales SET customer_id = $keep_id WHERE customer_id IN ($merge_ids_list)");
    $conn->query("UPDATE subscriptions SET customer_id = $keep_id WHERE customer_id IN ($merge_ids_list)");
    $conn->query("UPDATE quotes SET customer_id = $keep_id WHERE customer_id IN ($merge_ids_list)");

    // Telegram OCR eşleştirme geçmişi (tablo yoksa/hata verirse sessizce geç, kritik değil)
    @$conn->query("UPDATE telegram_pending_customers SET matched_customer_id = $keep_id WHERE matched_customer_id IN ($merge_ids_list)");
    @$conn->query("UPDATE telegram_pending_customers SET created_customer_id = $keep_id WHERE created_customer_id IN ($merge_ids_list)");

    $del = $conn->prepare("DELETE FROM customers WHERE id IN ($merge_ids_list)");
    if (!$del->execute()) {
        throw new Exception($conn->error);
    }
    $del->close();

    $conn->commit();
    $_SESSION['success'] = count($merge_ids) . ' mükerrer müşteri kaydı birleştirildi.';
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = 'Birleştirme sırasında hata oluştu: ' . $e->getMessage();
}

header('Location: duplicate-customers.php');
exit;
?>
