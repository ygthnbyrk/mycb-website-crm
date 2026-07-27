<?php
require_once 'config.php';
require_once 'partials/icons.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_name = $_SESSION['user_name'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Toplu Yüklemeler - CRM</title>
</head>
<body>
    <?php $active_page = 'bulk-uploads'; include 'partials/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1><?php echo icon('upload'); ?> Toplu Yüklemeler</h1>
                <p class="welcome">Excel ile toplu işlem yapabileceğin sayfalar</p>
            </div>
        </div>

        <div class="details-grid">
            <a href="bulk-sales-upload.php" class="detail-card" style="text-decoration:none;color:inherit;display:block;">
                <h3><?php echo icon('dollar'); ?> Toplu Satış Yükle</h3>
                <p style="font-size:13.5px;color:var(--text-secondary);">Excel dosyası ile tek seferde birden fazla satış kaydı oluştur.</p>
            </a>

            <a href="subscription-renewal-upload.php" class="detail-card" style="text-decoration:none;color:inherit;display:block;">
                <h3><?php echo icon('refresh'); ?> Abonelik Yenileme</h3>
                <p style="font-size:13.5px;color:var(--text-secondary);">Excel listesiyle toplu abonelik yenileme yap.</p>
            </a>
        </div>
    </div>
</body>
</html>
