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
$failed = 0;

if (!empty($batch)) {
    $stmt = $conn->prepare("UPDATE subscriptions SET renewal_date = ?, renewal_amount = ?, vat = ?, total_amount = ?, subscription_revenue = ?, status = 'Yenilendi' WHERE id = ?");
    foreach ($batch as $row) {
        $stmt->bind_param(
            "sddddi",
            $row['renewal_date'],
            $row['renewal_amount'],
            $row['vat'],
            $row['total_amount'],
            $row['subscription_revenue'],
            $row['sub_id']
        );
        if ($stmt->execute()) {
            $updated++;
        } else {
            $failed++;
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
            <strong><?php echo $updated; ?></strong> abonelik başarıyla yenilendi.
            <?php if ($failed > 0): ?>
                <br><strong style="color:var(--danger);"><?php echo $failed; ?></strong> kayıt güncellenemedi.
            <?php endif; ?>
        </div>

        <div class="actions">
            <a href="subscriptions.php" class="btn btn-primary"><?php echo icon('list'); ?> Abonelikleri Görüntüle</a>
            <a href="subscription-renewal-upload.php" class="btn btn-secondary">Yeni Dosya Yükle</a>
        </div>
    </div>
</body>
</html>
