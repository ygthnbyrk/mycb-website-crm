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
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Sim Kartlar - CRM</title>
</head>
<body>
    <?php $active_page = 'simcards'; include 'partials/sidebar.php'; ?>

    <!-- Ana İçerik -->
    <div class="main-content">
        <div class="top-bar">
            <h1><?php echo icon('sim'); ?> Sim Kart Yönetimi</h1>
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
                        <label>Şirket</label>
                        <select name="company" class="filter-select">
                            <option value="">Tüm Şirketler</option>
                            <option value="Waystech Bilişim" <?php echo $filter_company == 'Waystech Bilişim' ? 'selected' : ''; ?>>Waystech Bilişim</option>
                            <option value="Mycb Teknoloji" <?php echo $filter_company == 'Mycb Teknoloji' ? 'selected' : ''; ?>>Mycb Teknoloji</option>
                            <option value="Trio Mobil" <?php echo $filter_company == 'Trio Mobil' ? 'selected' : ''; ?>>Trio Mobil</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Operatör</label>
                        <select name="operator" class="filter-select">
                            <option value="">Tüm Operatörler</option>
                            <option value="Vodafone" <?php echo $filter_operator == 'Vodafone' ? 'selected' : ''; ?>>Vodafone</option>
                            <option value="Turkcell" <?php echo $filter_operator == 'Turkcell' ? 'selected' : ''; ?>>Turkcell</option>
                            <option value="Türk Telekom" <?php echo $filter_operator == 'Türk Telekom' ? 'selected' : ''; ?>>Türk Telekom</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary"><?php echo icon('filter'); ?> Filtrele</button>

                    <?php if($search || $filter_company || $filter_operator): ?>
                        <a href="simcards.php" class="btn btn-secondary"><?php echo icon('x'); ?> Temizle</a>
                    <?php endif; ?>
                </div>

                <!-- Aktif Filtreler -->
                <?php if($search || $filter_company || $filter_operator): ?>
                    <div class="active-filters">
                        <strong style="color: var(--text-secondary); font-size: 13px;">Aktif Filtreler:</strong>
                        <?php if($search): ?>
                            <div class="filter-tag">
                                Arama: <?php echo htmlspecialchars($search); ?>
                                <span class="remove" onclick="removeFilter('search')">×</span>
                            </div>
                        <?php endif; ?>
                        <?php if($filter_company): ?>
                            <div class="filter-tag">
                                <?php echo htmlspecialchars($filter_company); ?>
                                <span class="remove" onclick="removeFilter('company')">×</span>
                            </div>
                        <?php endif; ?>
                        <?php if($filter_operator): ?>
                            <div class="filter-tag">
                                <?php echo htmlspecialchars($filter_operator); ?>
                                <span class="remove" onclick="removeFilter('operator')">×</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>

            <!-- Butonlar -->
            <div class="action-buttons" style="margin-top: 15px;">
                <button onclick="openModal()" class="btn btn-primary"><?php echo icon('plus'); ?> Sim Kart Ekle</button>
                <a href="export-simcards.php?<?php echo http_build_query(['search' => $search, 'company' => $filter_company, 'operator' => $filter_operator]); ?>" class="btn btn-secondary">Excel İndir</a>
                <a href="sample-simcards-template.php" class="btn btn-secondary">Şablon İndir</a>
                <button onclick="document.getElementById('importFile').click()" class="btn btn-secondary"><?php echo icon('upload'); ?> Excel Yükle</button>
                <form id="importForm" method="POST" action="import-simcards.php" enctype="multipart/form-data" style="display: none;">
                    <input type="file" id="importFile" name="excel_file" accept=".xlsx,.xls" onchange="this.form.submit()">
                </form>
            </div>
        </div>

        <!-- Sim Kart Tablosu -->
        <div class="table-wrap">
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
                                        echo $sim['status'] == 'Stokta' ? 'green' :
                                             ($sim['status'] == 'Pasif' ? 'orange' : 'red');
                                    ?>">
                                        <?php echo $sim['status']; ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <div class="action-btns">
                                        <button onclick="editSimcard(<?php echo $sim['id']; ?>)" class="icon-btn btn-edit" title="Düzenle"><?php echo icon('edit'); ?></button>
                                        <button onclick="deleteSimcard(<?php echo $sim['id']; ?>)" class="icon-btn btn-delete" title="Sil"><?php echo icon('trash'); ?></button>
                                    </div>
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
            fetch('get-simcard.php?id=' + id)
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
                window.location.href = 'delete-simcard.php?id=' + id;
            }
        }

        window.onclick = function(event) {
            const modal = document.getElementById('simcardModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>