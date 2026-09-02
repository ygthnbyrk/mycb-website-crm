<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: create-camera-sale.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$customer_id = intval($_POST['customer_id'] ?? 0);
$sale_date = trim($_POST['sale_date'] ?? '') ?: date('Y-m-d');
$notes = trim($_POST['notes'] ?? '') ?: null;
$items = json_decode($_POST['items_data'] ?? '[]', true);

if (!$customer_id) {
    $_SESSION['error'] = 'Lütfen müşteri seçin.';
    header('Location: create-camera-sale.php');
    exit;
}

$stmt = $conn->prepare("INSERT INTO camera_sales (sale_date, customer_id, notes, created_by) VALUES (?, ?, ?, ?)");
$stmt->bind_param('sisi', $sale_date, $customer_id, $notes, $user_id);

if (!$stmt->execute()) {
    $_SESSION['error'] = 'Kamera satışı kaydedilirken hata oluştu.';
    header('Location: create-camera-sale.php');
    exit;
}
$camera_sale_id = $conn->insert_id;
$stmt->close();

if (!empty($items)) {
    $renewal_date = date('Y-m-d', strtotime($sale_date . ' + 24 months'));

    foreach ($items as $item) {
        $product_id = !empty($item['product_id']) ? intval($item['product_id']) : null;
        $item_name = trim($item['item_name'] ?? '');
        $category = trim($item['category'] ?? '') ?: null;
        $cost_price = isset($item['cost_price']) && $item['cost_price'] !== '' ? floatval($item['cost_price']) : null;
        $sale_price = isset($item['sale_price']) && $item['sale_price'] !== '' ? floatval($item['sale_price']) : null;
        $quantity = !empty($item['quantity']) ? intval($item['quantity']) : 1;

        if ($item_name === '') {
            continue;
        }

        $stmt_item = $conn->prepare("INSERT INTO camera_sale_items (camera_sale_id, product_id, category, item_name, cost_price, sale_price, quantity) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_item->bind_param('iissddi', $camera_sale_id, $product_id, $category, $item_name, $cost_price, $sale_price, $quantity);
        $stmt_item->execute();
        $stmt_item->close();

        // Kayıtlı ürün seçildiyse stok durumunu "Satıldı" yap (satış akışı dışındaki hızlı
        // Stokta<->Pasif geçişi toggle-product-status.php'de zaten bu duruma dokunmuyor).
        if ($product_id) {
            $stmt_status = $conn->prepare("UPDATE products SET status = 'Satıldı' WHERE id = ?");
            $stmt_status->bind_param('i', $product_id);
            $stmt_status->execute();
            $stmt_status->close();
        }

        // Abonelik oluştur - mevcut Abonelikler yapısıyla aynı mantık (24 ay / item_type=product)
        $item_detail = $category ?? '';
        $stmt_sub = $conn->prepare("INSERT INTO subscriptions (sale_id, customer_id, product_id, item_type, item_name, item_detail, initial_sale_date, renewal_date) VALUES (?, ?, ?, 'product', ?, ?, ?, ?)");
        $stmt_sub->bind_param('iiissss', $camera_sale_id, $customer_id, $product_id, $item_name, $item_detail, $sale_date, $renewal_date);
        $stmt_sub->execute();
        $stmt_sub->close();
    }
}

$_SESSION['success'] = 'Kamera satışı kaydedildi.';
header('Location: camera-sales-list.php');
exit;
