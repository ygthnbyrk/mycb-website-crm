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
    <title>Kamera Satışı - CRM</title>
    <style>
        #item_list .item-row { grid-template-columns: 1.8fr 1fr 0.9fr 0.9fr 0.6fr 50px; }
    </style>
</head>
<body>
    <?php $active_page = 'create-camera-sale'; include 'partials/sidebar-teknoloji.php'; ?>

    <!-- Ana İçerik -->
    <div class="main-content">
        <div class="top-bar">
            <h1><?php echo icon('cpu'); ?> Kamera Satışı</h1>
            <a href="camera-sales-list.php" class="btn btn-secondary"><?php echo icon('list'); ?> Kamera Satış Listesi</a>
        </div>

        <form id="cameraSaleForm" method="POST" action="save-camera-sale.php">
            <!-- Genel Bilgiler -->
            <div class="card">
                <div class="card-header"><?php echo icon('clock'); ?> Genel Bilgiler</div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Satış Tarihi</label>
                        <input type="date" id="sale_date" name="sale_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Müşteri Ara <span class="required">*</span></label>
                        <input type="text" id="customer_search" placeholder="Müşteri adı veya vergi numarası yazın..." autocomplete="off">
                        <div id="customer_dropdown" class="autocomplete-dropdown"></div>
                        <input type="hidden" id="customer_id" name="customer_id">
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

            <!-- Kalemler -->
            <div class="card">
                <div class="card-header"><?php echo icon('cpu'); ?> Ürün / Hizmet Kalemleri</div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Kayıtlı Ürün/Hizmet Ara</label>
                        <input type="text" id="item_search" placeholder="Kamera, aksesuar veya hizmet adı yazın..." autocomplete="off">
                        <div id="item_dropdown" class="autocomplete-dropdown"></div>
                    </div>
                    <div class="form-group" style="align-self: end;">
                        <button type="button" class="btn btn-secondary" onclick="addManualItem()"><?php echo icon('plus'); ?> Serbest Kalem Ekle</button>
                    </div>
                </div>

                <div id="item_list" class="item-list" style="display: none;">
                    <div class="item-row" style="background: var(--bg-page); font-weight: 600;">
                        <div>Kalem Adı</div>
                        <div>Kategori</div>
                        <div>Maliyet (₺)</div>
                        <div>Satış Fiyatı (₺)</div>
                        <div>Adet</div>
                        <div></div>
                    </div>
                    <div id="item_rows"></div>
                </div>
                <div id="no_items" class="no-items">Henüz kalem eklenmedi. Kayıtlı bir ürün/hizmet arayın ya da serbest kalem ekleyin.</div>

                <div id="items_summary" class="summary-box" style="display: none; margin-top: 16px;">
                    <div class="summary-row">
                        <span>Toplam Maliyet:</span>
                        <strong id="summary_cost">₺0,00</strong>
                    </div>
                    <div class="summary-row">
                        <span>Toplam Satış:</span>
                        <strong id="summary_sale">₺0,00</strong>
                    </div>
                    <div class="summary-row total">
                        <span>KÂR:</span>
                        <strong id="summary_profit">₺0,00</strong>
                    </div>
                </div>
            </div>

            <!-- Notlar -->
            <div class="card">
                <div class="card-header"><?php echo icon('file-text'); ?> Notlar</div>
                <div class="form-group">
                    <textarea id="notes" name="notes" rows="3" placeholder="İsteğe bağlı"></textarea>
                </div>

                <input type="hidden" id="items_data" name="items_data">

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 12px; font-size: 16px;">
                    <?php echo icon('check'); ?> Kamera Satışını Kaydet
                </button>
                <p style="color: var(--text-muted); font-size: 12.5px; margin-top: 10px;">
                    Kaydedilen her kalem için Abonelikler alanında 2 yıllık (24 ay) yenileme takibi otomatik oluşturulur.
                </p>
            </div>
        </form>
    </div>

    <script>
        let items = [];
        let itemSeq = 0;
        let searchTimeout;

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        // Müşteri arama autocomplete (create-sale.php ile aynı endpoint)
        const customerSearch = document.getElementById('customer_search');
        const customerDropdown = document.getElementById('customer_dropdown');

        customerSearch.addEventListener('input', function (e) {
            const search = e.target.value.trim();
            if (search.length < 2) {
                customerDropdown.classList.remove('show');
                return;
            }
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                fetch('search-customer.php?q=' + encodeURIComponent(search))
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

        // Kayıtlı ürün/hizmet arama
        const itemSearch = document.getElementById('item_search');
        const itemDropdown = document.getElementById('item_dropdown');

        itemSearch.addEventListener('input', function (e) {
            const search = e.target.value.trim();
            if (search.length < 2) {
                itemDropdown.classList.remove('show');
                return;
            }
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                fetch('search-camera-products.php?q=' + encodeURIComponent(search))
                    .then(r => r.json())
                    .then(data => {
                        if (data.length > 0) {
                            itemDropdown.innerHTML = data.map(p => `
                                <div class="autocomplete-item" onclick='selectCatalogItem(${JSON.stringify(p)})'>
                                    <strong>${escapeHtml(p.model)}</strong>
                                    <small>${escapeHtml(p.category)} • Maliyet: ₺${p.cost_price}</small>
                                </div>
                            `).join('');
                            itemDropdown.classList.add('show');
                        } else {
                            itemDropdown.innerHTML = '<div class="autocomplete-item"><small>Sonuç bulunamadı — "Serbest Kalem Ekle" ile manuel girebilirsiniz</small></div>';
                            itemDropdown.classList.add('show');
                        }
                    });
            }, 300);
        });

        function selectCatalogItem(p) {
            items.push({
                seq: itemSeq++,
                product_id: p.id,
                item_name: p.model,
                category: p.category,
                cost_price: p.cost_price || 0,
                sale_price: 0,
                quantity: 1
            });
            renderItems();
            itemSearch.value = '';
            itemDropdown.classList.remove('show');
        }

        function addManualItem() {
            items.push({
                seq: itemSeq++,
                product_id: null,
                item_name: '',
                category: '',
                cost_price: 0,
                sale_price: 0,
                quantity: 1
            });
            renderItems();
        }

        function findItem(seq) {
            return items.find(it => it.seq === seq);
        }

        function renderItems() {
            const container = document.getElementById('item_rows');
            const noItems = document.getElementById('no_items');
            const list = document.getElementById('item_list');

            if (items.length === 0) {
                list.style.display = 'none';
                noItems.style.display = 'block';
                document.getElementById('items_summary').style.display = 'none';
                return;
            }

            list.style.display = 'block';
            noItems.style.display = 'none';

            container.innerHTML = items.map(it => `
                <div class="item-row">
                    <div>
                        <input type="text" placeholder="Kalem adı" value="${escapeHtml(it.item_name)}" onchange="findItem(${it.seq}).item_name = this.value">
                    </div>
                    <div>
                        <input type="text" placeholder="Kategori" value="${escapeHtml(it.category)}" onchange="findItem(${it.seq}).category = this.value">
                    </div>
                    <div>
                        <input type="number" step="0.01" min="0" value="${it.cost_price}" onchange="findItem(${it.seq}).cost_price = parseFloat(this.value) || 0; updateSummary();">
                    </div>
                    <div>
                        <input type="number" step="0.01" min="0" value="${it.sale_price}" onchange="findItem(${it.seq}).sale_price = parseFloat(this.value) || 0; updateSummary();">
                    </div>
                    <div>
                        <input type="number" step="1" min="1" value="${it.quantity}" onchange="findItem(${it.seq}).quantity = parseInt(this.value) || 1; updateSummary();">
                    </div>
                    <div>
                        <button type="button" onclick="removeItem(${it.seq})" class="icon-btn btn-delete" title="Kaldır">${icon_trash()}</button>
                    </div>
                </div>
            `).join('');

            updateSummary();
        }

        function updateSummary() {
            const totalCost = items.reduce((sum, it) => sum + (it.cost_price * it.quantity), 0);
            const totalSale = items.reduce((sum, it) => sum + (it.sale_price * it.quantity), 0);
            const profit = totalSale - totalCost;

            document.getElementById('summary_cost').textContent = '₺' + totalCost.toFixed(2);
            document.getElementById('summary_sale').textContent = '₺' + totalSale.toFixed(2);
            document.getElementById('summary_profit').textContent = '₺' + profit.toFixed(2);
            document.getElementById('items_summary').style.display = 'block';
        }

        function icon_trash() {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>';
        }

        function removeItem(seq) {
            items = items.filter(it => it.seq !== seq);
            renderItems();
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.form-group')) {
                customerDropdown.classList.remove('show');
                itemDropdown.classList.remove('show');
            }
        });

        document.getElementById('cameraSaleForm').addEventListener('submit', function (e) {
            e.preventDefault();

            if (!document.getElementById('customer_id').value) {
                alert('Lütfen müşteri seçin');
                return;
            }

            document.getElementById('items_data').value = JSON.stringify(items.map(({ seq, ...rest }) => rest));
            this.submit();
        });
    </script>
</body>
</html>
