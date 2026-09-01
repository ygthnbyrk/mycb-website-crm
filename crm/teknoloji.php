<?php
require_once 'config.php';
require_once 'partials/icons.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_name = $_SESSION['user_name'];

$teknoloji_categories = ['Kamera', 'Aksesuar', 'Hizmet'];
$category_placeholders = implode(',', array_fill(0, count($teknoloji_categories), '?'));

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM products WHERE category IN ($category_placeholders) AND status = 'Stokta'");
$stmt->bind_param(str_repeat('s', count($teknoloji_categories)), ...$teknoloji_categories);
$stmt->execute();
$stock_count = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$sale_count = $conn->query("SELECT COUNT(*) as c FROM camera_sales")->fetch_assoc()['c'];
// Not: subscriptions.sale_id, camera_sales/sales tabloları arasında paylaşılan (tip
// ayrımı olmayan) bir alan — kamera abonelik sayısını subscriptions'a JOIN ile değil,
// doğrudan camera_sale_items'tan (her kalem = bir abonelik) alıyoruz, id çakışma riski olmasın.
$sub_count = $conn->query("SELECT COUNT(*) as c FROM camera_sale_items")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Teknoloji - CRM</title>
</head>
<body>
    <?php $active_page = 'teknoloji'; include 'partials/sidebar-teknoloji.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1><?php echo icon('cpu'); ?> Teknoloji</h1>
                <p class="welcome">Hoş geldiniz, <?php echo htmlspecialchars($user_name); ?> — kamera, aksesuar ve hizmet satışları burada.</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="icon"><?php echo icon('package'); ?></div>
                <h3>Stoktaki Ürün</h3>
                <div class="number"><?php echo $stock_count; ?></div>
                <small>Kamera / Aksesuar / Hizmet</small>
            </div>
            <div class="stat-card green">
                <div class="icon"><?php echo icon('dollar'); ?></div>
                <h3>Kamera Satışı</h3>
                <div class="number"><?php echo $sale_count; ?></div>
                <small>Toplam kayıt</small>
            </div>
            <div class="stat-card purple">
                <div class="icon"><?php echo icon('refresh'); ?></div>
                <h3>Abonelik</h3>
                <div class="number"><?php echo $sub_count; ?></div>
                <small>2 yıllık takip</small>
            </div>
        </div>

        <div class="report-catalog">
            <a href="customers.php" class="report-card" style="text-decoration:none;">
                <div class="icon"><?php echo icon('users'); ?></div>
                <h4>Müşteriler</h4>
                <p>Firma bilgileri — Araç Takip ile aynı, ortak müşteri kaydı.</p>
            </a>
            <a href="teknoloji-urunler.php" class="report-card" style="text-decoration:none;">
                <div class="icon"><?php echo icon('cpu'); ?></div>
                <h4>Ürünler</h4>
                <p>Kamera, aksesuar ve hizmet kataloğunu yönetin.</p>
            </a>
            <a href="create-camera-sale.php" class="report-card" style="text-decoration:none;">
                <div class="icon"><?php echo icon('dollar'); ?></div>
                <h4>Kamera Satışı</h4>
                <p>Yeni bir kamera/aksesuar/hizmet satışı kaydedin.</p>
            </a>
            <a href="camera-sales-list.php" class="report-card" style="text-decoration:none;">
                <div class="icon"><?php echo icon('list'); ?></div>
                <h4>Kamera Satış Listesi</h4>
                <p>Geçmiş kamera satışlarını görüntüleyin.</p>
            </a>
        </div>
    </div>
</body>
</html>
