<?php
require_once 'config.php';
require_once 'pagination.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Filtreleme değişkenleri
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$year_filter = $_GET['year'] ?? '';
$quarter_filter = $_GET['quarter'] ?? '';

// Sayfalama
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

// KPI'lar - Filtrelere göre dinamik
$today = date('Y-m-d');
$upcoming_date = date('Y-m-d', strtotime('+30 days'));

// KPI için SQL oluştur
$kpi_conditions = "WHERE 1=1";
$kpi_params = array();
$kpi_types = '';

// Yıl filtresi
if (!empty($year_filter)) {
    $kpi_conditions .= " AND YEAR(renewal_date) = ?";
    $kpi_params[] = intval($year_filter);
    $kpi_types .= 'i';
}

// Quarter filtresi
if (!empty($quarter_filter)) {
    $kpi_conditions .= " AND QUARTER(renewal_date) = ?";
    $kpi_params[] = intval($quarter_filter);
    $kpi_types .= 'i';
}

// Aktif aboneler
$stmt_active = $conn->prepare("SELECT COUNT(*) as total FROM subscriptions $kpi_conditions AND status = 'Aktif'");
if (!empty($kpi_params)) {
    $stmt_active->bind_param($kpi_types, ...$kpi_params);
}
$stmt_active->execute();
$active_count = $stmt_active->get_result()->fetch_assoc()['total'];
$stmt_active->close();

// Yaklaşan yenilemeler (30 gün içinde)
$upcoming_conditions = $kpi_conditions . " AND status = 'Aktif' AND renewal_date BETWEEN '$today' AND '$upcoming_date'";
$stmt_upcoming = $conn->prepare("SELECT COUNT(*) as total FROM subscriptions $upcoming_conditions");
if (!empty($kpi_params)) {
    $stmt_upcoming->bind_param($kpi_types, ...$kpi_params);
}
$stmt_upcoming->execute();
$upcoming_count = $stmt_upcoming->get_result()->fetch_assoc()['total'];
$stmt_upcoming->close();

// Gecikmiş abonelikler
$overdue_conditions = $kpi_conditions . " AND status = 'Aktif' AND renewal_date < '$today'";
$stmt_overdue = $conn->prepare("SELECT COUNT(*) as total FROM subscriptions $overdue_conditions");
if (!empty($kpi_params)) {
    $stmt_overdue->bind_param($kpi_types, ...$kpi_params);
}
$stmt_overdue->execute();
$overdue_count = $stmt_overdue->get_result()->fetch_assoc()['total'];
$stmt_overdue->close();

// Ürün yenileme geliri (sadece item_type = 'product')
$product_revenue_conditions = $kpi_conditions . " AND subscription_revenue > 0 AND item_type = 'product'";
$stmt_product_revenue = $conn->prepare("SELECT SUM(subscription_revenue) as total FROM subscriptions $product_revenue_conditions");
if (!empty($kpi_params)) {
    $stmt_product_revenue->bind_param($kpi_types, ...$kpi_params);
}
$stmt_product_revenue->execute();
$total_product_revenue = $stmt_product_revenue->get_result()->fetch_assoc()['total'] ?? 0;
$stmt_product_revenue->close();

// SIM kart yenileme geliri (sadece item_type = 'simcard')
$simcard_revenue_conditions = $kpi_conditions . " AND subscription_revenue > 0 AND item_type = 'simcard'";
$stmt_simcard_revenue = $conn->prepare("SELECT SUM(subscription_revenue) as total FROM subscriptions $simcard_revenue_conditions");
if (!empty($kpi_params)) {
    $stmt_simcard_revenue->bind_param($kpi_types, ...$kpi_params);
}
$stmt_simcard_revenue->execute();
$total_simcard_revenue = $stmt_simcard_revenue->get_result()->fetch_assoc()['total'] ?? 0;
$stmt_simcard_revenue->close();

// Toplam yenileme geliri (ürün + sim)
$total_renewal_revenue = $total_product_revenue + $total_simcard_revenue;

// Yılları çek (dinamik)
$years_result = $conn->query("SELECT DISTINCT YEAR(renewal_date) as year FROM subscriptions ORDER BY year DESC");
$available_years = array();
while ($year_row = $years_result->fetch_assoc()) {
    $available_years[] = $year_row['year'];
}

// Ana Sorgu
$sql = "SELECT s.*, c.name as customer_name
        FROM subscriptions s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        WHERE 1=1";

$params = [];
$types = '';

