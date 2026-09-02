<?php
require_once 'config.php';
require_once 'pagination.php';
require_once 'partials/icons.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_name = $_SESSION['user_name'];
$search = trim($_GET['search'] ?? '');
$year = $_GET['year'] ?? '';
$month = $_GET['month'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

$months = [
    1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
    5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
    9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
];

$years_result = $conn->query("SELECT DISTINCT YEAR(sale_date) as year FROM camera_sales ORDER BY year DESC");
$years = [];
while ($row = $years_result->fetch_assoc()) {
    $years[] = $row['year'];
}

// Ortak WHERE (arama + yıl/ay filtresi) - sayım, KPI ve ana sorguda aynı şekilde kullanılır
$where = "WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $where .= " AND (c.name LIKE ? OR cs.id IN (SELECT camera_sale_id FROM camera_sale_items WHERE item_name LIKE ?))";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

if (!empty($year)) {
    $where .= " AND YEAR(cs.sale_date) = ?";
    $params[] = $year;
    $types .= 'i';
}

if (!empty($month)) {
    $where .= " AND MONTH(cs.sale_date) = ?";
    $params[] = $month;
    $types .= 'i';
}

// Toplam satış sayısı (sayfalama için)
$count_sql = "SELECT COUNT(*) as total FROM camera_sales cs JOIN customers c ON cs.customer_id = c.id $where";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_sales = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_sales / $per_page);
$stmt->close();

// KPI: filtreye uyan satışların kalemleri üzerinden maliyet/satış/kâr
$kpi_sql = "SELECT COALESCE(SUM(csi.cost_price * csi.quantity), 0) as total_cost,
                   COALESCE(SUM(csi.sale_price * csi.quantity), 0) as total_revenue
            FROM camera_sales cs
            JOIN customers c ON cs.customer_id = c.id
            LEFT JOIN camera_sale_items csi ON csi.camera_sale_id = cs.id
            $where";
