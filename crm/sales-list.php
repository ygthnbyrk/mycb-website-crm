<?php
require_once 'config.php';
require_once 'pagination.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Arama ve Filtreleme
$search = $_GET['search'] ?? '';
$year = $_GET['year'] ?? '';
$month = $_GET['month'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

// KPI'lar - DİNAMİK (Yıl ve Ay Filtresine Göre)
$kpi_sql = "
    SELECT 
        COUNT(*) as total_sales,
        SUM(total) as total_revenue,
        (SELECT COUNT(*) FROM sale_products sp 
         INNER JOIN sales s ON sp.sale_id = s.id 
         WHERE 1=1";

// KPI için yıl filtresi
if (!empty($year)) {
    $kpi_sql .= " AND YEAR(s.sale_date) = " . intval($year);
}

// KPI için ay filtresi
if (!empty($month)) {
    $kpi_sql .= " AND MONTH(s.sale_date) = " . intval($month);
}

$kpi_sql .= ") as total_products_sold,
        (SELECT COUNT(*) FROM sale_simcards ss 
         INNER JOIN sales s ON ss.sale_id = s.id 
         WHERE 1=1";

// KPI için yıl filtresi (simcards)
if (!empty($year)) {
    $kpi_sql .= " AND YEAR(s.sale_date) = " . intval($year);
}

// KPI için ay filtresi (simcards)
if (!empty($month)) {
    $kpi_sql .= " AND MONTH(s.sale_date) = " . intval($month);
}

$kpi_sql .= ") as total_simcards_sold
    FROM sales
    WHERE 1=1";

// Ana sorgu için yıl filtresi
if (!empty($year)) {
    $kpi_sql .= " AND YEAR(sale_date) = " . intval($year);
}

// Ana sorgu için ay filtresi
if (!empty($month)) {
    $kpi_sql .= " AND MONTH(sale_date) = " . intval($month);
}

$kpi_result = $conn->query($kpi_sql)->fetch_assoc();

// Yıllar listesi
$years_sql = "SELECT DISTINCT YEAR(sale_date) as year FROM sales ORDER BY year DESC";
$years_result = $conn->query($years_sql);
$years = [];
while ($row = $years_result->fetch_assoc()) {
    $years[] = $row['year'];
}

// Aylar listesi
$months = [
    1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
    5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
    9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
];

// Arama ve filtreleme ile toplam sayı
$count_sql = "SELECT COUNT(*) as total FROM sales s 
              LEFT JOIN customers c ON s.customer_id = c.id 
              WHERE 1=1";

$params = [];
$types = '';

if (!empty($search)) {
    $count_sql .= " AND (c.name LIKE ? OR 
                        s.id IN (SELECT sale_id FROM sale_products WHERE imei_number LIKE ? OR plate LIKE ?))";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($year)) {
    $count_sql .= " AND YEAR(s.sale_date) = ?";
    $params[] = $year;
    $types .= 'i';
}

if (!empty($month)) {
    $count_sql .= " AND MONTH(s.sale_date) = ?";
    $params[] = $month;
    $types .= 'i';
}

$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_sales = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_sales / $per_page);
$stmt->close();

// Satışları çek
$sql = "SELECT s.*, c.name as customer_name, c.tax_number 
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        WHERE 1=1";

$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (c.name LIKE ? OR 
                   s.id IN (SELECT sale_id FROM sale_products WHERE imei_number LIKE ? OR plate LIKE ?))";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($year)) {
    $sql .= " AND YEAR(s.sale_date) = ?";
    $params[] = $year;
    $types .= 'i';
}

if (!empty($month)) {
    $sql .= " AND MONTH(s.sale_date) = ?";
    $params[] = $month;
    $types .= 'i';
}

