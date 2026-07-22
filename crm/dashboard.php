<?php
require_once 'config.php';
require_once 'partials/icons.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Filtreler
$product_model_filter = $_GET['product_model'] ?? 'all';
$simcard_operator_filter = $_GET['simcard_operator'] ?? 'all';
$simcard_company_filter = $_GET['simcard_company'] ?? 'all';

// Model listesi
$models = $conn->query("SELECT DISTINCT model FROM products ORDER BY model")->fetch_all(MYSQLI_ASSOC);

// Operatör listesi
$operators = $conn->query("SELECT DISTINCT operator FROM simcards ORDER BY operator")->fetch_all(MYSQLI_ASSOC);

// Şirket listesi
$companies = $conn->query("SELECT DISTINCT company FROM simcards ORDER BY company")->fetch_all(MYSQLI_ASSOC);

// 1. ÜRÜN STOK
$product_stock_sql = "SELECT COUNT(*) as total FROM products WHERE status = 'Stokta'";
if ($product_model_filter !== 'all') {
    $product_stock_sql .= " AND model = '" . $conn->real_escape_string($product_model_filter) . "'";
}
$product_stock = $conn->query($product_stock_sql)->fetch_assoc()['total'];

// 2. AKTİF ÜRÜNLER
$active_products_sql = "SELECT COUNT(*) as total FROM products WHERE status = 'Satıldı'";
if ($product_model_filter !== 'all') {
    $active_products_sql .= " AND model = '" . $conn->real_escape_string($product_model_filter) . "'";
}
$active_products = $conn->query($active_products_sql)->fetch_assoc()['total'];

// 3. STOK SIM KART
$simcard_stock_sql = "SELECT COUNT(*) as total FROM simcards WHERE status = 'Stokta'";
$where_conditions = [];
if ($simcard_operator_filter !== 'all') {
    $where_conditions[] = "operator = '" . $conn->real_escape_string($simcard_operator_filter) . "'";
}
if ($simcard_company_filter !== 'all') {
    $where_conditions[] = "company = '" . $conn->real_escape_string($simcard_company_filter) . "'";
}
if (!empty($where_conditions)) {
    $simcard_stock_sql .= " AND " . implode(" AND ", $where_conditions);
}
$simcard_stock = $conn->query($simcard_stock_sql)->fetch_assoc()['total'];

// 4. AKTİF SIM KART
// Aktif Sim Kart (Satılan sim kartlar)
$active_simcards = $conn->query("SELECT COUNT(*) as count FROM simcards WHERE status = 'Satıldı'")->fetch_assoc()['count'];


// 5. YAKLAŞAN ÜRÜN YENİLEMELERİ (30 gün)
$today = date('Y-m-d');
$upcoming_date = date('Y-m-d', strtotime('+30 days'));
$upcoming_products_sql = "SELECT COUNT(*) as total FROM subscriptions 
                          WHERE status = 'Aktif' 
                          AND item_type = 'product' 
                          AND renewal_date BETWEEN '$today' AND '$upcoming_date'";
if ($product_model_filter !== 'all') {
    $upcoming_products_sql .= " AND item_name = '" . $conn->real_escape_string($product_model_filter) . "'";
}
$upcoming_products = $conn->query($upcoming_products_sql)->fetch_assoc()['total'];

// 6. YAKLAŞAN SIM KART YENİLEMELERİ (30 gün)
$upcoming_simcards_sql = "SELECT COUNT(*) as total FROM subscriptions 
                          WHERE status = 'Aktif' 
                          AND item_type = 'simcard' 
                          AND renewal_date BETWEEN '$today' AND '$upcoming_date'";
$where_renewal = [];
if ($simcard_operator_filter !== 'all') {
    $where_renewal[] = "item_name = '" . $conn->real_escape_string($simcard_operator_filter) . "'";
}
if (!empty($where_renewal)) {
    $upcoming_simcards_sql .= " AND " . implode(" AND ", $where_renewal);
}
$upcoming_simcards = $conn->query($upcoming_simcards_sql)->fetch_assoc()['total'];