$stmt = $conn->prepare($kpi_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$kpi = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total_cost = (float)$kpi['total_cost'];
$total_revenue = (float)$kpi['total_revenue'];
$total_profit = $total_revenue - $total_cost;

// Sayfalanmış satış listesi (bir satış = bir satır)
$sql = "SELECT cs.id, cs.sale_date, cs.notes, c.name as customer_name, c.tax_number
        FROM camera_sales cs
        JOIN customers c ON cs.customer_id = c.id
        $where
        ORDER BY cs.sale_date DESC, cs.id DESC
        LIMIT ? OFFSET ?";
$list_params = $params;
$list_params[] = $per_page;
$list_params[] = $offset;
$list_types = $types . 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($list_types, ...$list_params);
$stmt->execute();
$sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Her satışın kalemlerini tek seferde çekip sale_id'ye göre grupla
$items_by_sale = [];
if (!empty($sales)) {
    $sale_ids = array_column($sales, 'id');
    $id_placeholders = implode(',', array_fill(0, count($sale_ids), '?'));
    $items_types = str_repeat('i', count($sale_ids));

    $items_stmt = $conn->prepare(
        "SELECT csi.camera_sale_id, csi.item_name, csi.category, csi.cost_price, csi.sale_price, csi.quantity,
                p.product_name as registered_name
         FROM camera_sale_items csi
         LEFT JOIN products p ON p.id = csi.product_id
         WHERE csi.camera_sale_id IN ($id_placeholders)
         ORDER BY csi.id"
    );
    $items_stmt->bind_param($items_types, ...$sale_ids);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    while ($it = $items_result->fetch_assoc()) {
        $items_by_sale[$it['camera_sale_id']][] = $it;
    }
    $items_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Kamera Satış Listesi - CRM</title>
</head>
<body>
    <?php $active_page = 'camera-sales-list'; include 'partials/sidebar-teknoloji.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h1><?php echo icon('list'); ?> Kamera Satış Listesi</h1>
            <a href="create-camera-sale.php" class="btn btn-primary"><?php echo icon('plus'); ?> Yeni Kamera Satışı</a>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="icon"><?php echo icon('cpu'); ?></div>
                <h3>Toplam Satış</h3>
                <div class="number"><?php echo $total_sales; ?></div>
            </div>
            <div class="stat-card green">
                <div class="icon"><?php echo icon('dollar'); ?></div>
                <h3>Toplam Maliyet</h3>
                <div class="number"><?php echo number_format($total_cost, 0, ',', '.'); ?> ₺</div>
            </div>
            <div class="stat-card blue">
                <div class="icon"><?php echo icon('dollar'); ?></div>
                <h3>Toplam Satış</h3>
                <div class="number"><?php echo number_format($total_revenue, 0, ',', '.'); ?> ₺</div>
            </div>
            <div class="stat-card green">
                <div class="icon"><?php echo icon('dollar'); ?></div>
                <h3>Kâr</h3>
                <div class="number"><?php echo number_format($total_profit, 0, ',', '.'); ?> ₺</div>
            </div>
        </div>

        <form method="get" class="filter-row" style="margin-bottom: 16px;">
            <input type="text" name="search" class="search-input" value="<?php echo htmlspecialchars($search); ?>" placeholder="Müşteri veya kalem adı ara...">
            <select name="year" onchange="this.form.submit()">
                <option value="">Tüm Yıllar</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?php echo $y; ?>" <?php echo (string)$year === (string)$y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endforeach; ?>
            </select>
            <select name="month" onchange="this.form.submit()">
                <option value="">Tüm Aylar</option>
                <?php foreach ($months as $m_num => $m_name): ?>
                    <option value="<?php echo $m_num; ?>" <?php echo (string)$month === (string)$m_num ? 'selected' : ''; ?>><?php echo $m_name; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary"><?php echo icon('search'); ?> Ara</button>
            <?php if ($search !== '' || $year !== '' || $month !== ''): ?>
                <a href="camera-sales-list.php" class="btn btn-secondary"><?php echo icon('x'); ?> Temizle</a>
            <?php endif; ?>
        </form>

        <?php if (empty($sales)): ?>
            <div class="no-data">Henüz kamera satışı kaydedilmedi.</div>
        <?php else: ?>
            <div class="table-wrapper">
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Müşteri</th>
                                <th>Kalemler</th>
                                <th style="text-align:right;">Maliyet</th>
                                <th style="text-align:right;">Satış Fiyatı</th>
                                <th style="text-align:right;">Kâr</th>
                                <th style="text-align:right;">Adet</th>
                                <th style="text-align:center;">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $sale): ?>
                                <?php
                                    $sale_items = $items_by_sale[$sale['id']] ?? [];
                                    $sale_cost = 0;
                                    $sale_revenue = 0;
                                    $sale_qty = 0;
                                    foreach ($sale_items as $it) {
                                        $qty = (int)$it['quantity'];
                                        $sale_cost += (float)$it['cost_price'] * $qty;
                                        $sale_revenue += (float)$it['sale_price'] * $qty;
                                        $sale_qty += $qty;
                                    }
                                    $sale_profit = $sale_revenue - $sale_cost;

                                    // Aynı ürün (adet>1 satışlarda stok takibi için ayrı satırlarda tutulur)
                                    // görüntüde tek satırda "×N" olarak birleştirilir.
                                    $display_items = [];
                                    foreach ($sale_items as $it) {
                                        $key = $it['item_name'] . '|' . ($it['category'] ?? '') . '|' . $it['cost_price'] . '|' . $it['sale_price'] . '|' . ($it['registered_name'] ?? '');
                                        if (isset($display_items[$key])) {
                                            $display_items[$key]['quantity'] += (int)$it['quantity'];
                                        } else {
                                            $display_items[$key] = $it;
                                            $display_items[$key]['quantity'] = (int)$it['quantity'];
                                        }
                                    }
                                ?>
                                <tr>
                                    <td style="white-space: nowrap;"><?php echo date('d.m.Y', strtotime($sale['sale_date'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($sale['customer_name']); ?></strong><br>
                                        <small style="color: var(--text-muted);"><?php echo htmlspecialchars($sale['tax_number']); ?></small>
                                    </td>
                                    <td>
                                        <?php if (empty($display_items)): ?>
                                            <span style="color:var(--text-muted);">—</span>
                                        <?php else: ?>
                                            <div class="item-mini-list">
                                                <?php foreach ($display_items as $it): ?>
                                                    <div class="item-mini">
                                                        <span class="item-badge"><?php echo htmlspecialchars($it['category'] ?: 'Kalem'); ?></span>
                                                        <strong><?php echo htmlspecialchars($it['item_name']); ?></strong>
                                                        <?php if (!empty($it['registered_name']) && $it['registered_name'] !== $it['item_name']): ?>
                                                            <small style="color: var(--text-muted);">(<?php echo htmlspecialchars($it['registered_name']); ?>)</small>
                                                        <?php endif; ?>
                                                        <small style="color: var(--text-secondary);">
                                                            ×<?php echo (int)$it['quantity']; ?>
                                                            • Maliyet: ₺<?php echo number_format((float)$it['cost_price'], 2, ',', '.'); ?>
                                                            • Satış: ₺<?php echo number_format((float)$it['sale_price'], 2, ',', '.'); ?>
                                                        </small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;"><?php echo number_format($sale_cost, 2, ',', '.'); ?> ₺</td>
                                    <td style="text-align:right;"><?php echo number_format($sale_revenue, 2, ',', '.'); ?> ₺</td>
                                    <td style="text-align:right;"><?php echo number_format($sale_profit, 2, ',', '.'); ?> ₺</td>
                                    <td style="text-align:right;"><?php echo $sale_qty; ?></td>
                                    <td style="text-align:center;">
                                        <div class="action-btns">
                                            <button onclick="window.location.href='edit-camera-sale.php?id=<?php echo $sale['id']; ?>'" class="icon-btn btn-edit" title="Düzenle"><?php echo icon('edit'); ?></button>
                                            <button onclick="if(confirm('Bu kamera satışını silmek istediğinizden emin misiniz? Bağlı ürünler stoğa geri eklenecek.')) window.location.href='delete-camera-sale.php?id=<?php echo $sale['id']; ?>'" class="icon-btn btn-delete" title="Sil"><?php echo icon('trash'); ?></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php
            echo renderPagination($page, $total_pages, 'camera-sales-list.php', [
                'search' => $search,
                'year' => $year,
                'month' => $month,
            ]);
            ?>
        <?php endif; ?>
    </div>
</body>
</html>
