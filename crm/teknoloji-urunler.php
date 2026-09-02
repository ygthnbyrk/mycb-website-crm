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

// Bu sayfa Araç Takip (Telematik) tarafından tamamen ayrı: sadece Kamera/Aksesuar/Hizmet.
$teknoloji_categories = ['Kamera', 'Aksesuar', 'Hizmet'];

// Arama, Kategori/Durum Filtresi ve Sayfalama
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$category_filter = in_array($_GET['category'] ?? '', $teknoloji_categories, true) ? $_GET['category'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

$return_qs = http_build_query(array_filter([
    'search' => $search,
    'status' => $status_filter,
    'category' => $category_filter,
    'page' => $page > 1 ? $page : '',
]));

$category_placeholders = implode(',', array_fill(0, count($teknoloji_categories), '?'));

// KPI'lar
$kpi_stmt = $conn->prepare("
    SELECT
        COUNT(*) as total_products,
        SUM(CASE WHEN status = 'Stokta' THEN 1 ELSE 0 END) as stock_products,
        SUM(CASE WHEN status = 'Pasif' THEN 1 ELSE 0 END) as passive_products,
        SUM(CASE WHEN status = 'Satıldı' THEN 1 ELSE 0 END) as sold_products
    FROM products
    WHERE category IN ($category_placeholders)
");
$kpi_stmt->bind_param(str_repeat('s', count($teknoloji_categories)), ...$teknoloji_categories);
$kpi_stmt->execute();
$kpi_result = $kpi_stmt->get_result()->fetch_assoc();
$kpi_stmt->close();

// Dinamik WHERE
$where_conditions = ["category IN ($category_placeholders)"];
$params = $teknoloji_categories;
$types = str_repeat('s', count($teknoloji_categories));

if (!empty($search)) {
    $where_conditions[] = "(product_name LIKE ? OR model LIKE ? OR imei_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if ($category_filter !== '') {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
    $types .= 's';
}

if (in_array($status_filter, ['Stokta', 'Pasif', 'Satıldı'], true)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Toplam sayı
$count_sql = "SELECT COUNT(*) as total FROM products $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$total_products = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_products / $per_page);
$stmt->close();

// Ürünleri çek
$sql = "SELECT * FROM products $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
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
    <title>Ürünler - Teknoloji - CRM</title>
</head>
<body>
    <?php $active_page = 'teknoloji-urunler'; include 'partials/sidebar-teknoloji.php'; ?>

    <!-- Ana İçerik -->
    <div class="main-content">
        <div class="top-bar">
            <h1><?php echo icon('cpu'); ?> Teknoloji Ürünleri</h1>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
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
                <h3><?php echo $kpi_result['passive_products']; ?></h3>
                <p>Pasif</p>
            </div>
            <div class="stat-box">
                <h3><?php echo $kpi_result['sold_products']; ?></h3>
                <p>Satıldı</p>
            </div>
        </div>

        <!-- Kategori Filtresi -->
        <div class="filter-row" style="margin-bottom: 8px;">
            <?php $sq = $search ? '&search=' . urlencode($search) : ''; $stq = $status_filter ? '&status=' . urlencode($status_filter) : ''; ?>
            <a href="?category=<?php echo $sq . $stq; ?>" class="btn btn-light <?php echo $category_filter === '' ? 'active' : ''; ?>">Tüm Kategoriler</a>
            <a href="?category=Kamera<?php echo $sq . $stq; ?>" class="btn btn-light <?php echo $category_filter === 'Kamera' ? 'active' : ''; ?>">Kamera</a>
            <a href="?category=Aksesuar<?php echo $sq . $stq; ?>" class="btn btn-light <?php echo $category_filter === 'Aksesuar' ? 'active' : ''; ?>">Aksesuar</a>
            <a href="?category=Hizmet<?php echo $sq . $stq; ?>" class="btn btn-light <?php echo $category_filter === 'Hizmet' ? 'active' : ''; ?>">Hizmet</a>
        </div>

        <!-- Durum Filtresi -->
        <div class="filter-row" style="margin-bottom: 12px;">
            <?php $cq = $category_filter ? '&category=' . urlencode($category_filter) : ''; ?>
            <a href="?status=<?php echo $sq . $cq; ?>" class="btn btn-light <?php echo $status_filter === '' ? 'active' : ''; ?>">Tümü</a>
            <a href="?status=Stokta<?php echo $sq . $cq; ?>" class="btn btn-light <?php echo $status_filter === 'Stokta' ? 'active' : ''; ?>">Stoktakiler</a>
            <a href="?status=Pasif<?php echo $sq . $cq; ?>" class="btn btn-light <?php echo $status_filter === 'Pasif' ? 'active' : ''; ?>">Pasif</a>
            <a href="?status=Satıldı<?php echo $sq . $cq; ?>" class="btn btn-light <?php echo $status_filter === 'Satıldı' ? 'active' : ''; ?>">Satılanlar</a>
        </div>

        <!-- Aksiyon Çubuğu -->
        <div class="action-bar">
            <div class="search-box">
                <form method="GET" style="display: flex; gap: 10px; width: 100%;">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                    <input type="text" name="search" class="search-input" placeholder="Ürün, model veya IMEI ara..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary"><?php echo icon('search'); ?> Ara</button>
                    <?php if ($search): ?>
                        <a href="?status=<?php echo htmlspecialchars($status_filter) . $cq; ?>" class="btn btn-secondary"><?php echo icon('x'); ?> Temizle</a>
                    <?php endif; ?>
                </form>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button onclick="openModal()" class="btn btn-primary"><?php echo icon('plus'); ?> Ürün Ekle</button>
            </div>
        </div>

        <!-- Ürün Tablosu -->
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Ürün</th>
                        <th>Kategori</th>
                        <th>IMEI / Seri</th>
                        <th>Fiyat</th>
                        <th>Durum</th>
                        <th style="text-align: center;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($products->num_rows > 0): ?>
                        <?php while ($product = $products->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($product['product_name']); ?></strong><br>
                                    <small style="color: var(--text-muted);"><?php echo htmlspecialchars($product['model']); ?></small>
                                </td>
                                <td><span class="badge badge-gray"><?php echo htmlspecialchars($product['category']); ?></span></td>
                                <td>
                                    <?php
                                        $identifier = $product['imei_number'] ?: $product['serial_number'];
                                        echo $identifier ? htmlspecialchars($identifier) : '<span style="color:var(--text-muted);">—</span>';
                                    ?>
                                </td>
                                <td><strong>₺<?php echo number_format($product['total_cost'], 2); ?></strong></td>
                                <td>
                                    <?php if ($product['status'] == 'Stokta'): ?>
                                        <span class="badge badge-green">Stokta</span>
                                    <?php elseif ($product['status'] == 'Pasif'): ?>
                                        <span class="badge badge-orange">Pasif</span>
                                    <?php else: ?>
                                        <span class="badge badge-red">Satıldı</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <div class="action-btns">
                                        <button onclick="editProduct(<?php echo $product['id']; ?>)" class="icon-btn btn-edit" title="Düzenle"><?php echo icon('edit'); ?></button>
                                        <?php if ($product['status'] === 'Stokta'): ?>
                                            <button onclick="toggleProductStatus(<?php echo $product['id']; ?>, 'Pasif', '<?php echo $return_qs; ?>')" class="icon-btn btn-pause" title="Pasife Al"><?php echo icon('x'); ?></button>
                                        <?php elseif ($product['status'] === 'Pasif'): ?>
                                            <button onclick="toggleProductStatus(<?php echo $product['id']; ?>, 'Stokta', '<?php echo $return_qs; ?>')" class="icon-btn btn-activate" title="Stoğa Al"><?php echo icon('refresh'); ?></button>
                                        <?php endif; ?>
                                        <button onclick="deleteProduct(<?php echo $product['id']; ?>)" class="icon-btn btn-delete" title="Sil"><?php echo icon('trash'); ?></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="no-data">
                                <?php echo ($search || $status_filter || $category_filter) ? "Filtrelere uygun sonuç bulunamadı." : "Henüz ürün eklenmemiş."; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Sayfalama -->
        <?php
        echo renderPagination($page, $total_pages, 'teknoloji-urunler.php', [
            'search' => $search,
            'status' => $status_filter,
            'category' => $category_filter,
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
                <input type="hidden" name="from" value="teknoloji-urunler.php">

                <div class="form-row">
                    <div class="form-group">
                        <label>Model <span class="required">*</span></label>
                        <select id="model" name="model" onchange="toggleCustomModel()" required>
                            <option value="">Seçiniz</option>
                            <option value="WT-95A">WT-95A</option>
                            <option value="WT-95C">WT-95C</option>
                            <option value="WT625A">WT625A</option>
                            <option value="Trio Dashcam">Trio Dashcam</option>
                            <option value="İç-Dış 2K Smart Dashcam">İç-Dış 2K Smart Dashcam</option>
                            <option value="SD Kart 128 GB">SD Kart 128 GB</option>
                            <option value="SD Kart 256 GB">SD Kart 256 GB</option>
                            <option value="SD Kart 512 GB">SD Kart 512 GB</option>
                            <option value="3M Kablo">3M Kablo</option>
                            <option value="5M Kablo">5M Kablo</option>
                            <option value="7M Kablo">7M Kablo</option>
                            <option value="10M Kablo">10M Kablo</option>
                            <option value="Montaj">Montaj</option>
                            <option value="__custom__">+ Ek Kamera / Diğer (yeni model gir)</option>
                        </select>
                        <input type="text" id="model_custom" name="model_custom" placeholder="Yeni model adını yazın" style="display:none; margin-top: 8px;">
                    </div>

                    <div class="form-group">
                        <label>Kategori <span class="required">*</span></label>
                        <select id="category" name="category" required>
                            <option value="">Seçiniz</option>
                            <option value="Kamera">Kamera</option>
                            <option value="Aksesuar">Aksesuar</option>
                            <option value="Hizmet">Hizmet</option>
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
                        <label>IMEI Numarası</label>
                        <input type="text" id="imei_number" name="imei_number">
                        <small style="color: var(--text-muted);">İsteğe bağlı — kablo/Montaj gibi kalemlerde boş bırakabilirsiniz.</small>
                    </div>
                </div>

                <div class="form-group" id="quantity_group">
                    <label>Adet</label>
                    <input type="number" step="1" min="1" id="quantity" name="quantity" value="1">
                    <small style="color: var(--text-muted);">Aynı üründen birden fazla stoğa eklemek için adet girin (IMEI'li ürünlerde adet 1 olmalı).</small>
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

        function toggleCustomModel() {
            const sel = document.getElementById('model');
            const custom = document.getElementById('model_custom');
            if (sel.value === '__custom__') {
                custom.style.display = 'block';
                custom.required = true;
                custom.focus();
            } else {
                custom.style.display = 'none';
                custom.required = false;
            }
        }

        function ensureModelOption(value) {
            const sel = document.getElementById('model');
            if (!value) return;
            const exists = Array.from(sel.options).some(o => o.value === value);
            if (!exists) {
                const opt = document.createElement('option');
                opt.value = value;
                opt.textContent = value;
                sel.insertBefore(opt, sel.options[sel.options.length - 1]);
            }
        }

        function openModal(mode = 'create') {
            const modal = document.getElementById('productModal');
            const title = document.getElementById('modalTitle');
            const form = document.getElementById('productForm');

            if (mode === 'create') {
                title.textContent = 'Yeni Ürün Ekle';
                form.reset();
                document.getElementById('product_id').value = '';
                document.getElementById('model_custom').style.display = 'none';
                document.getElementById('model_custom').required = false;
                document.getElementById('quantity_group').style.display = 'block';
                document.getElementById('quantity').value = '1';
                calculateTotal();
            } else {
                title.textContent = 'Ürün Düzenle';
                document.getElementById('quantity_group').style.display = 'none';
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
                ensureModelOption(data.model || '');
                document.getElementById('model').value = data.model || '';
                document.getElementById('model_custom').style.display = 'none';
                document.getElementById('model_custom').required = false;
                document.getElementById('model_custom').value = '';
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
                window.location.href = 'delete-product.php?id=' + encodeURIComponent(id) + '&from=teknoloji-urunler.php';
            }
        }

        function toggleProductStatus(id, status, returnQs) {
            const msg = status === 'Pasif'
                ? 'Bu ürünü pasife almak istediğinizden emin misiniz? Stok listesinde görünmeyecek.'
                : 'Bu ürünü tekrar stoğa almak istediğinizden emin misiniz?';
            if (confirm(msg)) {
                let url = 'toggle-product-status.php?id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(status) + '&from=teknoloji-urunler.php';
                if (returnQs) url += '&return=' + encodeURIComponent(returnQs);
                window.location.href = url;
            }
        }

        window.onclick = function (event) {
            const modal = document.getElementById('productModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        document.getElementById('productForm').addEventListener('submit', function (e) {
            const imei = document.getElementById('imei_number').value.trim();
            const quantity = parseInt(document.getElementById('quantity').value) || 1;
            if (!document.getElementById('product_id').value && imei && quantity > 1) {
                e.preventDefault();
                alert('IMEI numarası girilen ürünlerde adet 1 olmalı (her IMEI tekil bir cihazdır). Birden fazla eklemek için IMEI\'yi boş bırakın veya ürünleri tek tek girin.');
                return;
            }

            const sel = document.getElementById('model');
            if (sel.value === '__custom__') {
                const customVal = document.getElementById('model_custom').value.trim();
                if (!customVal) {
                    e.preventDefault();
                    alert('Lütfen yeni model adını girin.');
                    return;
                }
                const opt = document.createElement('option');
                opt.value = customVal;
                opt.textContent = customVal;
                opt.selected = true;
                sel.appendChild(opt);
            }
        });
    </script>
</body>
</html>
