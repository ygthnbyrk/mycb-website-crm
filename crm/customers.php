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
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Toplam müşteri sayısı ve arama
if (!empty($search)) {
    $count_sql = "SELECT COUNT(*) as total FROM customers WHERE name LIKE ? OR tax_number LIKE ?";
    $stmt = $conn->prepare($count_sql);
    $search_param = "%$search%";
    $stmt->bind_param("ss", $search_param, $search_param);
} else {
    $count_sql = "SELECT COUNT(*) as total FROM customers";
    $stmt = $conn->prepare($count_sql);
}
$stmt->execute();
$total_customers = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_customers / $per_page);
$stmt->close();

// Müşterileri çek
if (!empty($search)) {
    $sql = "SELECT * FROM customers WHERE name LIKE ? OR tax_number LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $search_param = "%$search%";
    $stmt->bind_param("ssii", $search_param, $search_param, $per_page, $offset);
} else {
    $sql = "SELECT * FROM customers ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $per_page, $offset);
}
$stmt->execute();
$customers = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Müşteriler - CRM</title>
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
        .top-bar h1 { font-size: 24px; color: #333; }
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 15px;
        }
        .stat-box {
            background: white;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .stat-box h3 {
            font-size: 24px;
            color: #667eea;
            margin-bottom: 4px;
        }
        .stat-box p { color: #666; font-size: 12px; }
        .action-bar {
            background: white;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            margin-bottom: 12px;
        }
        .filter-row {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-input {
            flex: 1;
            padding: 7px 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 13px;
            min-width: 200px;
        }
        .search-input:focus { outline: none; border-color: #667eea; }
        .btn {
            padding: 7px 14px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            font-size: 13px;
            white-space: nowrap;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-warning:hover { background: #e0a800; }
        
        /* TABLO KOMPAKT */
        .table-wrapper {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            overflow: hidden;
            height: calc(100vh - 280px);
            display: flex;
            flex-direction: column;
        }
        .table-scroll {
            overflow: auto;
            flex: 1;
        }
        table { 
            width: 100%; 
            border-collapse: collapse;
        }
        thead {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #667eea;
        }
        th {
            padding: 10px 8px;
            text-align: left;
            font-weight: 600;
            color: white;
            font-size: 12px;
            white-space: nowrap;
            border-right: 1px solid rgba(255,255,255,0.2);
        }
        th:last-child { border-right: none; }
        td {
            padding: 10px 8px;
            border-bottom: 1px solid #f0f0f0;
            color: #555;
            font-size: 12px;
            vertical-align: top;
        }
        tbody tr:hover { background: #f8f9fa; }
        .no-data { text-align: center; padding: 40px; color: #999; }
        
        /* İKON BUTONLAR */
        .action-btns {
            display: flex;
            gap: 4px;
            justify-content: center;
        }
        .icon-btn {
            width: 28px;
            height: 28px;
            padding: 0;
            font-size: 13px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .btn-edit { background: #17a2b8; color: white; }
        .btn-edit:hover { background: #138496; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-delete:hover { background: #c82333; }
        
        /* PAGINATION */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        .page-btn {
            padding: 5px 9px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            font-size: 12px;
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
        
        .alert {
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 12px;
            font-size: 13px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* MODAL */
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
            padding: 25px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-header h2 { font-size: 20px; color: #333; }
        .close-btn {
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        .close-btn:hover { color: #333; }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #555;
            font-size: 13px;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 13px;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .required { color: red; }
    </style>
</head>
<body>
   <!-- Hamburger Menu -->
<button class="mobile-menu-btn" onclick="toggleMenu()">☰</button>
    <!-- Sidebar -->
    <div class="sidebar">
        <img src="assets/images/logo-light.png" alt="Logo" style="max-width: 200px; height: auto; margin-bottom: 20px;">
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item">
                <span class="nav-icon">🏠</span>
                <span>Ana Sayfa</span>
            </a>
            <a href="customers.php" class="nav-item active">
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
            <h1>👥 Müşteri Yönetimi</h1>
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
                <h3><?php echo $total_customers; ?></h3>
                <p>Toplam Müşteri</p>
            </div>
        </div>

        <!-- Filtreler -->
        <div class="action-bar">
            <form method="GET" class="filter-row" style="margin-bottom: 8px;">
                <input type="text" name="search" class="search-input" 
                       placeholder="🔍 Müşteri adı veya vergi numarası ara..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">Ara</button>
                <?php if($search): ?>
                    <a href="customers.php" class="btn btn-warning">✖ Temizle</a>
                <?php endif; ?>
            </form>
            <div class="filter-row">
                <button onclick="openModal()" class="btn btn-success">➕ Müşteri Ekle</button>
                <a href="export-customers.php" class="btn btn-primary">📥 Excel İndir</a>
                <a href="sample-template.php" class="btn btn-warning">📋 Şablon İndir</a>
                <button onclick="document.getElementById('importFile').click()" class="btn btn-primary">📤 Excel Yükle</button>
                <form id="importForm" method="POST" action="import-customers.php" enctype="multipart/form-data" style="display: none;">
                    <input type="file" id="importFile" name="excel_file" accept=".xlsx,.xls" onchange="this.form.submit()">
                </form>
            </div>
        </div>

        <!-- Tablo -->
        <div class="table-wrapper">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 200px;">Müşteri Adı</th>
                            <th style="width: 140px;">Vergi No</th>
                            <th style="width: 120px;">Telefon</th>
                            <th style="width: 160px;">Email</th>
                            <th>Adres</th>
                            <th style="width: 70px; text-align: center;">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($customers->num_rows > 0): ?>
                            <?php while($customer = $customers->fetch_assoc()): ?>
                                <tr>
                                    <td><strong style="font-size: 13px;"><?php echo htmlspecialchars($customer['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($customer['tax_number']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['phone'] ?? '-'); ?></td>
                                    <td><small><?php echo htmlspecialchars($customer['email'] ?? '-'); ?></small></td>
                                    <td><small><?php echo htmlspecialchars(substr($customer['address'] ?? '-', 0, 60)); ?></small></td>
                                    <td>
                                        <div class="action-btns">
                                            <button onclick="editCustomer(<?php echo $customer['id']; ?>)" 
                                                    class="icon-btn btn-edit" title="Düzenle">✏️</button>
                                            <button onclick="deleteCustomer(<?php echo $customer['id']; ?>)" 
                                                    class="icon-btn btn-delete" title="Sil">🗑️</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="no-data">
                                    <?php echo $search ? "Arama sonucu bulunamadı." : "Henüz müşteri eklenmemiş."; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sayfalama -->
        <?php
        echo renderPagination($page, $total_pages, 'customers.php', ['search' => $search]);
        ?>
    </div>

    <!-- Modal -->
    <div id="customerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Yeni Müşteri Ekle</h2>
                <span class="close-btn" onclick="closeModal()">&times;</span>
            </div>
            <form id="customerForm" method="POST" action="save-customer.php">
                <input type="hidden" id="customer_id" name="customer_id" value="">
                
                <div class="form-group">
                    <label>Müşteri Adı <span class="required">*</span></label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label>Vergi Numarası <span class="required">*</span></label>
                    <input type="text" id="tax_number" name="tax_number" required>
                </div>
                
                <div class="form-group">
                    <label>E-posta</label>
                    <input type="email" id="email" name="email">
                </div>
                
                <div class="form-group">
                    <label>Telefon</label>
                    <input type="text" id="phone" name="phone">
                </div>
                
                <div class="form-group">
                    <label>Adres</label>
                    <textarea id="address" name="address" rows="2"></textarea>
                </div>
                
                <button type="submit" class="btn btn-success" style="width: 100%;">💾 Kaydet</button>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('customerModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('customerModal').classList.remove('show');
            document.getElementById('customerForm').reset();
            document.getElementById('customer_id').value = '';
            document.getElementById('modalTitle').textContent = 'Yeni Müşteri Ekle';
        }

        function editCustomer(id) {
            fetch('/crm/get-customer.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if(data.error) {
                        alert('Hata: ' + data.error);
                        return;
                    }
                    
                    document.getElementById('modalTitle').textContent = 'Müşteri Düzenle';
                    document.getElementById('customer_id').value = data.id;
                    document.getElementById('name').value = data.name || '';
                    document.getElementById('tax_number').value = data.tax_number || '';
                    document.getElementById('email').value = data.email || '';
                    document.getElementById('phone').value = data.phone || '';
                    document.getElementById('address').value = data.address || '';
                    
                    openModal();
                })
                .catch(error => {
                    alert('Bağlantı hatası: ' + error.message);
                });
        }

        function deleteCustomer(id) {
            if(confirm('Bu müşteriyi silmek istediğinizden emin misiniz?')) {
                window.location.href = '/crm/delete-customer.php?id=' + id;
            }
        }

        window.onclick = function(event) {
            const modal = document.getElementById('customerModal');
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

// Sidebar dışına tıklayınca kapat
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