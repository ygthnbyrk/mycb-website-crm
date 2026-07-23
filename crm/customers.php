<?php
require_once 'config.php';
require_once 'pagination.php';
require_once 'partials/icons.php';

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
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Müşteriler - CRM</title>
</head>
<body>
    <?php $active_page = 'customers'; include 'partials/sidebar.php'; ?>

    <!-- Ana İçerik -->
    <div class="main-content">
        <div class="top-bar">
            <h1><?php echo icon('users'); ?> Müşteri Yönetimi</h1>
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
                       placeholder="Müşteri adı veya vergi numarası ara..."
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary"><?php echo icon('search'); ?> Ara</button>
                <?php if($search): ?>
                    <a href="customers.php" class="btn btn-secondary"><?php echo icon('x'); ?> Temizle</a>
                <?php endif; ?>
            </form>
            <div class="filter-row">
                <button onclick="openModal()" class="btn btn-primary"><?php echo icon('plus'); ?> Müşteri Ekle</button>
                <a href="export-customers.php" class="btn btn-secondary">Excel İndir</a>
                <a href="sample-template.php" class="btn btn-secondary">Şablon İndir</a>
                <button onclick="document.getElementById('importFile').click()" class="btn btn-secondary"><?php echo icon('upload'); ?> Excel Yükle</button>
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
                                                    class="icon-btn btn-edit" title="Düzenle"><?php echo icon('edit'); ?></button>
                                            <button onclick="deleteCustomer(<?php echo $customer['id']; ?>)"
                                                    class="icon-btn btn-delete" title="Sil"><?php echo icon('trash'); ?></button>
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
                
                <button type="submit" class="btn btn-primary" style="width: 100%;"><?php echo icon('check'); ?> Kaydet</button>
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
            fetch('get-customer.php?id=' + id)
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
                window.location.href = 'delete-customer.php?id=' + id;
            }
        }

        window.onclick = function(event) {
            const modal = document.getElementById('customerModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>