if ($filter === 'active') {
    $sql .= " AND s.status = 'Aktif'";
} elseif ($filter === 'upcoming') {
    $sql .= " AND s.status = 'Aktif' AND s.renewal_date BETWEEN ? AND ?";
    $params[] = $today;
    $params[] = $upcoming_date;
    $types .= 'ss';
} elseif ($filter === 'overdue') {
    $sql .= " AND s.status = 'Aktif' AND s.renewal_date < ?";
    $params[] = $today;
    $types .= 's';
} elseif ($filter === 'renewed') {
    $sql .= " AND s.status = 'Yenilendi'";
} elseif ($filter === 'cancelled') {
    $sql .= " AND s.status = 'İptal'";
}

if (!empty($search)) {
    $sql .= " AND (c.name LIKE ? OR s.item_name LIKE ? OR s.item_detail LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

// Yıl filtresi
if (!empty($year_filter)) {
    $sql .= " AND YEAR(s.renewal_date) = ?";
    $params[] = intval($year_filter);
    $types .= 'i';
}

// Quarter filtresi
if (!empty($quarter_filter)) {
    $sql .= " AND QUARTER(s.renewal_date) = ?";
    $params[] = intval($quarter_filter);
    $types .= 'i';
}

// Toplam sayı
$count_sql = str_replace("SELECT s.*, c.name as customer_name", "SELECT COUNT(*) as total", $sql);
$stmt_count = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);
$stmt_count->close();

