<?php
require_once 'config.php';
require_once 'partials/icons.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_name = $_SESSION['user_name'];
$default_notes = "Fiyatlarımıza KDV dahildir.\nTeklifimiz, geçerlilik tarihine kadar geçerlidir.\nÖdeme koşulları: Sipariş onayında belirlenecektir.";
$default_valid_until = date('Y-m-d', strtotime('+15 days'));
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Yeni Teklif - CRM</title>
</head>
<body>
    <?php $active_page = 'create-quote'; include 'partials/sidebar.php'; ?>

    <!-- Ana İçerik -->
    <div class="main-content">
        <div class="top-bar">
            <h1><?php echo icon('file-text'); ?> Yeni Teklif Oluştur</h1>
        </div>

        <form id="quoteForm" method="POST" action="save-quote.php">
            <!-- Genel Bilgiler -->
            <div class="card">
                <div class="card-header"><?php echo icon('clock'); ?> Genel Bilgiler</div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Teklif Tarihi <span class="required">*</span></label>
                        <input type="date" id="quote_date" name="quote_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Geçerlilik Tarihi</label>
                        <input type="date" id="valid_until" name="valid_until" value="<?php echo $default_valid_until; ?>">
                    </div>
                </div>
            </div>

            <!-- Müşteri -->
            <div class="card">
                <div class="card-header"><?php echo icon('users'); ?> Müşteri Bilgileri</div>

                <div class="form-group">
                    <label>Kayıtlı Müşteri Ara</label>
                    <input type="text" id="customer_search" placeholder="Müşteri adı veya vergi numarası yazın..." autocomplete="off">
                    <div id="customer_dropdown" class="autocomplete-dropdown"></div>
                    <input type="hidden" id="customer_id" name="customer_id">
                </div>
                <p style="color: var(--text-muted); font-size: 12.5px; margin: -8px 0 16px;">
                    Kayıtlı müşteri seçin, ya da sistemde henüz kayıtlı olmayan bir müşteri için aşağıdaki alanları doğrudan doldurun.
                </p>

                <div class="form-row">
                    <div class="form-group">
                        <label>Müşteri / Firma Adı <span class="required">*</span></label>
                        <input type="text" id="customer_name" name="customer_name" required>
                    </div>
                    <div class="form-group">
                        <label>Vergi Numarası</label>
                        <input type="text" id="customer_tax_number" name="customer_tax_number">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Telefon</label>
                        <input type="text" id="customer_phone" name="customer_phone">
                    </div>
                    <div class="form-group">
                        <label>E-posta</label>
                        <input type="email" id="customer_email" name="customer_email">
                    </div>
                </div>

                <div class="form-group">
                    <label>Adres</label>
                    <textarea id="customer_address" name="customer_address" rows="2"></textarea>
                </div>
            </div>

            <!-- Kalemler -->
            <div class="card">
                <div class="card-header"><?php echo icon('package'); ?> Teklif Kalemleri</div>

                <div id="item_list" class="item-list" style="display: none;">
                    <div class="quote-item-row" style="background: var(--bg-page); font-weight: 600;">
                        <div>Ürün / Hizmet</div>
                        <div>Açıklama</div>
                        <div>Miktar</div>
                        <div>Birim Fiyat</div>
                        <div>KDV %</div>
                        <div>Toplam</div>
                        <div></div>
                    </div>
                    <div id="item_rows"></div>
                </div>
                <div id="no_items" class="no-items">Henüz kalem eklenmedi</div>

                <button type="button" onclick="addItem()" class="btn btn-secondary" style="margin-top: 14px;"><?php echo icon('plus'); ?> Satır Ekle</button>
            </div>

            <!-- Notlar -->
            <div class="card">
                <div class="card-header"><?php echo icon('file-text'); ?> Notlar / Şartlar</div>
                <div class="form-group">
                    <textarea id="notes" name="notes" rows="4"><?php echo htmlspecialchars($default_notes); ?></textarea>
                </div>
            </div>

            <!-- Özet -->
            <div class="card">
                <div class="card-header"><?php echo icon('dollar'); ?> Fiyat Özeti</div>
                <div class="summary-box">
                    <div class="summary-row">
                        <span>Ara Toplam:</span>
                        <strong id="subtotal_display">₺0,00</strong>
                    </div>
                    <div class="summary-row">
                        <span>KDV Toplamı:</span>
                        <strong id="vat_display">₺0,00</strong>
                    </div>
                    <div class="summary-row total">
                        <span>GENEL TOPLAM:</span>
                        <strong id="total_display">₺0,00</strong>
                    </div>
                </div>

                <input type="hidden" id="subtotal" name="subtotal">
                <input type="hidden" id="vat_total" name="vat_total">
                <input type="hidden" id="total" name="total">
                <input type="hidden" id="items_data" name="items_data">

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px; font-size: 16px;">
                    <?php echo icon('check'); ?> Teklifi Kaydet
                </button>
            </div>
        </form>
    </div>

    <script>
        let items = [];
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
                fetch('search-customer.php?q=' + encodeURIComponent(search))
                    .then(r => r.json())
                    .then(data => {
                        if (data.length > 0) {
                            customerDropdown.innerHTML = data.map(c => `
                                <div class="autocomplete-item" onclick='selectCustomer(${JSON.stringify(c)})'>
                                    <strong>${escapeHtml(c.name)}</strong>
                                    <small>Vergi No: ${escapeHtml(c.tax_number || '-')} ${c.phone ? '• ' + escapeHtml(c.phone) : ''}</small>
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

        function selectCustomer(c) {
            document.getElementById('customer_id').value = c.id;
            document.getElementById('customer_name').value = c.name || '';
            document.getElementById('customer_tax_number').value = c.tax_number || '';
            document.getElementById('customer_phone').value = c.phone || '';
            document.getElementById('customer_email').value = c.email || '';
            document.getElementById('customer_address').value = c.address || '';
            customerSearch.value = c.name;
            customerDropdown.classList.remove('show');
        }

        // Müşteri adı elle değiştirilirse (yeni bir isim yazılırsa) artık kayıtlı müşteriye bağlı değildir
        document.getElementById('customer_name').addEventListener('input', function() {
            if (this.value !== customerSearch.value) {
                document.getElementById('customer_id').value = '';
            }
        });

        function addItem() {
            items.push({ name: '', description: '', qty: 1, unit_price: 0, vat_rate: 20 });
            renderItems();
            updateSummary();
        }

        function removeItem(index) {
            items.splice(index, 1);
            renderItems();
            updateSummary();
        }

        function lineTotal(item) {
            const qty = parseFloat(item.qty) || 0;
            const price = parseFloat(item.unit_price) || 0;
            const vat = parseFloat(item.vat_rate) || 0;
            return qty * price * (1 + vat / 100);
        }

        function renderItems() {
            const container = document.getElementById('item_rows');
            const noItems = document.getElementById('no_items');
            const list = document.getElementById('item_list');

            if (items.length === 0) {
                list.style.display = 'none';
                noItems.style.display = 'block';
                return;
            }

            list.style.display = 'block';
            noItems.style.display = 'none';

            container.innerHTML = items.map((it, i) => `
                <div class="quote-item-row">
                    <div><input type="text" placeholder="Ürün / hizmet adı" value="${escapeHtml(it.name)}" onchange="items[${i}].name = this.value"></div>
                    <div><input type="text" placeholder="Açıklama" value="${escapeHtml(it.description)}" onchange="items[${i}].description = this.value"></div>
                    <div><input type="number" step="1" min="0" value="${it.qty}" onchange="items[${i}].qty = this.value; renderItems(); updateSummary();"></div>
                    <div><input type="number" step="0.01" min="0" value="${it.unit_price}" onchange="items[${i}].unit_price = this.value; renderItems(); updateSummary();"></div>
                    <div><input type="number" step="0.01" min="0" value="${it.vat_rate}" onchange="items[${i}].vat_rate = this.value; renderItems(); updateSummary();"></div>
                    <div class="line-total">₺${lineTotal(it).toFixed(2)}</div>
                    <div><button type="button" onclick="removeItem(${i})" class="icon-btn btn-delete" title="Kaldır">${trashIconSvg}</button></div>
                </div>
            `).join('');
        }

        function updateSummary() {
            let subtotal = 0;
            let vatTotal = 0;

            items.forEach(it => {
                const qty = parseFloat(it.qty) || 0;
                const price = parseFloat(it.unit_price) || 0;
                const vatRate = parseFloat(it.vat_rate) || 0;
                const lineSubtotal = qty * price;
                subtotal += lineSubtotal;
                vatTotal += lineSubtotal * (vatRate / 100);
            });

            const total = subtotal + vatTotal;

            document.getElementById('subtotal_display').textContent = '₺' + subtotal.toFixed(2);
            document.getElementById('vat_display').textContent = '₺' + vatTotal.toFixed(2);
            document.getElementById('total_display').textContent = '₺' + total.toFixed(2);

            document.getElementById('subtotal').value = subtotal.toFixed(2);
            document.getElementById('vat_total').value = vatTotal.toFixed(2);
            document.getElementById('total').value = total.toFixed(2);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        // Dropdown'ı dışarı tıklayınca kapat
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.form-group')) {
                customerDropdown.classList.remove('show');
            }
        });

        // Form gönder
        document.getElementById('quoteForm').addEventListener('submit', function(e) {
            e.preventDefault();

            if (!document.getElementById('customer_name').value.trim()) {
                alert('Lütfen müşteri adı girin');
                return;
            }

            if (items.length === 0) {
                alert('En az bir kalem ekleyin');
                return;
            }

            document.getElementById('items_data').value = JSON.stringify(items);
            this.submit();
        });

        // Başlangıçta bir satır ile başla
        addItem();
    </script>
</body>
</html>