$sql .= " ORDER BY s.sale_date DESC, s.id DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$sales = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Satış Listesi - CRM</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f5f7fa;
        }
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            padding: 20px;
            overflow-y: auto;
            z-index: 100;
        }
        .nav-menu { display: flex; flex-direction: column; gap: 10px; }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 8px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s;
        }
        .nav-item:hover { background: #f0f0f0; }
        .nav-item.active { background: #667eea; color: white; }
        .nav-icon { font-size: 20px; }
        .main-content {
            margin-left: 250px;
            padding: 15px;
        }
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .top-bar h1 { 
            font-size: 24px; 
            color: #333;
            text-align: left;
        }
        .logout-btn {
            padding: 8px 16px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
        }
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 15px;
        }
        .stat-box {
            background: white;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }
        .stat-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .stat-box h3 {
            font-size: 24px;
            color: #667eea;
            margin-bottom: 4px;
        }
        .stat-box p { 
            color: #666; 
            font-size: 12px; 
        }
        .filter-info {
            font-size: 10px;
            color: #28a745;
            margin-top: 2px;
            font-weight: 600;
        }
        .action-bar {
            background: white;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            margin-bottom: 15px;
        }
        .filter-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .search-input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
        }
        select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
            background: white;
            cursor: pointer;
        }
        .btn {
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover { background: #5568d3; }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover { background: #218838; }
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        .btn-warning:hover { background: #e0a800; }
        .table-wrapper {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        table-scroll {
    overflow-x: auto;
    max-height: calc(100vh - 320px);  /* 380px'den 320px'e düşürdük */
}
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        thead {
            position: sticky;
            top: 0;
            background: #667eea;
            z-index: 10;
        }
        th {
            padding: 10px 8px;
            color: white;
            font-weight: 600;
            text-align: left;
            white-space: nowrap;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #f0f0f0;
        }
        tbody tr:hover { background: #f8f9fa; }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .item-list { display: flex; flex-direction: column; gap: 6px; }
        .item-row {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .item-badge {
            background: #667eea;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 600;
            display: inline-block;
            width: fit-content;
        }
        .sim-badge {
            background: #28a745;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 600;
            display: inline-block;
            width: fit-content;
        }
        .action-btns {
            display: flex;
            gap: 4px;
            justify-content: center;
        }
        .icon-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            padding: 4px 8px;
            border-radius: 4px;
            transition: all 0.3s;
        }
        .icon-btn:hover { background: #f0f0f0; transform: scale(1.1); }
        .btn-edit:hover { background: #e3f2fd; }
        .btn-delete:hover { background: #ffebee; }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .logo-text {
            font-size: 20px;
            font-weight: 700;
            color: #333;
        }
        
        .pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-top: 20px;
    flex-wrap: wrap;
}
.pagination a,
.pagination span {
    padding: 8px 14px;
    border: 1px solid #ddd;
    border-radius: 6px;
    text-decoration: none;
    color: #333;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s;
    background: white;
    min-width: 40px;
    text-align: center;
}
.pagination a:hover {
    background: #667eea;
    color: white;
    border-color: #667eea;
}
.pagination span.current {
    background: #667eea;
    color: white;
    border-color: #667eea;
    font-weight: 600;
}
.pagination span.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f5f5f5;
}
.pagination .dots {
    border: none;
    background: none;
    padding: 8px 4px;
}
    </style>
</head>
<body>
   <!-- Hamburger Menu -->
<button class="mobile-menu-btn" onclick="toggleMenu()">☰</button>
     <!-- Sidebar -->
    <div class="sidebar">
        <img src="assets/images/logo-light.png" alt="Logo" style="max-width: 200px; height: auto;">
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item">
                <span class="nav-icon">🏠</span>
                <span>Ana Sayfa</span>
            </a>
            <a href="customers.php" class="nav-item">
                <span class="nav-icon">👥</span>
                <span>Müşteriler</span>
            </a>
            <a href="products.php" class="nav-item">
                <span class="nav-icon">📦</span>
                <span>Ürünler</span>
            </a>
            <a href="simcards.php" class="nav-item">
                <span class="nav-icon">📱</span>
                <span>Sim Kartlar</span>
            </a>
            <a href="create-sale.php" class="nav-item">
                <span class="nav-icon">💰</span>
                <span>Satış</span>
            </a>
            <a href="sales-list.php" class="nav-item active">
                <span class="nav-icon">📋</span>
                <span>Satış Listesi</span>
            </a>
            <a href="subscriptions.php" class="nav-item">
                <span class="nav-icon">🔄</span>
                <span>Abonelikler</span>
            </a>
            <a href="logout.php" class="nav-item" style="margin-top: auto;">
                <span class="nav-icon">🚪</span>
                <span>Çıkış Yap</span>
            </a>
        </nav>
    </div>

    <!-- Ana İçerik -->
    <div class="main-content">
        <div class="top-bar">
            <h1>📋 Satış Listesi</h1>
            <a href="logout.php" class="logout-btn">Çıkış Yap</a>
        </div>

        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- KPI -->
        <div class="stats-bar">
            <div class="stat-box">
                <h3><?php echo $kpi_result['total_sales']; ?></h3>
                <p>Toplam Satış</p>
                <?php if($year || $month): ?>
                    <p class="filter-info">
                        ✓ <?php 
                            if($year && $month) {
                                echo $months[$month] . ' ' . $year;
                            } elseif($year) {
                                echo $year . ' Yılı';
                            } elseif($month) {
                                echo $months[$month];
                            }
                        ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="stat-box">
                <h3>₺<?php echo number_format($kpi_result['total_revenue'], 2); ?></h3>
                <p>Toplam Ciro</p>
                <?php if($year || $month): ?>
                    <p class="filter-info">
                        ✓ <?php 
                            if($year && $month) {
                                echo $months[$month] . ' ' . $year;
                            } elseif($year) {
                                echo $year . ' Yılı';
                            } elseif($month) {
                                echo $months[$month];
                            }
                        ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="stat-box">
                <h3><?php echo $kpi_result['total_products_sold']; ?></h3>
                <p>Satılan Ürün</p>
                <?php if($year || $month): ?>
                    <p class="filter-info">
                        ✓ <?php 
                            if($year && $month) {
                                echo $months[$month] . ' ' . $year;
                            } elseif($year) {
                                echo $year . ' Yılı';
                            } elseif($month) {
                                echo $months[$month];
                            }
                        ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="stat-box">
                <h3><?php echo $kpi_result['total_simcards_sold']; ?></h3>
                <p>Satılan Sim Kart</p>
                <?php if($year || $month): ?>
                    <p class="filter-info">
                        ✓ <?php 
                            if($year && $month) {
                                echo $months[$month] . ' ' . $year;
                            } elseif($year) {
                                echo $year . ' Yılı';
                            } elseif($month) {
                                echo $months[$month];
                            }
                        ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filtreler -->
        <div class="action-bar">
            <form method="GET" class="filter-row" style="margin-bottom: 8px;">
                <input type="text" name="search" class="search-input" 
                       placeholder="🔍 Müşteri, IMEI veya plaka ara..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <select name="year" onchange="this.form.submit()">
                    <option value="">📅 Tüm Yıllar</option>
                    <?php foreach($years as $y): ?>
                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="month" onchange="this.form.submit()">
                    <option value="">📆 Tüm Aylar</option>
                    <?php foreach($months as $m_num => $m_name): ?>
                        <option value="<?php echo $m_num; ?>" <?php echo $month == $m_num ? 'selected' : ''; ?>><?php echo $m_name; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Ara</button>
                <?php if($search || $year || $month): ?>
                    <a href="sales-list.php" class="btn btn-warning">✖ Temizle</a>
                <?php endif; ?>
            </form>
            <div class="filter-row">
                <a href="create-sale.php" class="btn btn-success">➕ Yeni Satış</a>
                <a href="export-sales.php?<?php echo http_build_query(['search' => $search, 'year' => $year, 'month' => $month]); ?>" class="btn btn-primary">📥 Excel İndir</a>
            </div>
        </div>

        <!-- Tablo -->
        <div class="table-wrapper">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 80px;">Tarih</th>
                            <th style="width: 160px;">Müşteri</th>
                            <th style="width: 240px;">Ürün & Teknik Bilgi</th>
                            <th style="width: 160px;">Sim Kartlar</th>
                            <th style="width: 60px; text-align: center;">Adet</th>
                            <th style="width: 90px; text-align: right;">Tutar</th>
                            <th style="width: 70px; text-align: center;">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($sales->num_rows > 0): ?>
                            <?php while($sale = $sales->fetch_assoc()): 
                                // Ürünleri çek
                                $products_sql = "SELECT * FROM sale_products WHERE sale_id = ?";
                                $stmt_p = $conn->prepare($products_sql);
                                $stmt_p->bind_param("i", $sale['id']);
                                $stmt_p->execute();
                                $products = $stmt_p->get_result();
                                $stmt_p->close();
                                
                                // Sim kartları çek
                                $simcards_sql = "SELECT * FROM sale_simcards WHERE sale_id = ?";
                                $stmt_s = $conn->prepare($simcards_sql);
                                $stmt_s->bind_param("i", $sale['id']);
                                $stmt_s->execute();
                                $simcards = $stmt_s->get_result();
                                $stmt_s->close();
                                
                                $total_items = $products->num_rows + $simcards->num_rows;
                            ?>
                                <tr>
                                    <td style="white-space: nowrap;">
                                        <strong style="font-size: 12px;"><?php echo date('d.m.Y', strtotime($sale['sale_date'])); ?></strong>
                                    </td>
                                    <td>
                                        <strong style="font-size: 12px;"><?php echo htmlspecialchars($sale['customer_name']); ?></strong><br>
                                        <small style="color: #999; font-size: 10px;"><?php echo htmlspecialchars($sale['tax_number']); ?></small>
                                    </td>
                                    <td>
                                        <div class="item-list">
                                            <?php while($p = $products->fetch_assoc()): ?>
                                                <div class="item-row">
                                                    <span class="item-badge">CİHAZ</span>
                                                    <strong><?php echo htmlspecialchars($p['model']); ?></strong>
                                                    <small style="color: #666;">
                                                        IMEI: <?php echo htmlspecialchars($p['imei_number']); ?>
                                                        <?php if($p['plate']): ?>
                                                            • 🚗 <?php echo htmlspecialchars($p['plate']); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="item-list">
                                            <?php while($s = $simcards->fetch_assoc()): ?>
                                                <div class="item-row">
                                                    <span class="sim-badge">SIM</span>
                                                    <strong><?php echo htmlspecialchars($s['phone_number']); ?></strong>
                                                    <small style="color: #666;"><?php echo htmlspecialchars($s['operator']); ?></small>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <strong style="color: #667eea; font-size: 14px;"><?php echo $total_items; ?></strong>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="font-size: 10px; color: #999;">Ara: ₺<?php echo number_format($sale['subtotal'], 2); ?></div>
                                        <div style="font-size: 10px; color: #999;">KDV: ₺<?php echo number_format($sale['vat'], 2); ?></div>
                                        <strong style="color: #28a745; font-size: 13px;">₺<?php echo number_format($sale['total'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <button onclick="window.location.href='edit-sale.php?id=<?php echo $sale['id']; ?>'" 
                                                    class="icon-btn btn-edit" title="Düzenle">✏️</button>
                                            <button onclick="if(confirm('Bu satışı silmek istediğinizden emin misiniz?')) window.location.href='delete-sale.php?id=<?php echo $sale['id']; ?>'" 
                                                    class="icon-btn btn-delete" title="Sil">🗑️</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="no-data">
                                    <?php echo ($search || $year || $month) ? "Arama sonucu bulunamadı." : "Henüz satış kaydı yok."; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sayfalama -->
        <?php
        echo renderPagination($page, $total_pages, 'sales-list.php', [
            'search' => $search,
            'year' => $year,
            'month' => $month
        ]);
        ?>
    </div>
    <script>
function toggleMenu() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('active');
    }
}

document.addEventListener('click', function(event) {
    if (window.innerWidth <= 768) {
        const sidebar = document.querySelector('.sidebar');
        const menuBtn = document.querySelector('.mobile-menu-btn');
        
        if (sidebar && menuBtn) {
            if (!sidebar.contains(event.target) && !menuBtn.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        }
    }
});
</script>
</body>
</html>