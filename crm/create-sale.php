<?php
require_once 'config.php';
require_once 'partials/icons.php';

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
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Yeni Satış - CRM</title>
</head>
<body>
    <?php $active_page = 'create-sale'; include 'partials/sidebar.php'; ?>

    <!-- Ana İçerik -->
    <div class="main-content">
        <div class="top-bar">
            <h1><?php echo icon('dollar'); ?> Yeni Satış Oluştur</h1>
        </div>

        <form id="saleForm" method="POST" action="save-sale.php">
            <!-- Genel Bilgiler -->
            <div class="card">
                <div class="card-header"><?php echo icon('clock'); ?> Genel Bilgiler</div>
                
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
                    <strong><?php echo icon('check'); ?> Seçili Müşteri:</strong>
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
                <div class="card-header"><?php echo icon('package'); ?> Cihazlar</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>IMEI Numarası Ara</label>
                        <input type="text" id="product_imei_search" placeholder="IMEI numarası yazın..." autocomplete="off">
                        <div id="product_dropdown" class="autocomplete-dropdown"></div>
                    </div>
                </div>

                <div id="product_list" class="item-list" style="display: none;">
                    <div class="item-row" style="background: var(--bg-page); font-weight: 600;">
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
                <div class="card-header"><?php echo icon('sim'); ?> Sim Kartlar</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Telefon Numarası Ara</label>
                        <input type="text" id="simcard_phone_search" placeholder="Telefon numarası yazın..." autocomplete="off">
                        <div id="simcard_dropdown" class="autocomplete-dropdown"></div>
                    </div>
                </div>

                <div id="simcard_list" class="item-list" style="display: none;">
                    <div class="item-row" style="background: var(--bg-page); font-weight: 600;">
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
                <div class="card-header"><?php echo icon('refresh'); ?> Ürün-Sim Kart Eşleştirme</div>
                <div id="mapping_area" class="mapping-area">
                    <div class="no-items">Önce ürün ve sim kart ekleyin</div>
                </div>
            </div>

            <!-- Özet -->
            <div class="card">
                <div class="card-header"><?php echo icon('dollar'); ?> Fiyat Özeti</div>
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

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px; font-size: 16px;">
                    <?php echo icon('check'); ?> Satışı Kaydet
                </button>
            </div>
        </form>
    </div>

    <script>
        let products = [];
        let simcards = [];
        let mappings = [];
        let searchTimeout;
        const trashIconSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>';

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
                        <input type="text" placeholder="Plaka" value="${escapeHtml(p.plate)}" onchange="products[${i}].plate = this.value">
                    </div>
                    <div>
                        <input type="number" step="0.01" value="${p.price}" onchange="products[${i}].price = parseFloat(this.value); updateSummary();">
                    </div>
                    <div>
                        <button type="button" onclick="removeProduct(${i})" class="icon-btn btn-delete" title="Kaldır">${trashIconSvg}</button>
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
                        <input type="number" step="0.01" value="${s.price}" onchange="simcards[${i}].price = parseFloat(this.value); updateSummary();">
                    </div>
                    <div>
                        <button type="button" onclick="removeSimcard(${i})" class="icon-btn btn-delete" title="Kaldır">${trashIconSvg}</button>
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
                        <select onchange="updateMapping(${pi}, this.value)">
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
</body>
</html>