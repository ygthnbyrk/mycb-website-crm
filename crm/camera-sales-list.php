<?php
require_once 'config.php';
require_once 'partials/icons.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_name = $_SESSION['user_name'];
$search = trim($_GET['search'] ?? '');

$sql = "SELECT cs.id as sale_id, cs.sale_date, cs.notes, c.name as customer_name, c.tax_number,
               csi.item_name, csi.category, csi.cost_price, csi.quantity
        FROM camera_sales cs
        JOIN customers c ON cs.customer_id = c.id
        LEFT JOIN camera_sale_items csi ON csi.camera_sale_id = cs.id
        WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (c.name LIKE ? OR csi.item_name LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

$sql .= " ORDER BY cs.sale_date DESC, cs.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_sales = count(array_unique(array_column($rows, 'sale_id')));
$total_cost = 0;
foreach ($rows as $r) {
    $total_cost += (float)($r['cost_price'] ?? 0) * (int)($r['quantity'] ?? 1);
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
        </div>

        <form method="get" class="filter-row" style="margin-bottom: 16px;">
            <input type="text" name="search" class="search-input" value="<?php echo htmlspecialchars($search); ?>" placeholder="Müşteri veya kalem adı ara...">
            <button type="submit" class="btn btn-primary"><?php echo icon('search'); ?> Ara</button>
            <?php if ($search !== ''): ?>
                <a href="camera-sales-list.php" class="btn btn-secondary"><?php echo icon('x'); ?> Temizle</a>
            <?php endif; ?>
        </form>

        <?php if (empty($rows)): ?>
            <div class="no-data">Henüz kamera satışı kaydedilmedi.</div>
        <?php else: ?>
            <div class="table-wrapper">
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Müşteri</th>
                                <th>Kalem</th>
                                <th>Kategori</th>
                                <th style="text-align:right;">Maliyet</th>
                                <th style="text-align:right;">Adet</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r['sale_date']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($r['customer_name']); ?></strong><br>
                                        <small style="color: var(--text-muted);"><?php echo htmlspecialchars($r['tax_number']); ?></small>
                                    </td>
                                    <td><?php echo $r['item_name'] !== null ? htmlspecialchars($r['item_name']) : '<span style="color:var(--text-muted);">—</span>'; ?></td>
                                    <td><?php echo htmlspecialchars($r['category'] ?? ''); ?></td>
                                    <td style="text-align:right;"><?php echo $r['cost_price'] !== null ? number_format((float)$r['cost_price'], 2, ',', '.') . ' ₺' : '—'; ?></td>
                                    <td style="text-align:right;"><?php echo $r['quantity'] !== null ? (int)$r['quantity'] : '—'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
