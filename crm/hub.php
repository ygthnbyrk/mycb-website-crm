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
    <title>Mycb Teknoloji - Alan Seçimi</title>
</head>
<body>
    <div class="hub-wrap">
        <a href="logout.php" class="btn btn-secondary hub-logout"><?php echo icon('logout'); ?> Çıkış Yap</a>

        <div class="hub-logo">
            <img src="assets/images/logo-light.png" alt="MYCB">
        </div>
        <p class="hub-title">Hangi alana geçmek istersiniz?</p>

        <div class="hub-options">
            <a href="dashboard.php" class="hub-btn"><?php echo icon('truck'); ?> Araç Takip</a>
            <a href="teknoloji.php" class="hub-btn"><?php echo icon('cpu'); ?> Teknoloji</a>
        </div>
    </div>
</body>
</html>