// Veri çek
$sql .= " ORDER BY s.renewal_date ASC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$subscriptions = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Abonelikler - CRM</title>
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
            grid-template-columns: repeat(5, 1fr);
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
            font-size: 22px;
            color: #667eea;
            margin-bottom: 4px;
        }
        .stat-box p { 
            color: #666; 
            font-size: 11px;
            line-height: 1.3;
        }
        .filter-bar {
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
            flex-wrap: wrap;
            margin-bottom: 8px;
        }
        .filter-row:last-child {
            margin-bottom: 0;
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
        .btn-light {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #dee2e6;
        }
        .btn-light:hover, .btn-light.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        .btn-warning:hover { background: #e0a800; }
        .search-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
        }
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            overflow: hidden;
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
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
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
    /* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 5px;
    margin-top: 20px;
    padding: 15px;
    flex-wrap: wrap;
}
.page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 8px 12px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    color: #333;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s;
}
.page-btn:hover {
    background: #667eea;
    color: white;
    border-color: #667eea;
}
.page-btn.active {
    background: #667eea;
    color: white;
    border-color: #667eea;
}
.page-dots {
    padding: 8px 4px;
    color: #666;
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
                <span>👥</span> Müşteriler
            </a>
            <a href="products.php" class="nav-item">
                <span>📦</span> Ürünler
            </a>
            <a href="simcards.php" class="nav-item">
                <span>📱</span> Sim Kartlar
            </a>
            <a href="create-sale.php" class="nav-item">
                <span>💰</span> Satış
            </a>
            <a href="sales-list.php" class="nav-item">
                <span>📋</span> Satış Listesi
            </a>
            <a href="subscriptions.php" class="nav-item active">
                <span>🔄</span> Abonelikler
            </a>
            <a href="logout.php" class="nav-item" style="margin-top: auto;">
                <span>🚪</span> Çıkış Yap
            </a>
        </nav>
    </div>

    <!-- Ana İçerik -->
    <div class="main-content">
        <div class="top-bar">
            <h1>🔄 Abonelikler</h1>
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
                <h3><?php echo $active_count; ?></h3>
                <p>Aktif Aboneler</p>
                <?php if($year_filter || $quarter_filter): ?>
                    <p style="font-size: 10px; color: #28a745; margin-top: 3px; font-weight: 600;">
                        <?php 
                        if($year_filter && $quarter_filter) {
                            echo "✓ Q$quarter_filter $year_filter";
                        } elseif($year_filter) {
                            echo "✓ $year_filter";
                        } elseif($quarter_filter) {
                            echo "✓ Q$quarter_filter";
                        }
                        ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="stat-box">
                <h3><?php echo $upcoming_count; ?></h3>
                <p>Yaklaşan Yenilemeler<br>(30 gün)</p>
                <?php if($year_filter || $quarter_filter): ?>
                    <p style="font-size: 10px; color: #28a745; margin-top: 3px; font-weight: 600;">
                        <?php 
                        if($year_filter && $quarter_filter) {
                            echo "✓ Q$quarter_filter $year_filter";
                        } elseif($year_filter) {
                            echo "✓ $year_filter";
                        } elseif($quarter_filter) {
                            echo "✓ Q$quarter_filter";
                        }
                        ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="stat-box">
                <h3><?php echo $overdue_count; ?></h3>
                <p>Gecikmiş</p>
                <?php if($year_filter || $quarter_filter): ?>
                    <p style="font-size: 10px; color: #28a745; margin-top: 3px; font-weight: 600;">
                        <?php 
                        if($year_filter && $quarter_filter) {
                            echo "✓ Q$quarter_filter $year_filter";
                        } elseif($year_filter) {
                            echo "✓ $year_filter";
                        } elseif($quarter_filter) {
                            echo "✓ Q$quarter_filter";
                        }
                        ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="stat-box" style="border-left: 4px solid #667eea;">
                <h3 style="color: #667eea;">₺<?php echo number_format($total_product_revenue, 2); ?></h3>
                <p>Ürün Yenileme Geliri</p>
                <?php if($year_filter || $quarter_filter): ?>
                    <p style="font-size: 10px; color: #28a745; margin-top: 3px; font-weight: 600;">
                        <?php 
                        if($year_filter && $quarter_filter) {
                            echo "✓ Q$quarter_filter $year_filter";
                        } elseif($year_filter) {
                            echo "✓ $year_filter";
                        } elseif($quarter_filter) {
                            echo "✓ Q$quarter_filter";
                        }
                        ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="stat-box" style="border-left: 4px solid #28a745;">
                <h3 style="color: #28a745;">₺<?php echo number_format($total_simcard_revenue, 2); ?></h3>
                <p>SIM Kart Yenileme<br>Geliri</p>
                <?php if($year_filter || $quarter_filter): ?>
                    <p style="font-size: 10px; color: #28a745; margin-top: 3px; font-weight: 600;">
                        <?php 
                        if($year_filter && $quarter_filter) {
                            echo "✓ Q$quarter_filter $year_filter";
                        } elseif($year_filter) {
                            echo "✓ $year_filter";
                        } elseif($quarter_filter) {
                            echo "✓ Q$quarter_filter";
                        }
                        ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filtreler -->
        <div class="filter-bar">
            <!-- İlk Satır: Durum Filtreleri -->
            <div class="filter-row">
                <a href="?filter=all<?php echo $year_filter ? '&year='.$year_filter : ''; ?><?php echo $quarter_filter ? '&quarter='.$quarter_filter : ''; ?>" 
                   class="btn btn-light <?php echo $filter === 'all' ? 'active' : ''; ?>">📊 Tümü</a>
                <a href="?filter=active<?php echo $year_filter ? '&year='.$year_filter : ''; ?><?php echo $quarter_filter ? '&quarter='.$quarter_filter : ''; ?>" 
                   class="btn btn-light <?php echo $filter === 'active' ? 'active' : ''; ?>">✅ Aktif</a>
                <a href="?filter=upcoming<?php echo $year_filter ? '&year='.$year_filter : ''; ?><?php echo $quarter_filter ? '&quarter='.$quarter_filter : ''; ?>" 
                   class="btn btn-light <?php echo $filter === 'upcoming' ? 'active' : ''; ?>">⏰ Yaklaşan</a>
                <a href="?filter=overdue<?php echo $year_filter ? '&year='.$year_filter : ''; ?><?php echo $quarter_filter ? '&quarter='.$quarter_filter : ''; ?>" 
                   class="btn btn-light <?php echo $filter === 'overdue' ? 'active' : ''; ?>">⚠️ Gecikmişler</a>
                <a href="?filter=renewed<?php echo $year_filter ? '&year='.$year_filter : ''; ?><?php echo $quarter_filter ? '&quarter='.$quarter_filter : ''; ?>" 
                   class="btn btn-light <?php echo $filter === 'renewed' ? 'active' : ''; ?>">🔄 Yenilendi</a>
                <a href="?filter=cancelled<?php echo $year_filter ? '&year='.$year_filter : ''; ?><?php echo $quarter_filter ? '&quarter='.$quarter_filter : ''; ?>" 
                   class="btn btn-light <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">❌ İptal</a>
            </div>
            
            <!-- İkinci Satır: Zaman ve Arama Filtreleri -->
            <form method="GET" class="filter-row">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                
                <!-- Yıl Filtresi -->
                <select name="year" class="search-input" style="width: 140px; flex: 0 0 auto;" onchange="this.form.submit()">
                    <option value="">📅 Tüm Yıllar</option>
                    <?php foreach ($available_years as $year): ?>
                        <option value="<?php echo $year; ?>" <?php echo $year_filter == $year ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <!-- Quarter Filtresi -->
                <select name="quarter" class="search-input" style="width: 150px; flex: 0 0 auto;" onchange="this.form.submit()">
                    <option value="">📊 Tüm Çeyrekler</option>
                    <option value="1" <?php echo $quarter_filter == '1' ? 'selected' : ''; ?>>Q1 (Oca-Mar)</option>
                    <option value="2" <?php echo $quarter_filter == '2' ? 'selected' : ''; ?>>Q2 (Nis-Haz)</option>
                    <option value="3" <?php echo $quarter_filter == '3' ? 'selected' : ''; ?>>Q3 (Tem-Eyl)</option>
                    <option value="4" <?php echo $quarter_filter == '4' ? 'selected' : ''; ?>>Q4 (Eki-Ara)</option>
                </select>
                
                <input type="text" name="search" class="search-input" style="flex: 1; min-width: 250px;" 
                       placeholder="🔍 Müşteri, ürün veya IMEI/Telefon ara..." value="<?php echo htmlspecialchars($search); ?>">
                
                <button type="submit" class="btn btn-primary" style="flex: 0 0 auto;">Ara</button>
                
                <?php if($search || $year_filter || $quarter_filter): ?>
                    <a href="?filter=<?php echo $filter; ?>" class="btn btn-warning" style="flex: 0 0 auto;">✖ Temizle</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Tablo -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Müşteri</th>
                        <th>Ürün/Hizmet</th>
                        <th>Döngü</th>
                        <th>İlk Satış</th>
                        <th>Yenileme Tarihi</th>
                        <th>Kalan Süre</th>
                        <th>Durum</th>
                        <th>Yenileme Geliri</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($subscriptions->num_rows > 0): ?>
                        <?php while($sub = $subscriptions->fetch_assoc()): 
                            $days_left = (strtotime($sub['renewal_date']) - strtotime($today)) / (60 * 60 * 24);
                            $days_left = round($days_left);
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($sub['customer_name']); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($sub['item_name']); ?></strong><br>
                                    <small><?php echo $sub['item_type'] === 'product' ? '📱 IMEI: ' : '📞 Tel: '; ?><?php echo htmlspecialchars($sub['item_detail']); ?></small>
                                </td>
                                <td><span class="badge badge-info"><?php echo $sub['cycle']; ?>. Döngü</span></td>
                                <td><?php echo date('d.m.Y', strtotime($sub['initial_sale_date'])); ?></td>
                                <td><?php echo date('d.m.Y', strtotime($sub['renewal_date'])); ?></td>
                                <td>
                                    <?php 
                                    if ($sub['status'] == 'Yenilendi') {
                                        echo '<span class="badge badge-success">✓ Yenilendi</span>';
                                    } elseif ($sub['status'] == 'İptal') {
                                        echo '<span class="badge badge-danger">✗ İptal Edildi</span>';
                                    } else {
                                        if($days_left > 0) {
                                            echo '<span style="color: #28a745; font-weight: 600;">' . $days_left . ' gün</span>';
                                        } elseif($days_left == 0) {
                                            echo '<span style="color: #ffc107; font-weight: 600;">Bugün</span>';
                                        } else {
                                            echo '<span style="color: #dc3545; font-weight: 600;">' . abs($days_left) . ' gün gecikmiş</span>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if($sub['status'] === 'Aktif'): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php elseif($sub['status'] === 'Yenilendi'): ?>
                                        <span class="badge badge-info">Yenilendi</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">İptal</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: <?php echo $sub['item_type'] === 'product' ? '#667eea' : '#28a745'; ?>;">
                                        <?php 
                                        if (isset($sub['subscription_revenue']) && $sub['subscription_revenue'] > 0) {
                                            echo '₺' . number_format($sub['subscription_revenue'], 2);
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </strong>
                                </td>
                                <td>
                                    <button onclick="openEditModal(<?php echo $sub['id']; ?>)" class="btn btn-primary" style="font-size: 12px; padding: 6px 12px;">✏️ Düzenle</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="no-data">
                                <?php echo ($search) ? "Arama sonucu bulunamadı." : "Henüz abonelik kaydı yok."; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Sayfalama -->
        <?php
        echo renderPagination($page, $total_pages, 'subscriptions.php', [
            'filter' => $filter,
            'search' => $search,
            'year' => $year_filter,
            'quarter' => $quarter_filter
        ]);
        ?>
    </div>

    <script>
        function openEditModal(subscriptionId) {
            window.location.href = 'edit-subscription.php?id=' + subscriptionId;
        }
    </script>
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