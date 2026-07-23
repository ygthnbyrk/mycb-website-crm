<?php
require_once 'config.php';
require_once 'partials/icons.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Teknoloji - Mycb</title>
</head>
<body>
    <div class="hub-wrap">
        <div class="hub-icon hub-icon-lg" style="width: 72px; height: 72px; margin-bottom: 24px;">
            <?php echo icon('cpu'); ?>
        </div>
        <h1 style="font-size: 20px; margin-bottom: 10px; color: var(--text-primary);">Bu sayfa oluşturulmaktadır</h1>
        <p class="hub-title" style="margin-bottom: 28px;">Teknoloji alanı yakında burada olacak.</p>
        <a href="hub.php" class="btn btn-secondary"><?php echo icon('chevron-left'); ?> Geri Dön</a>
    </div>
</body>
</html>
