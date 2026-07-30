<?php
require_once 'config.php';
require_once 'partials/icons.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$sql = "SELECT t.*,
    p.model AS mp_model, p.imei_number AS mp_imei, p.total_cost AS mp_total_cost,
    s.phone_number AS ms_phone, s.operator AS ms_operator, s.total_cost AS ms_total_cost,
    c.name AS mc_name, c.tax_number AS mc_tax, c.phone AS mc_phone
    FROM telegram_pending_matches t
    LEFT JOIN products p ON t.matched_product_id = p.id
    LEFT JOIN simcards s ON t.matched_simcard_id = s.id
    LEFT JOIN customers c ON t.matched_customer_id = c.id
    WHERE t.status = 'pending'
    ORDER BY t.created_at DESC";
$result = $conn->query($sql);
$pending_rows = $result->fetch_all(MYSQLI_ASSOC);

$collecting_count = $conn->query("SELECT COUNT(*) as c FROM telegram_pending_matches WHERE status = 'collecting'")->fetch_assoc()['c'];

$customer_sql = "SELECT t.*, c.name AS mc_name, c.tax_number AS mc_tax, c.address AS mc_address
    FROM telegram_pending_customers t
    LEFT JOIN customers c ON t.matched_customer_id = c.id
    WHERE t.status = 'pending'
    ORDER BY t.created_at DESC";
