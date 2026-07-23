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
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Ürünler - CRM</title>
</head>
<body>
    <?php $active_page = 'products'; include 'partials/sidebar.php'; ?>

    <!-- Ana İçerik -->
    <div class="main-content">
        <div class="top-bar">
            <h1><?php echo icon('package'); ?> Ürün Yönetimi</h1>
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
                    <button type="submit" class="btn btn-primary"><?php echo icon('search'); ?> Ara</button>
                    <?php if($search): ?>
                        <a href="products.php" class="btn btn-secondary"><?php echo icon('x'); ?> Temizle</a>
                    <?php endif; ?>
                </form>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button onclick="openModal()" class="btn btn-primary"><?php echo icon('plus'); ?> Ürün Ekle</button>
                <a href="export-products.php" class="btn btn-secondary">Excel İndir</a>
                <a href="sample-products-template.php" class="btn btn-secondary">Şablon İndir</a>
                <button onclick="document.getElementById('importFile').click()" class="btn btn-secondary"><?php echo icon('upload'); ?> Excel Yükle</button>
                <form id="importForm" method="POST" action="import-products.php" enctype="multipart/form-data" style="display: none;">
                    <input type="file" id="importFile" name="excel_file" accept=".xlsx,.xls" onchange="this.form.submit()">
                </form>
            </div>
        </div>

        <!-- Ürün Tablosu -->
        <div class="table-wrap">
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
                                    <small style="color: var(--text-muted);"><?php echo htmlspecialchars($product['model']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($product['imei_number']); ?></td>
                                <td><strong>₺<?php echo number_format($product['total_cost'], 2); ?></strong></td>
                                <td>
                                    <?php if($product['status'] == 'Stokta'): ?>
                                        <span class="badge badge-green">Stokta</span>
                                    <?php else: ?>
                                        <span class="badge badge-red">Satıldı</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <div class="action-btns">
                                        <button onclick="editProduct(<?php echo $product['id']; ?>)" class="icon-btn btn-edit" title="Düzenle"><?php echo icon('edit'); ?></button>
                                        <button onclick="deleteProduct(<?php echo $product['id']; ?>)" class="icon-btn btn-delete" title="Sil"><?php echo icon('trash'); ?></button>
                                    </div>
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
                
                <button type="submit" class="btn btn-primary" style="width: 100%;"><?php echo icon('check'); ?> Kaydet</button>
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
</body>
</html>