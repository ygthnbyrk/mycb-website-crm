<?php
require_once 'config.php';
require_once 'pagination.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Arama ve Sayfalama
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

// KPI'lar
$kpi_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_products,
        SUM(CASE WHEN status = 'Stokta' THEN 1 ELSE 0 END) as stock_products,
        SUM(CASE WHEN status = 'Satıldı' THEN 1 ELSE 0 END) as sold_products,
        SUM(CASE WHEN status = 'Stokta' THEN total_cost ELSE 0 END) as stock_value
    FROM products
");
$kpi_stmt->execute();
$kpi_result = $kpi_stmt->get_result()->fetch_assoc();
$kpi_stmt->close();

// Arama ile toplam ürün sayısı
if (!empty($search)) {
    $count_sql = "SELECT COUNT(*) as total FROM products WHERE product_name LIKE ? OR imei_number LIKE ?";
    $stmt = $conn->prepare($count_sql);
    $search_param = "%$search%";
    $stmt->bind_param("ss", $search_param, $search_param);
} else {
    $count_sql = "SELECT COUNT(*) as total FROM products";
    $stmt = $conn->prepare($count_sql);
}
$stmt->execute();
$total_products = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_products / $per_page);
$stmt->close();

// Ürünleri çek
if (!empty($search)) {
    $sql = "SELECT * FROM products WHERE product_name LIKE ? OR imei_number LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $search_param = "%$search%";
    $stmt->bind_param("ssii", $search_param, $search_param, $per_page, $offset);
} else {
    $sql = "SELECT * FROM products ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $per_page, $offset);
}
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Ürünler - CRM</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .search-box {
            display: flex;
            gap: 10px;
            flex: 1;
            max-width: 400px;
        }
        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .search-input:focus { outline: none; border-color: #667eea; }
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
            max-width: 700px;
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
            <a href="products.php" class="nav-item active">
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
            <h1>Ürün Yönetimi</h1>
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
                <h3><?php echo $kpi_result['total_products']; ?></h3>
                <p>Toplam Ürün</p>
            </div>
            <div class="stat-box">
                <h3><?php echo $kpi_result['stock_products']; ?></h3>
                <p>Stokta</p>
            </div>
            <div class="stat-box">
                <h3><?php echo $kpi_result['sold_products']; ?></h3>
                <p>Satıldı</p>
            </div>
            <div class="stat-box">
                <h3>₺<?php echo number_format($kpi_result['stock_value'], 2); ?></h3>
                <p>Stok Değeri</p>
            </div>
        </div>

        <!-- Aksiyon Çubuğu -->
        <div class="action-bar">
            <div class="search-box">
                <form method="GET" style="display: flex; gap: 10px; width: 100%;">
                    <input type="text" name="search" class="search-input" placeholder="Ürün adı veya IMEI ara..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">🔍 Ara</button>
                    <?php if($search): ?>
                        <a href="products.php" class="btn btn-warning">✖ Temizle</a>
                    <?php endif; ?>
                </form>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button onclick="openModal()" class="btn btn-success">➕ Ürün Ekle</button>
                <a href="export-products.php" class="btn btn-primary">📥 Excel İndir</a>
                <a href="sample-products-template.php" class="btn btn-warning">📋 Şablon İndir</a>
                <button onclick="document.getElementById('importFile').click()" class="btn btn-primary">📤 Excel Yükle</button>
                <form id="importForm" method="POST" action="import-products.php" enctype="multipart/form-data" style="display: none;">
                    <input type="file" id="importFile" name="excel_file" accept=".xlsx,.xls" onchange="this.form.submit()">
                </form>
            </div>
        </div>

        <!-- Ürün Tablosu -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Ürün</th>
                        <th>IMEI Numarası</th>
                        <th>Fiyat</th>
                        <th>Durum</th>
                        <th style="text-align: center;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($products->num_rows > 0): ?>
                        <?php while($product = $products->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($product['product_name']); ?></strong><br>
                                    <small style="color: #999;"><?php echo htmlspecialchars($product['model']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($product['imei_number']); ?></td>
                                <td><strong>₺<?php echo number_format($product['total_cost'], 2); ?></strong></td>
                                <td>
                                    <?php if($product['status'] == 'Stokta'): ?>
                                        <span class="badge badge-success">Stokta</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Satıldı</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <button onclick="editProduct(<?php echo $product['id']; ?>)" class="btn btn-edit">✏️ Düzenle</button>
                                    <button onclick="deleteProduct(<?php echo $product['id']; ?>)" class="btn btn-danger">🗑️ Sil</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="no-data">
                                <?php echo $search ? "Arama sonucu bulunamadı." : "Henüz ürün eklenmemiş."; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Sayfalama -->
        <?php
        echo renderPagination($page, $total_pages, 'products.php', [
            'search' => $search,
            'status' => isset($status_filter) ? $status_filter : ''
        ]);
        ?>
    </div>

    <!-- Ürün Ekle/Düzenle Modal -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Yeni Ürün Ekle</h2>
                <span class="close-btn" onclick="closeModal()">&times;</span>
            </div>
            <form id="productForm" method="POST" action="save-product.php">
                <input type="hidden" id="product_id" name="product_id" value="">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Model <span class="required">*</span></label>
                        <select id="model" name="model" required>
                            <option value="">Seçiniz</option>
                            <option value="T0">T0</option>
                            <option value="T15">T15</option>
                            <option value="P56">P56</option>
                            <option value="WT-95A">WT-95A</option>
                            <option value="WT-95C">WT-95C</option>
                            <option value="WT625A">WT625A</option>
                            <option value="DBA">DBA</option>
                            <option value="Moto22">Moto22</option>
                            <option value="Trio Dashcam">Trio Dashcam</option>
                            <option value="SD Kart 125 GB">SD Kart 125 GB</option>
                            <option value="SD Kart 256 GB">SD Kart 256 GB</option>
                            <option value="SD Kart 512 GB">SD Kart 512 GB</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Kategori <span class="required">*</span></label>
                        <select id="category" name="category" required>
                            <option value="">Seçiniz</option>
                            <option value="Telematik">Telematik</option>
                            <option value="Kamera">Kamera</option>
                            <option value="Aksesuar">Aksesuar</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Ürün Adı <span class="required">*</span></label>
                    <input type="text" id="product_name" name="product_name" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Seri Numarası</label>
                        <input type="text" id="serial_number" name="serial_number">
                    </div>
                    
                    <div class="form-group">
                        <label>IMEI Numarası <span class="required">*</span></label>
                        <input type="text" id="imei_number" name="imei_number" required>
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

        function openModal(mode = 'create') {
            const modal = document.getElementById('productModal');
            const title = document.getElementById('modalTitle');
            const form = document.getElementById('productForm');

            if (mode === 'create') {
                title.textContent = 'Yeni Ürün Ekle';
                form.reset();
                document.getElementById('product_id').value = '';
                calculateTotal();
            } else {
                title.textContent = 'Ürün Düzenle';
            }
            modal.classList.add('show');
        }

        function closeModal() {
            document.getElementById('productModal').classList.remove('show');
        }

        async function editProduct(id) {
            try {
                const resp = await fetch('get-product.php?id=' + encodeURIComponent(id), {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store'
                });
                if (!resp.ok) throw new Error('Sunucu hatası: ' + resp.status);

                const data = await resp.json();
                if (data.error) {
                    alert('Hata: ' + data.error);
                    return;
                }

                openModal('edit');

                document.getElementById('product_id').value = data.id;
                document.getElementById('model').value = data.model || '';
                document.getElementById('product_name').value = data.product_name || '';
                document.getElementById('serial_number').value = data.serial_number || '';
                document.getElementById('imei_number').value = data.imei_number || '';
                document.getElementById('cost_price').value = data.cost_price ?? '';
                document.getElementById('vat').value = data.vat ?? '';
                document.getElementById('total_cost').value = data.total_cost ?? '';
                document.getElementById('category').value = data.category || '';
                document.getElementById('description').value = data.description || '';

            } catch (err) {
                alert('Bağlantı/işleme hatası: ' + err.message);
            }
        }

        function deleteProduct(id) {
            if (confirm('Bu ürünü silmek istediğinize emin misiniz?')) {
                window.location.href = 'delete-product.php?id=' + encodeURIComponent(id);
            }
        }

        window.onclick = function (event) {
            const modal = document.getElementById('productModal');
            if (event.target === modal) {
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
</body>
</html>