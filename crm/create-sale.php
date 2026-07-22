<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Yeni Satış - CRM</title>
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
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 20px;
        }
        .card-header {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            color: #333;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group { 
            margin-bottom: 20px;
            position: relative;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        .required { color: red; }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; font-size: 12px; padding: 6px 12px; }
        .btn-danger:hover { background: #c82333; }
        
        /* Autocomplete Dropdown */
        .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #667eea;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        .autocomplete-dropdown.show {
            display: block;
        }
        .autocomplete-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }
        .autocomplete-item:hover {
            background: #f8f9fa;
        }
        .autocomplete-item:last-child {
            border-bottom: none;
        }
        .autocomplete-item strong {
            display: block;
            color: #333;
        }
        .autocomplete-item small {
            color: #666;
            font-size: 12px;
        }
        
        .search-result {
            border: 2px solid #28a745;
            background: #d4edda;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            display: none;
        }
        .search-result.show { display: block; }
        .item-list {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-top: 15px;
        }
        .item-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 50px;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            align-items: center;
        }
        .item-row:last-child { border-bottom: none; }
        .summary-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 16px;
        }
        .summary-row.total {
            border-top: 2px solid #333;
            font-weight: bold;
            font-size: 20px;
            color: #667eea;
            margin-top: 10px;
            padding-top: 15px;
        }
        .mapping-area {
            background: #fff3cd;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .mapping-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            padding: 10px;
            background: white;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        .no-items {
            text-align: center;
            padding: 30px;
            color: #999;
            font-style: italic;
        }
        table { width: 100%; }
        td { padding: 5px; }
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
            <a href="create-sale.php" class="nav-item active">
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
            <h1>Yeni Satış Oluştur</h1>
            <a href="logout.php" class="logout-btn">Çıkış Yap</a>
        </div>

        <form id="saleForm" method="POST" action="save-sale.php">
            <!-- Genel Bilgiler -->
            <div class="card">
                <div class="card-header">📅 Genel Bilgiler</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Satış Tarihi <span class="required">*</span></label>
                        <input type="date" id="sale_date" name="sale_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Müşteri Ara <span class="required">*</span></label>
                        <input type="text" id="customer_search" placeholder="Müşteri adı veya vergi numarası yazın..." autocomplete="off">
                        <div id="customer_dropdown" class="autocomplete-dropdown"></div>
                        <input type="hidden" id="customer_id" name="customer_id" required>
                    </div>
                </div>
                
                <div id="customer_result" class="search-result">
                    <strong>✓ Seçili Müşteri:</strong>
                    <table>
                        <tr>
                            <td><strong>Ad:</strong></td>
                            <td id="selected_customer_name">-</td>
                        </tr>
                        <tr>
                            <td><strong>Vergi No:</strong></td>
                            <td id="selected_customer_tax">-</td>
                        </tr>
                        <tr>
                            <td><strong>Telefon:</strong></td>
                            <td id="selected_customer_phone">-</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Cihazlar -->
            <div class="card">
                <div class="card-header">📦 Cihazlar</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>IMEI Numarası Ara</label>
                        <input type="text" id="product_imei_search" placeholder="IMEI numarası yazın..." autocomplete="off">
                        <div id="product_dropdown" class="autocomplete-dropdown"></div>
                    </div>
                </div>

                <div id="product_list" class="item-list" style="display: none;">
                    <div class="item-row" style="background: #f8f9fa; font-weight: 600;">
                        <div>Cihaz Bilgisi</div>
                        <div>Plaka</div>
                        <div>Fiyat (₺)</div>
                        <div></div>
                    </div>
                    <div id="product_items"></div>
                </div>
                <div id="no_products" class="no-items">Henüz cihaz eklenmedi</div>
            </div>

            <!-- Sim Kartlar -->
            <div class="card">
                <div class="card-header">📱 Sim Kartlar</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Telefon Numarası Ara</label>
                        <input type="text" id="simcard_phone_search" placeholder="Telefon numarası yazın..." autocomplete="off">
                        <div id="simcard_dropdown" class="autocomplete-dropdown"></div>
                    </div>
                </div>

                <div id="simcard_list" class="item-list" style="display: none;">
                    <div class="item-row" style="background: #f8f9fa; font-weight: 600;">
                        <div>Sim Kart Bilgisi</div>
                        <div>Operatör</div>
                        <div>Fiyat (₺)</div>
                        <div></div>
                    </div>
                    <div id="simcard_items"></div>
                </div>
                <div id="no_simcards" class="no-items">Henüz sim kart eklenmedi</div>
            </div>

            <!-- Eşleştirme -->
            <div class="card">
                <div class="card-header">🔗 Ürün-Sim Kart Eşleştirme</div>
                <div id="mapping_area" class="mapping-area">
                    <div class="no-items">Önce ürün ve sim kart ekleyin</div>
                </div>
            </div>

            <!-- Özet -->
            <div class="card">
                <div class="card-header">💵 Fiyat Özeti</div>
                <div class="summary-box">
                    <div class="summary-row">
                        <span>Ara Toplam:</span>
                        <strong id="subtotal_display">₺0.00</strong>
                    </div>
                    <div class="summary-row">
                        <span>KDV (%20):</span>
                        <strong id="vat_display">₺0.00</strong>
                    </div>
                    <div class="summary-row total">
                        <span>GENEL TOPLAM:</span>
                        <strong id="total_display">₺0.00</strong>
                    </div>
                </div>

                <input type="hidden" id="subtotal" name="subtotal">
                <input type="hidden" id="vat" name="vat">
                <input type="hidden" id="total" name="total">
                <input type="hidden" id="products_data" name="products_data">
                <input type="hidden" id="simcards_data" name="simcards_data">
                <input type="hidden" id="mappings_data" name="mappings_data">

                <button type="submit" class="btn btn-success" style="width: 100%; margin-top: 20px; font-size: 18px;">
                    💾 Satışı Kaydet
                </button>
            </div>
        </form>
    </div>

    <script>
        let products = [];
        let simcards = [];
        let mappings = [];
        let searchTimeout;

        // Müşteri arama autocomplete
        const customerSearch = document.getElementById('customer_search');
        const customerDropdown = document.getElementById('customer_dropdown');

        customerSearch.addEventListener('input', function(e) {
            const search = e.target.value.trim();
            
            if (search.length < 2) {
                customerDropdown.classList.remove('show');
                return;
            }

            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                fetch('/crm/search-customer.php?q=' + encodeURIComponent(search))
                    .then(r => r.json())
                    .then(data => {
                        if (data.length > 0) {
                            customerDropdown.innerHTML = data.map(c => `
                                <div class="autocomplete-item" onclick="selectCustomer(${c.id}, '${escapeHtml(c.name)}', '${escapeHtml(c.tax_number)}', '${escapeHtml(c.phone || '')}')">
                                    <strong>${escapeHtml(c.name)}</strong>
                                    <small>Vergi No: ${escapeHtml(c.tax_number)} ${c.phone ? '• ' + escapeHtml(c.phone) : ''}</small>
                                </div>
                            `).join('');
                            customerDropdown.classList.add('show');
                        } else {
                            customerDropdown.innerHTML = '<div class="autocomplete-item"><small>Sonuç bulunamadı</small></div>';
                            customerDropdown.classList.add('show');
                        }
                    });
            }, 300);
        });

        function selectCustomer(id, name, tax, phone) {
            document.getElementById('customer_id').value = id;
            document.getElementById('selected_customer_name').textContent = name;
            document.getElementById('selected_customer_tax').textContent = tax;
            document.getElementById('selected_customer_phone').textContent = phone || '-';
            document.getElementById('customer_result').classList.add('show');
            document.getElementById('customer_search').value = name;
            customerDropdown.classList.remove('show');
        }

        // Ürün arama autocomplete
        const productSearch = document.getElementById('product_imei_search');
        const productDropdown = document.getElementById('product_dropdown');

        productSearch.addEventListener('input', function(e) {
            const search = e.target.value.trim();
            
            if (search.length < 3) {
                productDropdown.classList.remove('show');
                return;
            }

            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                fetch('/crm/search-products-list.php?q=' + encodeURIComponent(search))
                    .then(r => r.json())
                    .then(data => {
                        if (data.length > 0) {
                            productDropdown.innerHTML = data.map(p => `
                                <div class="autocomplete-item" onclick='selectProduct(${JSON.stringify(p)})'>
                                    <strong>${escapeHtml(p.model)}</strong>
                                    <small>IMEI: ${escapeHtml(p.imei_number)} • Fiyat: ₺${p.total_cost}</small>
                                </div>
                            `).join('');
                            productDropdown.classList.add('show');
                        } else {
                            productDropdown.innerHTML = '<div class="autocomplete-item"><small>Sonuç bulunamadı</small></div>';
                            productDropdown.classList.add('show');
                        }
                    });
            }, 300);
        });

        function selectProduct(productData) {
            const product = {
                id: productData.id,
                imei: productData.imei_number,
                model: productData.model,
                price: parseFloat(productData.total_cost),
                plate: ''
            };

            products.push(product);
            renderProducts();
            updateSummary();
            updateMappings();
            productSearch.value = '';
            productDropdown.classList.remove('show');
        }

        // Sim kart arama autocomplete
        const simcardSearch = document.getElementById('simcard_phone_search');
        const simcardDropdown = document.getElementById('simcard_dropdown');

        simcardSearch.addEventListener('input', function(e) {
            const search = e.target.value.trim();
            
            if (search.length < 3) {
                simcardDropdown.classList.remove('show');
                return;
            }

            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                fetch('/crm/search-simcards-list.php?q=' + encodeURIComponent(search))
                    .then(r => r.json())
                    .then(data => {
                        if (data.length > 0) {
                            simcardDropdown.innerHTML = data.map(s => `
                                <div class="autocomplete-item" onclick='selectSimcard(${JSON.stringify(s)})'>
                                    <strong>${escapeHtml(s.phone_number)}</strong>
                                    <small>${escapeHtml(s.operator)} • ${escapeHtml(s.company)} • Fiyat: ₺${s.total_cost}</small>
                                </div>
                            `).join('');
                            simcardDropdown.classList.add('show');
                        } else {
                            simcardDropdown.innerHTML = '<div class="autocomplete-item"><small>Sonuç bulunamadı</small></div>';
                            simcardDropdown.classList.add('show');
                        }
                    });
            }, 300);
        });

       function selectSimcard(simcardData) {
    console.log('=== SIM KART SEÇİLDİ ===');
    console.log('Gelen data:', simcardData);
    console.log('ID:', simcardData.id);
   
    const simcard = {
        id: simcardData.id,
        phone: simcardData.phone_number,
        operator: simcardData.operator,
        price: parseFloat(simcardData.total_cost)
    };

    simcards.push(simcard);
    renderSimcards();
    updateSummary();
    updateMappings();
    simcardSearch.value = '';
    simcardDropdown.classList.remove('show');
}

        function renderProducts() {
            const container = document.getElementById('product_items');
            const noItems = document.getElementById('no_products');
            const list = document.getElementById('product_list');

            if (products.length === 0) {
                list.style.display = 'none';
                noItems.style.display = 'block';
                return;
            }

            list.style.display = 'block';
            noItems.style.display = 'none';

            container.innerHTML = products.map((p, i) => `
                <div class="item-row">
                    <div>
                        <strong>${escapeHtml(p.model)}</strong><br>
                        <small>IMEI: ${escapeHtml(p.imei)}</small>
                    </div>
                    <div>
                        <input type="text" placeholder="Plaka" value="${escapeHtml(p.plate)}" onchange="products[${i}].plate = this.value" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <input type="number" step="0.01" value="${p.price}" onchange="products[${i}].price = parseFloat(this.value); updateSummary();" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <button type="button" onclick="removeProduct(${i})" class="btn btn-danger">🗑️</button>
                    </div>
                </div>
            `).join('');
        }

        function removeProduct(index) {
            products.splice(index, 1);
            renderProducts();
            updateSummary();
            updateMappings();
        }

        function renderSimcards() {
            const container = document.getElementById('simcard_items');
            const noItems = document.getElementById('no_simcards');
            const list = document.getElementById('simcard_list');

            if (simcards.length === 0) {
                list.style.display = 'none';
                noItems.style.display = 'block';
                return;
            }

            list.style.display = 'block';
            noItems.style.display = 'none';

            container.innerHTML = simcards.map((s, i) => `
                <div class="item-row">
                    <div>
                        <strong>${escapeHtml(s.phone)}</strong><br>
                        <small>${escapeHtml(s.operator)}</small>
                    </div>
                    <div>${escapeHtml(s.operator)}</div>
                    <div>
                        <input type="number" step="0.01" value="${s.price}" onchange="simcards[${i}].price = parseFloat(this.value); updateSummary();" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <button type="button" onclick="removeSimcard(${i})" class="btn btn-danger">🗑️</button>
                    </div>
                </div>
            `).join('');
        }

        function removeSimcard(index) {
            simcards.splice(index, 1);
            renderSimcards();
            updateSummary();
            updateMappings();
        }

        function updateSummary() {
            const productTotal = products.reduce((sum, p) => sum + p.price, 0);
            const simcardTotal = simcards.reduce((sum, s) => sum + s.price, 0);
            const subtotal = productTotal + simcardTotal;
            const vat = subtotal * 0.20;
            const total = subtotal + vat;

            document.getElementById('subtotal_display').textContent = '₺' + subtotal.toFixed(2);
            document.getElementById('vat_display').textContent = '₺' + vat.toFixed(2);
            document.getElementById('total_display').textContent = '₺' + total.toFixed(2);

            document.getElementById('subtotal').value = subtotal.toFixed(2);
            document.getElementById('vat').value = vat.toFixed(2);
            document.getElementById('total').value = total.toFixed(2);
        }

        function updateMappings() {
            const container = document.getElementById('mapping_area');

            if (products.length === 0 || simcards.length === 0) {
                container.innerHTML = '<div class="no-items">Önce ürün ve sim kart ekleyin</div>';
                return;
            }

            container.innerHTML = products.map((p, pi) => `
                <div class="mapping-row">
                    <div>
                        <strong>Cihaz:</strong> ${escapeHtml(p.model)} (${escapeHtml(p.imei)})
                    </div>
                    <div>
                        <label><strong>Sim Kart:</strong></label>
                        <select onchange="updateMapping(${pi}, this.value)" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">Seçiniz</option>
                            ${simcards.map((s, si) => `<option value="${si}">${escapeHtml(s.phone)} (${escapeHtml(s.operator)})</option>`).join('')}
                        </select>
                    </div>
                </div>
            `).join('');
        }

        function updateMapping(productIndex, simcardIndex) {
            if (simcardIndex === '') {
                delete mappings[productIndex];
                return;
            }
            mappings[productIndex] = parseInt(simcardIndex);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Dropdown'ları dışarı tıklayınca kapat
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.form-group')) {
                customerDropdown.classList.remove('show');
                productDropdown.classList.remove('show');
                simcardDropdown.classList.remove('show');
            }
        });

        // Form gönder
        document.getElementById('saleForm').addEventListener('submit', function(e) {
            e.preventDefault();

            if (!document.getElementById('customer_id').value) {
                alert('Lütfen müşteri seçin');
                return;
            }

            if (products.length === 0 && simcards.length === 0) {
                alert('En az bir ürün veya sim kart ekleyin');
                return;
            }

            document.getElementById('products_data').value = JSON.stringify(products);
            document.getElementById('simcards_data').value = JSON.stringify(simcards);
            document.getElementById('mappings_data').value = JSON.stringify(mappings);

            this.submit();
        });
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