// DETAY VERİLERİ - ÜRÜN STOK DETAY
$product_stock_detail_sql = "SELECT model, COUNT(*) as count FROM products WHERE status = 'Stokta'";
if ($product_model_filter !== 'all') {
    $product_stock_detail_sql .= " AND model = '" . $conn->real_escape_string($product_model_filter) . "'";
}
$product_stock_detail_sql .= " GROUP BY model ORDER BY count DESC LIMIT 10";
$product_stock_detail = $conn->query($product_stock_detail_sql)->fetch_all(MYSQLI_ASSOC);

// DETAY VERİLERİ - AKTİF ÜRÜNLER DETAY
$active_products_detail_sql = "SELECT model, COUNT(*) as count FROM products WHERE status = 'Satıldı'";
if ($product_model_filter !== 'all') {
    $active_products_detail_sql .= " AND model = '" . $conn->real_escape_string($product_model_filter) . "'";
}
$active_products_detail_sql .= " GROUP BY model ORDER BY count DESC LIMIT 10";
$active_products_detail = $conn->query($active_products_detail_sql)->fetch_all(MYSQLI_ASSOC);

// DETAY VERİLERİ - SIM KART STOK DETAY
$simcard_stock_detail_sql = "SELECT operator, company, COUNT(*) as count FROM simcards WHERE status = 'Stokta'";
if (!empty($where_conditions)) {
    $simcard_stock_detail_sql .= " AND " . implode(" AND ", $where_conditions);
}
$simcard_stock_detail_sql .= " GROUP BY operator, company ORDER BY count DESC LIMIT 10";
$simcard_stock_detail = $conn->query($simcard_stock_detail_sql)->fetch_all(MYSQLI_ASSOC);

