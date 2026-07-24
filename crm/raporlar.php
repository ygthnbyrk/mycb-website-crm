<?php
require_once 'config.php';
require_once 'partials/icons.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: hub.php');
    exit();
}

$user_name = $_SESSION['user_name'];

// Aylik satis trendi (son 12 ay)
$trend_sql = "SELECT DATE_FORMAT(sale_date, '%Y-%m') as ym, COUNT(*) as sale_count, SUM(total) as revenue
              FROM sales
              GROUP BY ym
              ORDER BY ym DESC
              LIMIT 12";
$trend = $conn->query($trend_sql)->fetch_all(MYSQLI_ASSOC);
$trend = array_reverse($trend);
$max_revenue = 0;
foreach ($trend as $t) {
    $max_revenue = max($max_revenue, (float)$t['revenue']);
}

$ay_isimleri = [
    '01' => 'Ocak', '02' => 'Şubat', '03' => 'Mart', '04' => 'Nisan',
    '05' => 'Mayıs', '06' => 'Haziran', '07' => 'Temmuz', '08' => 'Ağustos',
    '09' => 'Eylül', '10' => 'Ekim', '11' => 'Kasım', '12' => 'Aralık'
];

// En cok satan modeller
$top_models_sql = "SELECT model, COUNT(*) as qty, SUM(price) as revenue
                    FROM sale_products
                    GROUP BY model
                    ORDER BY qty DESC
                    LIMIT 10";
$top_models = $conn->query($top_models_sql)->fetch_all(MYSQLI_ASSOC);
$max_model_qty = 0;
foreach ($top_models as $m) {
    $max_model_qty = max($max_model_qty, (int)$m['qty']);
}

// Genel toplamlar
$totals = $conn->query("SELECT COUNT(*) as sale_count, SUM(total) as revenue FROM sales")->fetch_assoc();
$total_products_sold = $conn->query("SELECT COUNT(*) as c FROM sale_products")->fetch_assoc()['c'];
$total_simcards_sold = $conn->query("SELECT COUNT(*) as c FROM sale_simcards")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Raporlar - CRM</title>
</head>
<body>
    <?php $active_page = 'raporlar'; include 'partials/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1>Raporlar</h1>
                <p class="welcome">Hoş geldiniz, <?php echo htmlspecialchars($user_name); ?>!</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="icon"><?php echo icon('dollar'); ?></div>
                <h3>Toplam Satış</h3>
                <div class="number"><?php echo number_format((float)$totals['revenue'], 0, ',', '.'); ?> ₺</div>
                <small><?php echo (int)$totals['sale_count']; ?> satış işlemi</small>
            </div>
            <div class="stat-card green">
                <div class="icon"><?php echo icon('package'); ?></div>
                <h3>Satılan Cihaz</h3>
                <div class="number"><?php echo (int)$total_products_sold; ?></div>
                <small>Toplam ürün adedi</small>
            </div>
            <div class="stat-card orange">
                <div class="icon"><?php echo icon('sim'); ?></div>
                <h3>Satılan Sim Kart</h3>
                <div class="number"><?php echo (int)$total_simcards_sold; ?></div>
                <small>Toplam sim kart adedi</small>
            </div>
        </div>

        <div class="details-grid">
            <div class="detail-card">
                <h3><?php echo icon('chart'); ?> Aylık Satış Trendi (Son 12 Ay)</h3>
                <?php if (!empty($trend)): ?>
                    <?php foreach ($trend as $t):
                        $pct = $max_revenue > 0 ? round(((float)$t['revenue'] / $max_revenue) * 100) : 0;
                        [$yil, $ay] = explode('-', $t['ym']);
                        $label = ($ay_isimleri[$ay] ?? $ay) . ' ' . $yil;
                    ?>
                        <div class="trend-row">
                            <div class="trend-row-head">
                                <span><?php echo $label; ?> · <?php echo (int)$t['sale_count']; ?> satış</span>
                                <span><?php echo number_format((float)$t['revenue'], 0, ',', '.'); ?> ₺</span>
                            </div>
                            <div class="trend-track"><div class="trend-fill" style="width: <?php echo $pct; ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">Veri yok</div>
                <?php endif; ?>
            </div>

            <div class="detail-card">
                <h3><?php echo icon('package'); ?> En Çok Satan Modeller</h3>
                <?php if (!empty($top_models)): ?>
                    <?php foreach ($top_models as $m):
                        $pct = $max_model_qty > 0 ? round(((int)$m['qty'] / $max_model_qty) * 100) : 0;
                    ?>
                        <div class="trend-row">
                            <div class="trend-row-head">
                                <span><?php echo htmlspecialchars($m['model']); ?></span>
                                <span><?php echo (int)$m['qty']; ?> adet</span>
                            </div>
                            <div class="trend-track"><div class="trend-fill" style="width: <?php echo $pct; ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">Veri yok</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