$pending_customer_rows = $conn->query($customer_sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Telegram Onay - CRM</title>
    <style>
        .tg-card { margin-bottom: 20px; }
        .tg-photos { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .tg-photos img { max-width: 220px; max-height: 220px; border-radius: 8px; border: 1px solid var(--border); object-fit: cover; }
        .tg-ocr-box { background: var(--bg-page); border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; font-size: 14px; }
        .tg-ocr-box strong { display: inline-block; min-width: 90px; }
        .tg-section { border-top: 1px solid var(--border); padding-top: 14px; margin-top: 14px; }
        .tg-toggle { display: flex; gap: 16px; margin-bottom: 10px; font-size: 14px; }
        .tg-new-fields, .tg-existing-fields { display: none; }
        .tg-new-fields.show, .tg-existing-fields.show { display: block; }
        .tg-reject-form { display: inline-block; margin-left: 10px; }
    </style>
</head>
<body>
    <?php $active_page = 'telegram-review'; include 'partials/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h1><?php echo icon('bell'); ?> Telegram Onay Kuyruğu</h1>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php if ($collecting_count > 0): ?>
            <div class="alert alert-warning">
                <?php echo $collecting_count; ?> kayıt ikinci fotoğrafı bekliyor (henüz tam gelmedi), listede görünmeyecek.
            </div>
        <?php endif; ?>

        <?php if (empty($pending_rows)): ?>
            <div class="card"><div style="padding: 20px;">Onay bekleyen kayıt yok.</div></div>
        <?php endif; ?>

        <?php foreach ($pending_rows as $row):
            $rid = $row['id'];
            $has_photo2 = !empty($row['photo_2_file_id']);
            $product_matched = !empty($row['matched_product_id']);
            $simcard_matched = !empty($row['matched_simcard_id']);
            // OCR hiç cihaz/sim tespit etmediyse (örn. tek sim kart fotoğrafı gönderildiyse)
            // "Yeni oluştur"u boş alanlarla zorlamak yerine baştan "Yok" seçili gelsin.
            $product_ocr_empty = empty($row['ocr_imei']) && empty($row['ocr_serial']) && empty($row['ocr_model_guess']);
            $simcard_ocr_empty = empty($row['ocr_phone_number']) && empty($row['ocr_operator_guess']);
            $product_default_none = !$product_matched && $product_ocr_empty;
            $simcard_default_none = !$simcard_matched && $simcard_ocr_empty;
        ?>
        <div class="card tg-card">
            <div class="card-header">
                <?php echo icon('clock'); ?>
                <?php echo htmlspecialchars($row['customer_name_raw'] ?: 'Müşteri okunamadı'); ?>
                <?php if ($row['plate_raw']): ?> / <?php echo htmlspecialchars($row['plate_raw']); ?><?php endif; ?>
                <small style="float:right; color: var(--text-secondary);"><?php echo date('d.m.Y H:i', strtotime($row['created_at'])); ?></small>
            </div>
            <div style="padding: 16px;">

                <?php if ($row['error_message']): ?>
                    <div class="alert alert-danger">Otomatik okuma hatası: <?php echo htmlspecialchars($row['error_message']); ?> — alanları manuel doldurun.</div>
                <?php endif; ?>

                <div class="tg-photos">
                    <img src="telegram-photo.php?id=<?php echo $rid; ?>&slot=1" alt="Foto 1">
                    <?php if ($has_photo2): ?>
                        <img src="telegram-photo.php?id=<?php echo $rid; ?>&slot=2" alt="Foto 2">
                    <?php endif; ?>
                </div>

                <div class="tg-ocr-box">
                    <div><strong>Cihaz:</strong>
                        IMEI: <?php echo htmlspecialchars($row['ocr_imei'] ?: '-'); ?>,
                        Seri: <?php echo htmlspecialchars($row['ocr_serial'] ?: '-'); ?>,
                        Model: <?php echo htmlspecialchars($row['ocr_model_guess'] ?: '-'); ?>
                        <?php echo $product_matched ? ' <span style="color: var(--success);">✓ stokta eşleşti</span>' : ' <span style="color: var(--warning);">⚠ eşleşmedi</span>'; ?>
                    </div>
                    <div><strong>Sim Kart:</strong>
                        Tel: <?php echo htmlspecialchars($row['ocr_phone_number'] ?: '-'); ?>,
                        Operatör tahmini: <?php echo htmlspecialchars($row['ocr_operator_guess'] ?: '-'); ?>
                        <?php echo $simcard_matched ? ' <span style="color: var(--success);">✓ stokta eşleşti</span>' : ' <span style="color: var(--warning);">⚠ eşleşmedi</span>'; ?>
                    </div>
                    <?php if ($row['ocr_notes']): ?><div><strong>Not:</strong> <?php echo htmlspecialchars($row['ocr_notes']); ?></div><?php endif; ?>
                </div>

                <form method="POST" action="telegram-approve.php">
                    <input type="hidden" name="pending_id" value="<?php echo $rid; ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" id="customer_id_<?php echo $rid; ?>" name="customer_id" value="<?php echo $row['matched_customer_id'] ?: ''; ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Satış Tarihi</label>
                            <input type="date" name="sale_date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Plaka</label>
                            <input type="text" name="plate" value="<?php echo htmlspecialchars($row['plate_raw'] ?: ''); ?>">
                        </div>
                    </div>

                    <div class="form-group" style="position: relative;">
                        <label>Müşteri <span class="required">*</span></label>
                        <input type="text" id="customer_search_<?php echo $rid; ?>" placeholder="Müşteri ara..." autocomplete="off"
                               value="<?php echo htmlspecialchars($row['mc_name'] ?: $row['customer_name_raw'] ?: ''); ?>">
                        <div id="customer_dropdown_<?php echo $rid; ?>" class="autocomplete-dropdown"></div>
                    </div>

                    <div class="tg-section">
                        <strong>Cihaz</strong>
                        <div class="tg-toggle">
                            <label><input type="radio" name="product_mode" value="existing" <?php echo $product_matched ? 'checked' : ''; ?> onchange="tgToggle(<?php echo $rid; ?>, 'product', 'existing')"> Mevcut cihazı kullan</label>
                            <label><input type="radio" name="product_mode" value="new" <?php echo (!$product_matched && !$product_default_none) ? 'checked' : ''; ?> onchange="tgToggle(<?php echo $rid; ?>, 'product', 'new')"> Yeni cihaz oluştur</label>
                            <label><input type="radio" name="product_mode" value="none" <?php echo $product_default_none ? 'checked' : ''; ?> onchange="tgToggle(<?php echo $rid; ?>, 'product', 'none')"> Bu satışta cihaz yok</label>
                        </div>

                        <div id="product_existing_<?php echo $rid; ?>" class="tg-existing-fields <?php echo $product_matched ? 'show' : ''; ?>" style="position: relative;">
                            <input type="hidden" id="product_id_<?php echo $rid; ?>" name="product_id" value="<?php echo $row['matched_product_id'] ?: ''; ?>">
                            <input type="text" id="product_search_<?php echo $rid; ?>" placeholder="IMEI veya model ara..." autocomplete="off"
                                   value="<?php echo $product_matched ? htmlspecialchars($row['mp_model'] . ' (' . $row['mp_imei'] . ')') : ''; ?>">
                            <div id="product_dropdown_<?php echo $rid; ?>" class="autocomplete-dropdown"></div>
                        </div>

                        <div id="product_new_<?php echo $rid; ?>" class="tg-new-fields <?php echo (!$product_matched && !$product_default_none) ? 'show' : ''; ?>">
                            <div class="form-row">
                                <div class="form-group"><label>Model</label><input type="text" name="p_model" value="<?php echo htmlspecialchars($row['ocr_model_guess'] ?: ''); ?>"></div>
                                <div class="form-group"><label>IMEI</label><input type="text" name="p_imei" value="<?php echo htmlspecialchars($row['ocr_imei'] ?: ''); ?>"></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label>Seri No</label><input type="text" name="p_serial" value="<?php echo htmlspecialchars($row['ocr_serial'] ?: ''); ?>"></div>
                                <div class="form-group">
                                    <label>Kategori</label>
                                    <select name="p_category">
                                        <option value="Telematik" selected>Telematik</option>
                                        <option value="Kamera">Kamera</option>
                                        <option value="Aksesuar">Aksesuar</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label>Ürün Adı</label><input type="text" name="p_product_name" value="<?php echo htmlspecialchars($row['ocr_model_guess'] ?: ''); ?>"></div>
                                <div class="form-group"><label>Maliyet (₺)</label><input type="number" step="0.01" name="p_cost_price" value="0"></div>
                            </div>
                        </div>

                        <div class="form-group" id="product_price_group_<?php echo $rid; ?>" style="<?php echo $product_default_none ? 'display:none;' : ''; ?>">
                            <label>Satış Fiyatı - Cihaz (₺)</label>
                            <input type="number" step="0.01" id="product_price_<?php echo $rid; ?>" name="product_price" value="<?php echo $product_matched ? htmlspecialchars($row['mp_total_cost']) : '0'; ?>">
                        </div>
                    </div>

                    <div class="tg-section">
                        <strong>Sim Kart</strong>
                        <div class="tg-toggle">
                            <label><input type="radio" name="simcard_mode" value="existing" <?php echo $simcard_matched ? 'checked' : ''; ?> onchange="tgToggle(<?php echo $rid; ?>, 'simcard', 'existing')"> Mevcut sim kartı kullan</label>
                            <label><input type="radio" name="simcard_mode" value="new" <?php echo (!$simcard_matched && !$simcard_default_none) ? 'checked' : ''; ?> onchange="tgToggle(<?php echo $rid; ?>, 'simcard', 'new')"> Yeni sim kart oluştur</label>
                            <label><input type="radio" name="simcard_mode" value="none" <?php echo $simcard_default_none ? 'checked' : ''; ?> onchange="tgToggle(<?php echo $rid; ?>, 'simcard', 'none')"> Bu satışta sim kart yok</label>
                        </div>

                        <div id="simcard_existing_<?php echo $rid; ?>" class="tg-existing-fields <?php echo $simcard_matched ? 'show' : ''; ?>" style="position: relative;">
                            <input type="hidden" id="simcard_id_<?php echo $rid; ?>" name="simcard_id" value="<?php echo $row['matched_simcard_id'] ?: ''; ?>">
                            <input type="text" id="simcard_search_<?php echo $rid; ?>" placeholder="Telefon numarası ara..." autocomplete="off"
                                   value="<?php echo $simcard_matched ? htmlspecialchars($row['ms_phone'] . ' (' . $row['ms_operator'] . ')') : ''; ?>">
                            <div id="simcard_dropdown_<?php echo $rid; ?>" class="autocomplete-dropdown"></div>
                        </div>

                        <div id="simcard_new_<?php echo $rid; ?>" class="tg-new-fields <?php echo (!$simcard_matched && !$simcard_default_none) ? 'show' : ''; ?>">
                            <div class="form-row">
                                <div class="form-group"><label>Telefon Numarası</label><input type="text" name="s_phone" value="<?php echo htmlspecialchars($row['ocr_phone_number'] ?: ''); ?>"></div>
                                <div class="form-group">
                                    <label>Operatör</label>
                                    <select name="s_operator">
                                        <option value="">Seçiniz</option>
                                        <?php foreach (['Vodafone', 'Turkcell', 'Türk Telekom'] as $op): ?>
                                            <option value="<?php echo $op; ?>" <?php echo (stripos($row['ocr_operator_guess'] ?: '', $op) !== false) ? 'selected' : ''; ?>><?php echo $op; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Şirket</label>
                                    <select name="s_company">
                                        <option value="">Seçiniz</option>
                                        <option value="Waystech Bilişim">Waystech Bilişim</option>
                                        <option value="Mycb Teknoloji">Mycb Teknoloji</option>
                                        <option value="Trio Mobil">Trio Mobil</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Kategori</label>
                                    <select name="s_category">
                                        <option value="Sim Kart" selected>Sim Kart</option>
                                        <option value="Yenileme">Yenileme</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group"><label>Maliyet (₺)</label><input type="number" step="0.01" name="s_cost_price" value="0"></div>
                        </div>

                        <div class="form-group" id="simcard_price_group_<?php echo $rid; ?>" style="<?php echo $simcard_default_none ? 'display:none;' : ''; ?>">
                            <label>Satış Fiyatı - Sim (₺)</label>
                            <input type="number" step="0.01" id="simcard_price_<?php echo $rid; ?>" name="simcard_price" value="<?php echo $simcard_matched ? htmlspecialchars($row['ms_total_cost']) : '0'; ?>">
                        </div>
                    </div>

                    <div style="margin-top: 16px;">
                        <button type="submit" class="btn btn-primary"><?php echo icon('check'); ?> Onayla ve Satışı Oluştur</button>
                    </div>
                </form>
                <form method="POST" action="telegram-approve.php" class="tg-reject-form" style="margin-top: -46px; float: right;">
                    <input type="hidden" name="pending_id" value="<?php echo $rid; ?>">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="btn btn-secondary" onclick="return confirm('Bu kayıt reddedilsin mi?');"><?php echo icon('x'); ?> Reddet</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>

        <h2 style="margin: 28px 0 12px;"><?php echo icon('users'); ?> Yeni Müşteri Kayıtları (Vergi Levhası)</h2>

        <?php if (empty($pending_customer_rows)): ?>
            <div class="card"><div style="padding: 20px;">Onay bekleyen vergi levhası yok.</div></div>
        <?php endif; ?>

        <?php foreach ($pending_customer_rows as $crow):
            $cid = $crow['id'];
            $c_matched = !empty($crow['matched_customer_id']);
            $is_pdf = $crow['telegram_media_type'] === 'document';
        ?>
        <div class="card tg-card">
            <div class="card-header">
                <?php echo icon('clock'); ?>
                <?php echo htmlspecialchars($crow['ocr_name'] ?: 'İsim okunamadı'); ?>
                <small style="float:right; color: var(--text-secondary);"><?php echo date('d.m.Y H:i', strtotime($crow['created_at'])); ?></small>
            </div>
            <div style="padding: 16px;">

                <?php if ($crow['error_message']): ?>
                    <div class="alert alert-danger">Otomatik okuma hatası: <?php echo htmlspecialchars($crow['error_message']); ?> — alanları manuel doldurun.</div>
                <?php endif; ?>

                <div class="tg-photos">
                    <?php if ($is_pdf): ?>
                        <a href="telegram-photo.php?id=<?php echo $cid; ?>&source=customer" target="_blank" class="btn btn-secondary">📄 PDF'i Aç</a>
                    <?php else: ?>
                        <img src="telegram-photo.php?id=<?php echo $cid; ?>&source=customer" alt="Vergi Levhası">
                    <?php endif; ?>
                </div>

                <div class="tg-ocr-box">
                    <div><strong>Tür:</strong> <?php echo $crow['ocr_entity_type'] === 'tuzel' ? 'Tüzel (Ltd/A.Ş)' : ($crow['ocr_entity_type'] === 'sahis' ? 'Şahıs İşletmesi' : '-'); ?></div>
                    <?php if ($crow['ocr_notes']): ?><div><strong>Not:</strong> <?php echo htmlspecialchars($crow['ocr_notes']); ?></div><?php endif; ?>
                </div>

                <form method="POST" action="telegram-customer-approve.php">
                    <input type="hidden" name="pending_id" value="<?php echo $cid; ?>">
                    <input type="hidden" name="action" value="approve">

                    <div class="form-row">
                        <div class="form-group">
                            <label>İsim / Unvan <span class="required">*</span></label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($crow['ocr_name'] ?: ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Vergi No / T.C. Kimlik No <span class="required">*</span></label>
                            <input type="text" name="tax_number" value="<?php echo htmlspecialchars($crow['ocr_tax_number'] ?: ''); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Adres</label>
                        <textarea name="address" rows="2"><?php echo htmlspecialchars($crow['ocr_address'] ?: ''); ?></textarea>
                    </div>

                    <div class="tg-toggle">
                        <label><input type="radio" name="customer_mode" value="existing" <?php echo $c_matched ? 'checked' : ''; ?> onchange="tcToggle(<?php echo $cid; ?>, 'existing')"> Mevcut müşteriyi güncelle</label>
                        <label><input type="radio" name="customer_mode" value="new" <?php echo !$c_matched ? 'checked' : ''; ?> onchange="tcToggle(<?php echo $cid; ?>, 'new')"> Yeni müşteri oluştur</label>
                    </div>

                    <div id="tc_existing_<?php echo $cid; ?>" class="tg-existing-fields <?php echo $c_matched ? 'show' : ''; ?>" style="position: relative;">
                        <input type="hidden" id="tc_customer_id_<?php echo $cid; ?>" name="customer_id" value="<?php echo $crow['matched_customer_id'] ?: ''; ?>">
                        <input type="text" id="tc_customer_search_<?php echo $cid; ?>" placeholder="Müşteri ara..." autocomplete="off"
                               value="<?php echo $c_matched ? htmlspecialchars($crow['mc_name']) : ''; ?>">
                        <div id="tc_customer_dropdown_<?php echo $cid; ?>" class="autocomplete-dropdown"></div>
                    </div>

                    <div style="margin-top: 16px;">
                        <button type="submit" class="btn btn-primary"><?php echo icon('check'); ?> Onayla ve Kaydet</button>
                    </div>
                </form>
                <form method="POST" action="telegram-customer-approve.php" class="tg-reject-form" style="margin-top: -46px; float: right;">
                    <input type="hidden" name="pending_id" value="<?php echo $cid; ?>">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="btn btn-secondary" onclick="return confirm('Bu kayıt reddedilsin mi?');"><?php echo icon('x'); ?> Reddet</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
        function tcToggle(cid, mode) {
            document.getElementById('tc_existing_' + cid).classList.toggle('show', mode === 'existing');
        }

        function setupTcAutocomplete(cid) {
            let tcSearchTimeout;
            const search = document.getElementById('tc_customer_search_' + cid);
            const dropdown = document.getElementById('tc_customer_dropdown_' + cid);
            search.addEventListener('input', function (e) {
                const q = e.target.value.trim();
                if (q.length < 2) { dropdown.classList.remove('show'); return; }
                clearTimeout(tcSearchTimeout);
                tcSearchTimeout = setTimeout(() => {
                    fetch('search-customer.php?q=' + encodeURIComponent(q)).then(r => r.json()).then(data => {
                        dropdown.innerHTML = data.length ? data.map(c => `
                            <div class="autocomplete-item" onclick="selectTcCustomer_${cid}(${c.id}, '${escapeHtml(c.name)}')">
                                <strong>${escapeHtml(c.name)}</strong><small>Vergi No: ${escapeHtml(c.tax_number)}</small>
                            </div>`).join('') : '<div class="autocomplete-item"><small>Sonuç yok</small></div>';
                        dropdown.classList.add('show');
                    });
                }, 300);
            });
            window['selectTcCustomer_' + cid] = function (id, name) {
                document.getElementById('tc_customer_id_' + cid).value = id;
                search.value = name;
                dropdown.classList.remove('show');
            };
        }

        <?php foreach ($pending_customer_rows as $crow): ?>
            setupTcAutocomplete(<?php echo $crow['id']; ?>);
        <?php endforeach; ?>

        function tgToggle(rid, kind, mode) {
            document.getElementById(kind + '_existing_' + rid).classList.toggle('show', mode === 'existing');
            document.getElementById(kind + '_new_' + rid).classList.toggle('show', mode === 'new');
            const priceGroup = document.getElementById(kind + '_price_group_' + rid);
            if (priceGroup) priceGroup.style.display = (mode === 'none') ? 'none' : '';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function setupAutocomplete(rid) {
            let searchTimeout;

            const customerSearch = document.getElementById('customer_search_' + rid);
            const customerDropdown = document.getElementById('customer_dropdown_' + rid);
            customerSearch.addEventListener('input', function (e) {
                const q = e.target.value.trim();
                if (q.length < 2) { customerDropdown.classList.remove('show'); return; }
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    fetch('search-customer.php?q=' + encodeURIComponent(q)).then(r => r.json()).then(data => {
                        customerDropdown.innerHTML = data.length ? data.map(c => `
                            <div class="autocomplete-item" onclick="selectCustomer_${rid}(${c.id}, '${escapeHtml(c.name)}')">
                                <strong>${escapeHtml(c.name)}</strong><small>Vergi No: ${escapeHtml(c.tax_number)}</small>
                            </div>`).join('') : '<div class="autocomplete-item"><small>Sonuç yok</small></div>';
                        customerDropdown.classList.add('show');
                    });
                }, 300);
            });
            window['selectCustomer_' + rid] = function (id, name) {
                document.getElementById('customer_id_' + rid).value = id;
                customerSearch.value = name;
                customerDropdown.classList.remove('show');
            };

            const productSearch = document.getElementById('product_search_' + rid);
            const productDropdown = document.getElementById('product_dropdown_' + rid);
            productSearch.addEventListener('input', function (e) {
                const q = e.target.value.trim();
                if (q.length < 3) { productDropdown.classList.remove('show'); return; }
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    fetch('search-products-list.php?q=' + encodeURIComponent(q)).then(r => r.json()).then(data => {
                        productDropdown.innerHTML = data.length ? data.map(p => `
                            <div class="autocomplete-item" onclick="selectProduct_${rid}(${p.id}, '${escapeHtml(p.model)}', '${escapeHtml(p.imei_number)}', ${p.total_cost})">
                                <strong>${escapeHtml(p.model)}</strong><small>IMEI: ${escapeHtml(p.imei_number)}</small>
                            </div>`).join('') : '<div class="autocomplete-item"><small>Sonuç yok</small></div>';
                        productDropdown.classList.add('show');
                    });
                }, 300);
            });
            window['selectProduct_' + rid] = function (id, model, imei, price) {
                document.getElementById('product_id_' + rid).value = id;
                document.getElementById('product_price_' + rid).value = price;
                productSearch.value = model + ' (' + imei + ')';
                productDropdown.classList.remove('show');
            };

            const simcardSearch = document.getElementById('simcard_search_' + rid);
            const simcardDropdown = document.getElementById('simcard_dropdown_' + rid);
            simcardSearch.addEventListener('input', function (e) {
                const q = e.target.value.trim();
                if (q.length < 3) { simcardDropdown.classList.remove('show'); return; }
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    fetch('search-simcards-list.php?q=' + encodeURIComponent(q)).then(r => r.json()).then(data => {
                        simcardDropdown.innerHTML = data.length ? data.map(s => `
                            <div class="autocomplete-item" onclick="selectSimcard_${rid}(${s.id}, '${escapeHtml(s.phone_number)}', '${escapeHtml(s.operator)}', ${s.total_cost})">
                                <strong>${escapeHtml(s.phone_number)}</strong><small>${escapeHtml(s.operator)}</small>
                            </div>`).join('') : '<div class="autocomplete-item"><small>Sonuç yok</small></div>';
                        simcardDropdown.classList.add('show');
                    });
                }, 300);
            });
            window['selectSimcard_' + rid] = function (id, phone, operator, price) {
                document.getElementById('simcard_id_' + rid).value = id;
                document.getElementById('simcard_price_' + rid).value = price;
                simcardSearch.value = phone + ' (' + operator + ')';
                simcardDropdown.classList.remove('show');
            };
        }

        <?php foreach ($pending_rows as $row): ?>
            setupAutocomplete(<?php echo $row['id']; ?>);
        <?php endforeach; ?>

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.form-group')) {
                document.querySelectorAll('.autocomplete-dropdown').forEach(d => d.classList.remove('show'));
            }
        });
    </script>
</body>
</html>