// DETAY VERİLERİ - AKTİF SIM KART DETAY
$active_simcards_detail_sql = "SELECT operator, company, COUNT(*) as count FROM simcards WHERE status = 'Satıldı'";
if (!empty($where_conditions)) {
    $active_simcards_detail_sql .= " AND " . implode(" AND ", $where_conditions);
}
$active_simcards_detail_sql .= " GROUP BY operator, company ORDER BY count DESC LIMIT 10";
$active_simcards_detail = $conn->query($active_simcards_detail_sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Dashboard - CRM</title>
</head>
<body>
    <?php $active_page = 'dashboard'; include 'partials/sidebar.php'; ?>

    <!-- Ana İçerik -->
    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1>Dashboard</h1>
                <p class="welcome">Hoş geldiniz, <?php echo htmlspecialchars($user_name); ?>!</p>
            </div>
        </div>

        <!-- Filtreler -->
        <form method="GET" class="filters">
            <div>
                <label>Ürün Modeli</label>
                <select name="product_model">
                    <option value="all" <?php echo $product_model_filter === 'all' ? 'selected' : ''; ?>>Tüm Modeller</option>
                    <?php foreach($models as $model): ?>
                        <option value="<?php echo htmlspecialchars($model['model']); ?>" <?php echo $product_model_filter === $model['model'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($model['model']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Operatör</label>
                <select name="simcard_operator">
                    <option value="all" <?php echo $simcard_operator_filter === 'all' ? 'selected' : ''; ?>>Tüm Operatörler</option>
                    <?php foreach($operators as $operator): ?>
                        <option value="<?php echo htmlspecialchars($operator['operator']); ?>" <?php echo $simcard_operator_filter === $operator['operator'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($operator['operator']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Şirket</label>
                <select name="simcard_company">
                    <option value="all" <?php echo $simcard_company_filter === 'all' ? 'selected' : ''; ?>>Tüm Şirketler</option>
                    <?php foreach($companies as $company): ?>
                        <option value="<?php echo htmlspecialchars($company['company']); ?>" <?php echo $simcard_company_filter === $company['company'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($company['company']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary"><?php echo icon('search'); ?> Filtrele</button>
            <a href="dashboard.php" class="btn btn-secondary"><?php echo icon('x'); ?> Temizle</a>
        </form>

        <!-- İstatistik Kartları -->
        <div class="stats-grid">
            <!-- 1. Ürün Stok -->
            <div class="stat-card blue">
                <div class="icon"><?php echo icon('package'); ?></div>
                <h3>Ürün Stok</h3>
                <div class="number"><?php echo $product_stock; ?></div>
                <small>Stoktaki cihaz sayısı</small>
            </div>

            <!-- 2. Aktif Ürünler -->
            <div class="stat-card green">
                <div class="icon"><?php echo icon('check'); ?></div>
                <h3>Aktif Ürünler</h3>
                <div class="number"><?php echo $active_products; ?></div>
                <small>Satılmış cihaz sayısı</small>
            </div>

            <!-- 3. Stok Sim Kart -->
            <div class="stat-card orange">
                <div class="icon"><?php echo icon('sim'); ?></div>
                <h3>Stok Sim Kart</h3>
                <div class="number"><?php echo $simcard_stock; ?></div>
                <small>Stoktaki sim kart sayısı</small>
            </div>

            <!-- 4. Aktif Sim Kart -->
            <div class="stat-card purple">
                <div class="icon"><?php echo icon('check'); ?></div>
                <h3>Aktif Sim Kart</h3>
                <div class="number"><?php echo $active_simcards; ?></div>
                <small>Satılmış sim kart sayısı</small>
            </div>

            <!-- 5. Yaklaşan Ürün Yenilemeleri -->
            <div class="stat-card red">
                <div class="icon"><?php echo icon('clock'); ?></div>
                <h3>Yaklaşan Ürün Yenileme</h3>
                <div class="number"><?php echo $upcoming_products; ?></div>
                <small>Son 30 gün içinde</small>
            </div>

            <!-- 6. Yaklaşan Sim Kart Yenilemeleri -->
            <div class="stat-card cyan">
                <div class="icon"><?php echo icon('bell'); ?></div>
                <h3>Yaklaşan Sim Yenileme</h3>
                <div class="number"><?php echo $upcoming_simcards; ?></div>
                <small>Son 30 gün içinde</small>
            </div>
        </div>

        <!-- Detay Kartları -->
        <div class="details-grid">
            <!-- Ürün Stok Detay -->
            <div class="detail-card">
                <h3><?php echo icon('package'); ?> Ürün Stok - Model Bazında</h3>
                <?php if (!empty($product_stock_detail)): ?>
                    <?php foreach($product_stock_detail as $item): ?>
                        <div class="detail-item">
                            <span><?php echo htmlspecialchars($item['model']); ?></span>
                            <span><?php echo $item['count']; ?> adet</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">Veri yok</div>
                <?php endif; ?>
            </div>

            <!-- Aktif Ürünler Detay -->
            <div class="detail-card">
                <h3><?php echo icon('check'); ?> Aktif Ürünler - Model Bazında</h3>
                <?php if (!empty($active_products_detail)): ?>
                    <?php foreach($active_products_detail as $item): ?>
                        <div class="detail-item">
                            <span><?php echo htmlspecialchars($item['model']); ?></span>
                            <span><?php echo $item['count']; ?> adet</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">Veri yok</div>
                <?php endif; ?>
            </div>

            <!-- Sim Kart Stok Detay -->
            <div class="detail-card">
                <h3><?php echo icon('sim'); ?> Stok Sim Kart - Operatör/Şirket</h3>
                <?php if (!empty($simcard_stock_detail)): ?>
                    <?php foreach($simcard_stock_detail as $item): ?>
                        <div class="detail-item">
                            <span><?php echo htmlspecialchars($item['operator']) . ' - ' . htmlspecialchars($item['company']); ?></span>
                            <span><?php echo $item['count']; ?> adet</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">Veri yok</div>
                <?php endif; ?>
            </div>

            <!-- Aktif Sim Kart Detay -->
            <div class="detail-card">
                <h3><?php echo icon('check'); ?> Aktif Sim Kart - Operatör/Şirket</h3>
                <?php if (!empty($active_simcards_detail)): ?>
                    <?php foreach($active_simcards_detail as $item): ?>
                        <div class="detail-item">
                            <span><?php echo htmlspecialchars($item['operator']) . ' - ' . htmlspecialchars($item['company']); ?></span>
                            <span><?php echo $item['count']; ?> adet</span>
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

