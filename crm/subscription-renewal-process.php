<?php
require_once 'config.php';
require_once 'partials/icons.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$batch = $_SESSION['subscription_renewal_batch'] ?? [];
unset($_SESSION['subscription_renewal_batch']);

$updated = 0;
$inserted = 0;
$cancelled = 0;
$full_created = 0;
$failed = 0;

if (!empty($batch)) {
    $update_stmt = $conn->prepare("UPDATE subscriptions SET renewal_date = ?, renewal_amount = ?, vat = ?, total_amount = ?, subscription_revenue = ?, status = 'Yenilendi' WHERE id = ?");
    $insert_stmt = $conn->prepare("INSERT INTO subscriptions (sale_id, customer_id, product_id, item_type, item_name, item_detail, cycle, initial_sale_date, renewal_date, renewal_amount, vat, total_amount, subscription_revenue, status) VALUES (?, ?, ?, 'product', ?, ?, 1, ?, ?, ?, ?, ?, ?, 'Yenilendi')");
    $cancel_stmt = $conn->prepare("UPDATE subscriptions SET status = 'İptal' WHERE id = ?");
    $cancel_insert_stmt = $conn->prepare("INSERT INTO subscriptions (sale_id, customer_id, product_id, item_type, item_name, item_detail, cycle, initial_sale_date, renewal_date, renewal_amount, vat, total_amount, subscription_revenue, status) VALUES (?, ?, ?, 'product', ?, ?, 1, ?, ?, 0, 0, 0, 0, 'İptal')");
    $customer_find_stmt = $conn->prepare("SELECT id FROM customers WHERE name = ? LIMIT 1");
    $customer_insert_stmt = $conn->prepare("INSERT INTO customers (name, tax_number, created_by) VALUES (?, ?, ?)");
    $product_insert_stmt = $conn->prepare("INSERT INTO products (model, product_name, imei_number, cost_price, vat, total_cost, category, description, status, created_by) VALUES (?, ?, ?, 0, 0, 0, ?, ?, 'Satıldı', ?)");
    $sale_insert_stmt = $conn->prepare("INSERT INTO sales (sale_date, customer_id, subtotal, vat, total, created_by) VALUES (?, ?, ?, 0, ?, ?)");
    $sale_product_insert_stmt = $conn->prepare("INSERT INTO sale_products (sale_id, product_id, imei_number, model, price) VALUES (?, ?, ?, ?, ?)");

    foreach ($batch as $row) {
        if ($row['action'] === 'cancel') {
            $cancel_stmt->bind_param("i", $row['sub_id']);
            if ($cancel_stmt->execute()) {
                $cancelled++;
            } else {
                $failed++;
            }
        } elseif ($row['action'] === 'cancel_insert') {
            $cancel_insert_stmt->bind_param(
                "iiissss",
                $row['sale_id'],
                $row['customer_id'],
                $row['product_id'],
                $row['item_name'],
                $row['imei'],
                $row['initial_sale_date'],
                $row['initial_sale_date']
            );
            if ($cancel_insert_stmt->execute()) {
                $cancelled++;
            } else {
                $failed++;
            }
        } elseif ($row['action'] === 'full_create') {
            $user_id = $_SESSION['user_id'];
            $musteri_adi = trim($row['musteri_adi'] ?? '');
            $cust_name = $musteri_adi !== '' ? $musteri_adi : 'Bilinmeyen Müşteri';

            $customer_id = null;
            $customer_find_stmt->bind_param("s", $cust_name);
            $customer_find_stmt->execute();
            $existing_customer = $customer_find_stmt->get_result()->fetch_assoc();
            if ($existing_customer) {
                $customer_id = $existing_customer['id'];
            } else {
                $temp_tax = 'GECICI-' . uniqid('', true);
                $customer_insert_stmt->bind_param("ssi", $cust_name, $temp_tax, $user_id);
                if ($customer_insert_stmt->execute()) {
                    $customer_id = $conn->insert_id;
                }
            }

            $product_id = null;
            if ($customer_id) {
                $model = $row['item_name'];
                $category = 'Telefon';
                $description = 'Abonelik yenileme toplu yüklemesinden otomatik oluşturuldu - manuel düzeltme gerekebilir';
                $product_insert_stmt->bind_param("sssssi", $model, $model, $row['imei'], $category, $description, $user_id);
                if ($product_insert_stmt->execute()) {
                    $product_id = $conn->insert_id;
                }
            }

            $sale_id = null;
            if ($product_id) {
                $sale_date = $row['initial_sale_date'];
                $sale_insert_stmt->bind_param("siddi", $sale_date, $customer_id, $row['renewal_amount'], $row['renewal_amount'], $user_id);
                if ($sale_insert_stmt->execute()) {
                    $sale_id = $conn->insert_id;
                }
            }

            if ($sale_id) {
                $sale_product_insert_stmt->bind_param("iissd", $sale_id, $product_id, $row['imei'], $model, $row['renewal_amount']);
                $sale_product_insert_stmt->execute();

                $insert_stmt->bind_param(
                    "iiissssdddd",
                    $sale_id,
                    $customer_id,
                    $product_id,
                    $model,
                    $row['imei'],
                    $sale_date,
                    $row['renewal_date'],
                    $row['renewal_amount'],
                    $row['vat'],
                    $row['total_amount'],
                    $row['subscription_revenue']
                );
                if ($insert_stmt->execute()) {
                    $full_created++;
                } else {
                    $failed++;
                }
            } else {
                $failed++;
            }
        } elseif ($row['action'] === 'update') {
            $update_stmt->bind_param(
                "sddddi",
                $row['renewal_date'],
                $row['renewal_amount'],
                $row['vat'],
                $row['total_amount'],
                $row['subscription_revenue'],
                $row['sub_id']
            );
            if ($update_stmt->execute()) {
                $updated++;
            } else {
                $failed++;
            }
        } else {
            $insert_stmt->bind_param(
                "iiissssdddd",
                $row['sale_id'],
                $row['customer_id'],
                $row['product_id'],
                $row['item_name'],
                $row['imei'],
                $row['initial_sale_date'],
                $row['renewal_date'],
                $row['renewal_amount'],
                $row['vat'],
                $row['total_amount'],
                $row['subscription_revenue']
            );
            if ($insert_stmt->execute()) {
                $inserted++;
            } else {
                $failed++;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Abonelik Yenileme Sonucu - CRM</title>
</head>
<body>
    <?php $active_page = 'subscription-renewal'; include 'partials/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1><?php echo icon('check'); ?> Abonelik Yenileme Tamamlandı</h1>
            </div>
        </div>

        <div class="alert alert-success">
            <strong><?php echo $updated; ?></strong> mevcut abonelik yenilendi, <strong><?php echo $inserted; ?></strong> yeni abonelik kaydı oluşturuldu, <strong><?php echo $full_created; ?></strong> için sıfırdan müşteri/ürün/satış oluşturuldu, <strong><?php echo $cancelled; ?></strong> abonelik iptal edildi.
            <?php if ($failed > 0): ?>
                <br><strong style="color:var(--danger);"><?php echo $failed; ?></strong> kayıt işlenemedi.
            <?php endif; ?>
        </div>

        <div class="actions">
            <a href="subscriptions.php" class="btn btn-primary"><?php echo icon('list'); ?> Abonelikleri Görüntüle</a>
            <a href="subscription-renewal-upload.php" class="btn btn-secondary">Yeni Dosya Yükle</a>
        </div>
    </div>
</body>
</html>
