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
$filter_company = $_GET['company'] ?? '';
$filter_operator = $_GET['operator'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

// KPI'lar
$kpi_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_simcards,
        SUM(CASE WHEN status = 'Stokta' THEN 1 ELSE 0 END) as active_simcards,
        SUM(CASE WHEN status = 'Pasif' THEN 1 ELSE 0 END) as passive_simcards,
        SUM(CASE WHEN status = 'Stokta' THEN total_cost ELSE 0 END) as stock_value
    FROM simcards
");
$kpi_stmt->execute();
$kpi_result = $kpi_stmt->get_result()->fetch_assoc();
$kpi_stmt->close();

// Dinamik SQL oluşturma
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(phone_number LIKE ? OR company LIKE ? OR operator LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($filter_company)) {
    $where_conditions[] = "company = ?";
    $params[] = $filter_company;
    $types .= 's';
}

if (!empty($filter_operator)) {
    $where_conditions[] = "operator = ?";
    $params[] = $filter_operator;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Toplam sayı
$count_sql = "SELECT COUNT(*) as total FROM simcards $where_clause";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_simcards = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_simcards / $per_page);
$stmt->close();

// Sim kartları çek
$sql = "SELECT * FROM simcards $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$simcards = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Sim Kartlar - CRM</title>
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
        }
        .logo-sidebar {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        .logo-sidebar span { color: #667eea; }
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
            padding: 30px;
        }
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .top-bar h1 { font-size: 28px; color: #333; }
        .logout-btn {
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
        }
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .stat-box h3 {
            font-size: 32px;
            color: #667eea;
            margin-bottom: 5px;
        }
        .stat-box p { color: #666; font-size: 14px; }
        .action-bar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .search-filter-row {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .search-box {
            display: flex;
            gap: 10px;
            flex: 1;
            min-width: 300px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 180px;
        }
        .filter-group label {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .search-input, .filter-select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .search-input {
            flex: 1;
        }
        .filter-select {
            width: 100%;
            background: white;
            cursor: pointer;
        }
        .search-input:focus, .filter-select:focus { 
            outline: none; 
            border-color: #667eea; 
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            white-space: nowrap;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-warning:hover { background: #e0a800; }
        .btn-danger { background: #dc3545; color: white; font-size: 12px; padding: 6px 12px; }
        .btn-danger:hover { background: #c82333; }
        .btn-edit { background: #17a2b8; color: white; font-size: 12px; padding: 6px 12px; }
        .btn-edit:hover { background: #138496; }
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .active-filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .filter-tag {
            background: #667eea;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .filter-tag .remove {
            cursor: pointer;
            font-weight: bold;
            opacity: 0.8;
        }
        .filter-tag .remove:hover {
            opacity: 1;
        }
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
        }
        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            color: #555;
        }
        tr:hover { background: #f8f9fa; }
        .no-data { text-align: center; padding: 40px; color: #999; }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .page-btn {
            padding: 8px 12px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            font-size: 14px;
            transition: all 0.3s;
        }
        .page-btn:hover {
            border-color: #667eea;
            color: #667eea;
            background: #f8f9fa;
        }
        .page-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
            font-weight: bold;
        }
        .page-dots {
            padding: 8px 4px;
            color: #999;
            font-weight: bold;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-header h2 { font-size: 24px; color: #333; }
        .close-btn { font-size: 28px; cursor: pointer; color: #999; }
        .close-btn:hover { color: #333; }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group input:read-only {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        .required { color: red; }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
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
            <a href="simcards.php" class="nav-item active">
                <span class="nav-icon">📱</span>
                <span>Sim Kartlar</span>
            </a>
            <a href="create-sale.php" class="nav-item">
                <span class="nav-icon">💰</span>
                <span>Satış</span>
            </a>
            <a href="sales-list.php" class="nav-item">
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
            <h1>Sim Kart Yönetimi</h1>
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
                <h3><?php echo $kpi_result['total_simcards']; ?></h3>
                <p>Toplam Sim Kart</p>
            </div>
            <div class="stat-box">
                <h3><?php echo $kpi_result['active_simcards']; ?></h3>
                <p>Aktif (Stokta)</p>
            </div>
            <div class="stat-box">
                <h3><?php echo $kpi_result['passive_simcards']; ?></h3>
                <p>Pasif</p>
            </div>
            <div class="stat-box">
                <h3>₺<?php echo number_format($kpi_result['stock_value'], 2); ?></h3>
                <p>Stok Değeri</p>
            </div>
        </div>

        <!-- Aksiyon Çubuğu -->
        <div class="action-bar">
            <form method="GET" style="width: 100%;">
                <!-- Arama ve Filtreler -->
                <div class="search-filter-row">
                    <div class="search-box">
                        <input type="text" name="search" class="search-input" 
                               placeholder="Telefon numarası ara..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>🏢 Şirket</label>
                        <select name="company" class="filter-select">
                            <option value="">Tüm Şirketler</option>
                            <option value="Waystech Bilişim" <?php echo $filter_company == 'Waystech Bilişim' ? 'selected' : ''; ?>>Waystech Bilişim</option>
                            <option value="Mycb Teknoloji" <?php echo $filter_company == 'Mycb Teknoloji' ? 'selected' : ''; ?>>Mycb Teknoloji</option>
                            <option value="Trio Mobil" <?php echo $filter_company == 'Trio Mobil' ? 'selected' : ''; ?>>Trio Mobil</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>📡 Operatör</label>
                        <select name="operator" class="filter-select">
                            <option value="">Tüm Operatörler</option>
                            <option value="Vodafone" <?php echo $filter_operator == 'Vodafone' ? 'selected' : ''; ?>>Vodafone</option>
                            <option value="Turkcell" <?php echo $filter_operator == 'Turkcell' ? 'selected' : ''; ?>>Turkcell</option>
                            <option value="Türk Telekom" <?php echo $filter_operator == 'Türk Telekom' ? 'selected' : ''; ?>>Türk Telekom</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">🔍 Filtrele</button>
                    
                    <?php if($search || $filter_company || $filter_operator): ?>
                        <a href="simcards.php" class="btn btn-warning">✖ Temizle</a>
                    <?php endif; ?>
                </div>

                <!-- Aktif Filtreler -->
                <?php if($search || $filter_company || $filter_operator): ?>
                    <div class="active-filters">
                        <strong style="color: #666; font-size: 13px;">Aktif Filtreler:</strong>
                        <?php if($search): ?>
                            <div class="filter-tag">
                                🔍 Arama: <?php echo htmlspecialchars($search); ?>
                                <span class="remove" onclick="removeFilter('search')">×</span>
                            </div>
                        <?php endif; ?>
                        <?php if($filter_company): ?>
                            <div class="filter-tag">
                                🏢 <?php echo htmlspecialchars($filter_company); ?>
                                <span class="remove" onclick="removeFilter('company')">×</span>
                            </div>
                        <?php endif; ?>
                        <?php if($filter_operator): ?>
                            <div class="filter-tag">
                                📡 <?php echo htmlspecialchars($filter_operator); ?>
                                <span class="remove" onclick="removeFilter('operator')">×</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>

            <!-- Butonlar -->
            <div class="action-buttons" style="margin-top: 15px;">
                <button onclick="openModal()" class="btn btn-success">➕ Sim Kart Ekle</button>
                <a href="export-simcards.php?<?php echo http_build_query(['search' => $search, 'company' => $filter_company, 'operator' => $filter_operator]); ?>" class="btn btn-primary">📥 Excel İndir</a>
                <a href="sample-simcards-template.php" class="btn btn-warning">📋 Şablon İndir</a>
                <button onclick="document.getElementById('importFile').click()" class="btn btn-primary">📤 Excel Yükle</button>
                <form id="importForm" method="POST" action="import-simcards.php" enctype="multipart/form-data" style="display: none;">
                    <input type="file" id="importFile" name="excel_file" accept=".xlsx,.xls" onchange="this.form.submit()">
                </form>
            </div>
        </div>

        <!-- Sim Kart Tablosu -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Telefon Numarası</th>
                        <th>Operatör</th>
                        <th>Şirket</th>
                        <th>Kategori</th>
                        <th>Fiyat</th>
                        <th>Durum</th>
                        <th style="text-align: center;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($simcards->num_rows > 0): ?>
                        <?php while($sim = $simcards->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($sim['phone_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($sim['operator']); ?></td>
                                <td><?php echo htmlspecialchars($sim['company']); ?></td>
                                <td><?php echo htmlspecialchars($sim['category']); ?></td>
                                <td><strong>₺<?php echo number_format($sim['total_cost'], 2); ?></strong></td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $sim['status'] == 'Stokta' ? 'success' : 
                                             ($sim['status'] == 'Pasif' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo $sim['status']; ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <button onclick="editSimcard(<?php echo $sim['id']; ?>)" class="btn btn-edit">✏️ Düzenle</button>
                                    <button onclick="deleteSimcard(<?php echo $sim['id']; ?>)" class="btn btn-danger">🗑️ Sil</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-data">
                                <?php 
                                if($search || $filter_company || $filter_operator) {
                                    echo "Filtrelere uygun sonuç bulunamadı.";
                                } else {
                                    echo "Henüz sim kart eklenmemiş.";
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Sayfalama -->
        <?php
        echo renderPagination($page, $total_pages, 'simcards.php', [
            'search' => $search,
            'company' => $filter_company,
            'operator' => $filter_operator
        ]);
        ?>
    </div>

    <!-- Sim Kart Ekle/Düzenle Modal -->
    <div id="simcardModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Yeni Sim Kart Ekle</h2>
                <span class="close-btn" onclick="closeModal()">&times;</span>
            </div>
            <form id="simcardForm" method="POST" action="save-simcard.php">
                <input type="hidden" id="simcard_id" name="simcard_id" value="">
                
                <div class="form-group">
                    <label>Telefon Numarası <span class="required">*</span></label>
                    <input type="text" id="phone_number" name="phone_number" placeholder="05XX XXX XX XX" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Operatör <span class="required">*</span></label>
                        <select id="operator" name="operator" required>
                            <option value="">Seçiniz</option>
                            <option value="Vodafone">Vodafone</option>
                            <option value="Turkcell">Turkcell</option>
                            <option value="Türk Telekom">Türk Telekom</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Şirket <span class="required">*</span></label>
                        <select id="company" name="company" required>
                            <option value="">Seçiniz</option>
                            <option value="Waystech Bilişim">Waystech Bilişim</option>
                            <option value="Mycb Teknoloji">Mycb Teknoloji</option>
                            <option value="Trio Mobil">Trio Mobil</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Kategori <span class="required">*</span></label>
                        <select id="category" name="category" required>
                            <option value="">Seçiniz</option>
                            <option value="Sim Kart">Sim Kart</option>
                            <option value="Yenileme">Yenileme</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Durum <span class="required">*</span></label>
                        <select id="status" name="status" required>
                            <option value="">Seçiniz</option>
                            <option value="Stokta">Stokta</option>
                            <option value="Pasif">Pasif</option>
                            <option value="Satıldı">Satıldı</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Maliyet Fiyatı (₺) <span class="required">*</span></label>
                        <input type="number" step="0.01" id="cost_price" name="cost_price" required onkeyup="calculateTotal()">
                    </div>
                    
                    <div class="form-group">
                        <label>KDV (%20)</label>
                        <input type="number" step="0.01" id="vat" name="vat" readonly>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Toplam Maliyet (₺)</label>
                    <input type="number" step="0.01" id="total_cost" name="total_cost" readonly>
                </div>
                
                <div class="form-group">
                    <label>Açıklama</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn btn-success" style="width: 100%;">💾 Kaydet</button>
            </form>
        </div>
    </div>

    <script>
        function calculateTotal() {
            const costPrice = parseFloat(document.getElementById('cost_price').value) || 0;
            const vat = costPrice * 0.20;
            const total = costPrice + vat;
            
            document.getElementById('vat').value = vat.toFixed(2);
            document.getElementById('total_cost').value = total.toFixed(2);
        }

        function openModal() {
            document.getElementById('simcardModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('simcardModal').classList.remove('show');
            document.getElementById('simcardForm').reset();
            document.getElementById('simcard_id').value = '';
            document.getElementById('modalTitle').textContent = 'Yeni Sim Kart Ekle';
        }

        function removeFilter(filterType) {
            const url = new URL(window.location.href);
            url.searchParams.delete(filterType);
            url.searchParams.delete('page'); // Sayfa numarasını da sıfırla
            window.location.href = url.toString();
        }

        function editSimcard(id) {
            fetch('/crm/get-simcard.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Hata: ' + data.error);
                        return;
                    }
                    
                    document.getElementById('modalTitle').textContent = 'Sim Kart Düzenle';
                    document.getElementById('simcard_id').value = data.id;
                    document.getElementById('phone_number').value = data.phone_number || '';
                    document.getElementById('operator').value = data.operator || '';
                    document.getElementById('company').value = data.company || '';
                    document.getElementById('category').value = data.category || '';
                    document.getElementById('status').value = data.status || '';
                    document.getElementById('cost_price').value = data.cost_price || '';
                    document.getElementById('vat').value = data.vat || '';
                    document.getElementById('total_cost').value = data.total_cost || '';
                    document.getElementById('description').value = data.description || '';
                    
                    openModal();
                })
                .catch(error => {
                    console.error('Hata:', error);
                    alert('Bağlantı hatası: ' + error.message);
                });
        }

        function deleteSimcard(id) {
            if(confirm('Bu sim kartı silmek istediğinizden emin misiniz?')) {
                window.location.href = '/crm/delete-simcard.php?id=' + id;
            }
        }

        window.onclick = function(event) {
            const modal = document.getElementById('simcardModal');
            if (event.target == modal) {
                closeModal();
            }
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