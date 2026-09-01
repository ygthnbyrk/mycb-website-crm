<?php
require_once 'config.php';
require_once 'partials/icons.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$palette = ['var(--accent)', 'var(--success)', 'var(--warning)', 'var(--purple)', 'var(--info)', 'var(--danger)'];

function svg_donut($segments, $size = 160, $stroke = 24) {
    $r = ($size - $stroke) / 2;
    $cx = $size / 2;
    $cy = $size / 2;
    $circumference = 2 * M_PI * $r;
    $total = 0;
    foreach ($segments as $s) $total += $s['value'];
    $svg = '<svg class="donut-chart" viewBox="0 0 ' . $size . ' ' . $size . '">';
    if ($total <= 0) {
        $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="var(--bg-hover)" stroke-width="' . $stroke . '"/>';
    } else {
        $svg .= '<g transform="rotate(-90 ' . $cx . ' ' . $cy . ')">';
        $offset = 0;
        foreach ($segments as $seg) {
            $frac = $seg['value'] / $total;
            $dash = $frac * $circumference;
            $gap = $circumference - $dash;
            $title = htmlspecialchars($seg['label']) . ': ' . $seg['value'] . ' (%' . round($frac * 100) . ')';
            $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="' . $seg['color'] . '" stroke-width="' . $stroke . '" stroke-dasharray="' . round($dash, 2) . ' ' . round($gap, 2) . '" stroke-dashoffset="-' . round($offset, 2) . '"><title>' . $title . '</title></circle>';
            $offset += $dash;
        }
        $svg .= '</g>';
    }
    $svg .= '<text x="' . $cx . '" y="' . ($cy - 3) . '" text-anchor="middle" font-size="19" font-weight="700" fill="var(--text-primary)">' . number_format($total, 0, ',', '.') . '</text>';
    $svg .= '<text x="' . $cx . '" y="' . ($cy + 15) . '" text-anchor="middle" font-size="10.5" fill="var(--text-muted)">toplam</text>';
    $svg .= '</svg>';
    return $svg;
}

// Ham satırları (ör. [model => x, count => y]) en fazla 6 dilim + "Diğer" olacak şekilde donut segmentine çevirir
function rows_to_segments($rows, $label_fn, $palette) {
    $segments = [];
    $other = 0;
    foreach ($rows as $i => $row) {
        if ($i < 6) {
            $segments[] = ['label' => $label_fn($row), 'value' => (int)$row['count'], 'color' => $palette[$i % count($palette)]];
        } else {
            $other += (int)$row['count'];
        }
    }
    if ($other > 0) {
        $segments[] = ['label' => 'Diğer', 'value' => $other, 'color' => 'var(--text-muted)'];
    }
    return $segments;
}

// Filtreler
$product_model_filter = $_GET['product_model'] ?? 'all';
$simcard_operator_filter = $_GET['simcard_operator'] ?? 'all';
$simcard_company_filter = $_GET['simcard_company'] ?? 'all';
$has_active_filter = $product_model_filter !== 'all' || $simcard_operator_filter !== 'all' || $simcard_company_filter !== 'all';

// Model listesi (sadece araç takip/Telematik cihazları — Kamera/Aksesuar/Hizmet Teknoloji tarafında)
$models = $conn->query("SELECT DISTINCT model FROM products WHERE category = 'Telematik' ORDER BY model")->fetch_all(MYSQLI_ASSOC);

// Operatör listesi
$operators = $conn->query("SELECT DISTINCT operator FROM simcards ORDER BY operator")->fetch_all(MYSQLI_ASSOC);

// Şirket listesi
$companies = $conn->query("SELECT DISTINCT company FROM simcards ORDER BY company")->fetch_all(MYSQLI_ASSOC);

// 1. ÜRÜN STOK
$product_stock_sql = "SELECT COUNT(*) as total FROM products WHERE status = 'Stokta' AND category = 'Telematik'";
if ($product_model_filter !== 'all') {
    $product_stock_sql .= " AND model = '" . $conn->real_escape_string($product_model_filter) . "'";
}
$product_stock = $conn->query($product_stock_sql)->fetch_assoc()['total'];

// 2. AKTİF ÜRÜNLER
$active_products_sql = "SELECT COUNT(*) as total FROM products WHERE status = 'Satıldı' AND category = 'Telematik'";
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
// product_id üzerinden products.category = 'Telematik' ile eşleştiriyoruz ki Teknoloji
// tarafının (Kamera Satışı) oluşturduğu abonelikler bu araç takip KPI'sine karışmasın.
$today = date('Y-m-d');
$upcoming_date = date('Y-m-d', strtotime('+30 days'));
$upcoming_products_sql = "SELECT COUNT(*) as total FROM subscriptions sub
                          INNER JOIN products p ON p.id = sub.product_id AND p.category = 'Telematik'
                          WHERE sub.status = 'Aktif'
                          AND sub.item_type = 'product'
                          AND sub.renewal_date BETWEEN '$today' AND '$upcoming_date'";
