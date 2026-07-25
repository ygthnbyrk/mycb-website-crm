<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: telegram-review.php');
    exit;
}

$pending_id = intval($_POST['pending_id'] ?? 0);
$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM telegram_pending_customers WHERE id = ? AND status = 'pending'");
$stmt->bind_param("i", $pending_id);
$stmt->execute();
$pending = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pending) {
    $_SESSION['error'] = 'Kayıt bulunamadı ya da zaten işlenmiş.';
    header('Location: telegram-review.php');
    exit;
}

if ($action === 'reject') {
    $stmt = $conn->prepare("UPDATE telegram_pending_customers SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param("ii", $user_id, $pending_id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success'] = 'Kayıt reddedildi.';
    header('Location: telegram-review.php');
    exit;
}

if ($action !== 'approve') {
    header('Location: telegram-review.php');
    exit;
}

$customer_mode = $_POST['customer_mode'] ?? '';
$name = trim($_POST['name'] ?? '');
$tax_number = trim($_POST['tax_number'] ?? '');
$address = trim($_POST['address'] ?? '') ?: null;

if (empty($name) || empty($tax_number)) {
    $_SESSION['error'] = 'Müşteri adı ve vergi/T.C. numarası zorunlu.';
    header('Location: telegram-review.php');
    exit;
}

try {
    if ($customer_mode === 'existing') {
        $customer_id = intval($_POST['customer_id'] ?? 0);
        if ($customer_id <= 0) throw new Exception('Güncellenecek müşteri seçilmedi.');

        $check = $conn->prepare("SELECT id FROM customers WHERE tax_number = ? AND id != ?");
        $check->bind_param("si", $tax_number, $customer_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) throw new Exception('Bu vergi/T.C. numarası başka bir müşteride kayıtlı.');
        $check->close();

        $stmt = $conn->prepare("UPDATE customers SET name = ?, tax_number = ?, address = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $tax_number, $address, $customer_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($customer_mode === 'new') {
        $check = $conn->prepare("SELECT id FROM customers WHERE tax_number = ?");
        $check->bind_param("s", $tax_number);
        $check->execute();
        if ($check->get_result()->num_rows > 0) throw new Exception('Bu vergi/T.C. numarası ile kayıtlı bir müşteri zaten var - "Mevcut müşteriyi güncelle" seçip ara.');
        $check->close();

        $stmt = $conn->prepare("INSERT INTO customers (name, tax_number, address, created_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $name, $tax_number, $address, $user_id);
        $stmt->execute();
        $customer_id = $conn->insert_id;
        $stmt->close();
    } else {
        throw new Exception('Müşteri modu belirtilmedi.');
    }

    $stmt = $conn->prepare("UPDATE telegram_pending_customers SET status = 'approved', created_customer_id = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param("iii", $customer_id, $user_id, $pending_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success'] = 'Müşteri kaydedildi.';
} catch (Exception $e) {
    $_SESSION['error'] = 'Onaylanamadı: ' . $e->getMessage();
}

header('Location: telegram-review.php');
exit;
?>
