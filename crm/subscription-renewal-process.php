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
$failed = 0;

if (!empty($batch)) {
    $update_stmt = $conn->prepare("UPDATE subscriptions SET renewal_date = ?, renewal_amount = ?, vat = ?, total_amount = ?, subscription_revenue = ?, status = 'Yenilendi' WHERE id = ?");
    $insert_stmt = $conn->prepare("INSERT INTO subscriptions (sale_id, customer_id, product_id, item_type, item_name, item_detail, cycle, initial_sale_date, renewal_date, renewal_amount, vat, total_amount, subscription_revenue, status) VALUES (?, ?, ?, 'product', ?, ?, 1, ?, ?, ?, ?, ?, ?, 'Yenilendi')");
    $cancel_stmt = $conn->prepare("UPDATE subscriptions SET status = 'İptal' WHERE id = ?");
    $cancel_insert_stmt = $conn->prepare("INSERT INTO subscriptions (sale_id, customer_id, product_id, item_type, item_name, item_detail, cycle, initial_sale_date, renewal_date, renewal_amount, vat, total_amount, subscription_revenue, status) VALUES (?, ?, ?, 'product', ?, ?, 1, ?, ?, 0, 0, 0, 0, 'İptal')");

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
            <strong><?php echo $updated; ?></strong> mevcut abonelik yenilendi, <strong><?php echo $inserted; ?></strong> yeni abonelik kaydı oluşturuldu, <strong><?php echo $cancelled; ?></strong> abonelik iptal edildi.
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