if ($product_model_filter !== 'all') {
    $upcoming_products_sql .= " AND sub.item_name = '" . $conn->real_escape_string($product_model_filter) . "'";
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
$product_stock_detail_sql = "SELECT model, COUNT(*) as count FROM products WHERE status = 'Stokta' AND category = 'Telematik'";
if ($product_model_filter !== 'all') {
    $product_stock_detail_sql .= " AND model = '" . $conn->real_escape_string($product_model_filter) . "'";
}
$product_stock_detail_sql .= " GROUP BY model ORDER BY count DESC";
$product_stock_detail = $conn->query($product_stock_detail_sql)->fetch_all(MYSQLI_ASSOC);

// DETAY VERİLERİ - AKTİF ÜRÜNLER DETAY
$active_products_detail_sql = "SELECT model, COUNT(*) as count FROM products WHERE status = 'Satıldı' AND category = 'Telematik'";
if ($product_model_filter !== 'all') {
    $active_products_detail_sql .= " AND model = '" . $conn->real_escape_string($product_model_filter) . "'";
}
$active_products_detail_sql .= " GROUP BY model ORDER BY count DESC";
$active_products_detail = $conn->query($active_products_detail_sql)->fetch_all(MYSQLI_ASSOC);

// DETAY VERİLERİ - SIM KART STOK DETAY
$simcard_stock_detail_sql = "SELECT operator, company, COUNT(*) as count FROM simcards WHERE status = 'Stokta'";
if (!empty($where_conditions)) {
    $simcard_stock_detail_sql .= " AND " . implode(" AND ", $where_conditions);
}
$simcard_stock_detail_sql .= " GROUP BY operator, company ORDER BY count DESC";
$simcard_stock_detail = $conn->query($simcard_stock_detail_sql)->fetch_all(MYSQLI_ASSOC);

// DETAY VERİLERİ - AKTİF SIM KART DETAY
$active_simcards_detail_sql = "SELECT operator, company, COUNT(*) as count FROM simcards WHERE status = 'Satıldı'";
if (!empty($where_conditions)) {
    $active_simcards_detail_sql .= " AND " . implode(" AND ", $where_conditions);
}
$active_simcards_detail_sql .= " GROUP BY operator, company ORDER BY count DESC";
$active_simcards_detail = $conn->query($active_simcards_detail_sql)->fetch_all(MYSQLI_ASSOC);

// Donut segmentleri
$product_stock_segments = rows_to_segments($product_stock_detail, fn($r) => $r['model'], $palette);
$active_products_segments = rows_to_segments($active_products_detail, fn($r) => $r['model'], $palette);
$simcard_stock_segments = rows_to_segments($simcard_stock_detail, fn($r) => $r['operator'] . ' - ' . $r['company'], $palette);
$active_simcards_segments = rows_to_segments($active_simcards_detail, fn($r) => $r['operator'] . ' - ' . $r['company'], $palette);

// Envanter oranı donut'ları (stokta / satıldı kıyası)
$product_ratio_segments = [
    ['label' => 'Stokta', 'value' => (int)$product_stock, 'color' => 'var(--accent)'],
    ['label' => 'Satıldı', 'value' => (int)$active_products, 'color' => 'var(--success)'],
];
$simcard_ratio_segments = [
    ['label' => 'Stokta', 'value' => (int)$simcard_stock, 'color' => 'var(--warning)'],
    ['label' => 'Satıldı', 'value' => (int)$active_simcards, 'color' => 'var(--purple)'],
];

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
                <h1><?php echo icon('home'); ?> Dashboard</h1>
                <p class="welcome">Hoş geldiniz, <?php echo htmlspecialchars($user_name); ?>!</p>
            </div>
        </div>

        <!-- Filtreler -->
        <div class="action-bar">
            <form method="GET">
                <div class="search-filter-row">
                    <div class="filter-group">
                        <label>Ürün Modeli</label>
                        <select name="product_model" class="filter-select">
                            <option value="all" <?php echo $product_model_filter === 'all' ? 'selected' : ''; ?>>Tüm Modeller</option>
                            <?php foreach ($models as $model): ?>
                                <option value="<?php echo htmlspecialchars($model['model']); ?>" <?php echo $product_model_filter === $model['model'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($model['model']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Operatör</label>
                        <select name="simcard_operator" class="filter-select">
                            <option value="all" <?php echo $simcard_operator_filter === 'all' ? 'selected' : ''; ?>>Tüm Operatörler</option>
                            <?php foreach ($operators as $operator): ?>
                                <option value="<?php echo htmlspecialchars($operator['operator']); ?>" <?php echo $simcard_operator_filter === $operator['operator'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($operator['operator']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Şirket</label>
                        <select name="simcard_company" class="filter-select">
                            <option value="all" <?php echo $simcard_company_filter === 'all' ? 'selected' : ''; ?>>Tüm Şirketler</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo htmlspecialchars($company['company']); ?>" <?php echo $simcard_company_filter === $company['company'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($company['company']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary"><?php echo icon('filter'); ?> Filtrele</button>
                    <?php if ($has_active_filter): ?>
                        <a href="dashboard.php" class="btn btn-secondary"><?php echo icon('x'); ?> Temizle</a>
                    <?php endif; ?>
                </div>

                <?php if ($has_active_filter): ?>
                    <div class="active-filters">
                        <strong style="color: var(--text-secondary); font-size: 13px;">Aktif Filtreler:</strong>
                        <?php if ($product_model_filter !== 'all'): ?>
                            <div class="filter-tag">
                                Model: <?php echo htmlspecialchars($product_model_filter); ?>
                                <span class="remove" onclick="removeFilter('product_model')">×</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($simcard_operator_filter !== 'all'): ?>
                            <div class="filter-tag">
                                Operatör: <?php echo htmlspecialchars($simcard_operator_filter); ?>
                                <span class="remove" onclick="removeFilter('simcard_operator')">×</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($simcard_company_filter !== 'all'): ?>
                            <div class="filter-tag">
                                Şirket: <?php echo htmlspecialchars($simcard_company_filter); ?>
                                <span class="remove" onclick="removeFilter('simcard_company')">×</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- İstatistik Kartları -->
        <div class="stats-grid">
            <!-- 1. Ürün Stok -->
            <a href="products.php?status=Stokta" class="stat-card blue">
                <div class="icon"><?php echo icon('package'); ?></div>
                <h3>Ürün Stok</h3>
                <div class="number"><?php echo $product_stock; ?></div>
                <small>Stoktaki cihaz sayısı</small>
            </a>

            <!-- 2. Aktif Ürünler -->
            <a href="products.php?status=Satıldı" class="stat-card green">
                <div class="icon"><?php echo icon('check'); ?></div>
                <h3>Aktif Ürünler</h3>
                <div class="number"><?php echo $active_products; ?></div>
                <small>Satılmış cihaz sayısı</small>
            </a>

            <!-- 3. Stok Sim Kart -->
            <a href="simcards.php?status=Stokta" class="stat-card orange">
                <div class="icon"><?php echo icon('sim'); ?></div>
                <h3>Stok Sim Kart</h3>
                <div class="number"><?php echo $simcard_stock; ?></div>
                <small>Stoktaki sim kart sayısı</small>
            </a>

            <!-- 4. Aktif Sim Kart -->
            <a href="simcards.php?status=Satıldı" class="stat-card purple">
                <div class="icon"><?php echo icon('check'); ?></div>
                <h3>Aktif Sim Kart</h3>
                <div class="number"><?php echo $active_simcards; ?></div>
                <small>Satılmış sim kart sayısı</small>
            </a>

            <!-- 5. Yaklaşan Ürün Yenilemeleri -->
            <a href="subscriptions.php?filter=upcoming&type=product" class="stat-card red">
                <div class="icon"><?php echo icon('clock'); ?></div>
                <h3>Yaklaşan Ürün Yenileme</h3>
                <div class="number"><?php echo $upcoming_products; ?></div>
                <small>Önümüzdeki 30 gün içinde</small>
            </a>

            <!-- 6. Yaklaşan Sim Kart Yenilemeleri -->
            <a href="subscriptions.php?filter=upcoming&type=simcard" class="stat-card cyan">
                <div class="icon"><?php echo icon('bell'); ?></div>
                <h3>Yaklaşan Sim Yenileme</h3>
                <div class="number"><?php echo $upcoming_simcards; ?></div>
                <small>Önümüzdeki 30 gün içinde</small>
            </a>
        </div>

        <!-- Envanter Oranı -->
        <div class="details-grid" style="margin-bottom:16px;">
            <div class="detail-card">
                <h3><?php echo icon('package'); ?> Ürün Envanteri — Stok / Satış Oranı</h3>
                <?php if ($product_stock + $active_products > 0): ?>
                    <div class="donut-row">
                        <?php echo svg_donut($product_ratio_segments); ?>
                        <div class="donut-legend">
                            <?php foreach ($product_ratio_segments as $seg): ?>
                                <div class="legend-item">
                                    <span class="legend-dot" style="background:<?php echo $seg['color']; ?>"></span>
                                    <span class="legend-label"><?php echo htmlspecialchars($seg['label']); ?></span>
                                    <span class="legend-value"><?php echo $seg['value']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-data">Veri yok</div>
                <?php endif; ?>
            </div>

            <div class="detail-card">
                <h3><?php echo icon('sim'); ?> Sim Kart Envanteri — Stok / Satış Oranı</h3>
                <?php if ($simcard_stock + $active_simcards > 0): ?>
                    <div class="donut-row">
                        <?php echo svg_donut($simcard_ratio_segments); ?>
                        <div class="donut-legend">
                            <?php foreach ($simcard_ratio_segments as $seg): ?>
                                <div class="legend-item">
                                    <span class="legend-dot" style="background:<?php echo $seg['color']; ?>"></span>
                                    <span class="legend-label"><?php echo htmlspecialchars($seg['label']); ?></span>
                                    <span class="legend-value"><?php echo $seg['value']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-data">Veri yok</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Detay Kartları -->
        <div class="details-grid">
            <!-- Ürün Stok Detay -->
            <div class="detail-card">
                <h3><?php echo icon('package'); ?> Ürün Stok - Model Bazında</h3>
                <?php if (!empty($product_stock_segments)): ?>
                    <div class="donut-row">
                        <?php echo svg_donut($product_stock_segments); ?>
                        <div class="donut-legend">
                            <?php foreach ($product_stock_segments as $seg): ?>
                                <div class="legend-item">
                                    <span class="legend-dot" style="background:<?php echo $seg['color']; ?>"></span>
                                    <span class="legend-label"><?php echo htmlspecialchars($seg['label']); ?></span>
                                    <span class="legend-value"><?php echo $seg['value']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-data">Veri yok</div>
                <?php endif; ?>
            </div>

            <!-- Aktif Ürünler Detay -->
            <div class="detail-card">
                <h3><?php echo icon('check'); ?> Aktif Ürünler - Model Bazında</h3>
                <?php if (!empty($active_products_segments)): ?>
                    <div class="donut-row">
                        <?php echo svg_donut($active_products_segments); ?>
                        <div class="donut-legend">
                            <?php foreach ($active_products_segments as $seg): ?>
                                <div class="legend-item">
                                    <span class="legend-dot" style="background:<?php echo $seg['color']; ?>"></span>
                                    <span class="legend-label"><?php echo htmlspecialchars($seg['label']); ?></span>
                                    <span class="legend-value"><?php echo $seg['value']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-data">Veri yok</div>
                <?php endif; ?>
            </div>

            <!-- Sim Kart Stok Detay -->
            <div class="detail-card">
                <h3><?php echo icon('sim'); ?> Stok Sim Kart - Operatör/Şirket</h3>
                <?php if (!empty($simcard_stock_segments)): ?>
                    <div class="donut-row">
                        <?php echo svg_donut($simcard_stock_segments); ?>
                        <div class="donut-legend">
                            <?php foreach ($simcard_stock_segments as $seg): ?>
                                <div class="legend-item">
                                    <span class="legend-dot" style="background:<?php echo $seg['color']; ?>"></span>
                                    <span class="legend-label"><?php echo htmlspecialchars($seg['label']); ?></span>
                                    <span class="legend-value"><?php echo $seg['value']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-data">Veri yok</div>
                <?php endif; ?>
            </div>

            <!-- Aktif Sim Kart Detay -->
            <div class="detail-card">
                <h3><?php echo icon('check'); ?> Aktif Sim Kart - Operatör/Şirket</h3>
                <?php if (!empty($active_simcards_segments)): ?>
                    <div class="donut-row">
                        <?php echo svg_donut($active_simcards_segments); ?>
                        <div class="donut-legend">
                            <?php foreach ($active_simcards_segments as $seg): ?>
                                <div class="legend-item">
                                    <span class="legend-dot" style="background:<?php echo $seg['color']; ?>"></span>
                                    <span class="legend-label"><?php echo htmlspecialchars($seg['label']); ?></span>
                                    <span class="legend-value"><?php echo $seg['value']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-data">Veri yok</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function removeFilter(filterType) {
            const url = new URL(window.location.href);
            url.searchParams.delete(filterType);
            window.location.href = url.toString();
        }
    </script>
</body>
</html>
