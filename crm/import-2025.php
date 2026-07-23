<?php
require_once 'config.php';
require_once 'partials/icons.php';
set_time_limit(300);

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$stats = null;
$fatal_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['data_file']) && $_FILES['data_file']['error'] === UPLOAD_ERR_OK) {
    $json = file_get_contents($_FILES['data_file']['tmp_name']);
    $groups = json_decode($json, true);

    if (!is_array($groups)) {
        $fatal_error = 'Dosya okunamadı ya da JSON formatı hatalı.';
    } else {
        $stats = [
            'sales_created'      => 0,
            'customers_created'  => 0,
            'products_created'   => 0,
            'products_reused'    => 0,
            'simcards_created'   => 0,
            'simcards_reused'    => 0,
            'already_imported'   => 0,
            'row_errors'         => [],
        ];

        foreach ($groups as $gi => $g) {
            // Bu grup daha onceki bir calistirmada zaten basariyla islendi mi?
            // (ayni dosyayi guvenle tekrar yuklenebilir kilmak icin: bu gruptaki
            // herhangi bir cihaz/sim zaten bir satisa baglanmissa grubu atla)
            $already = false;
            foreach (($g['products'] ?? []) as $p) {
                $imei = trim($p['imei']);
                $chk = $conn->prepare("SELECT 1 FROM sale_products WHERE imei_number = ? LIMIT 1");
                $chk->bind_param("s", $imei);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) { $already = true; }
                $chk->close();
                if ($already) break;
            }
            if (!$already) {
                foreach (($g['simcards'] ?? []) as $s) {
                    $phone = trim($s['phone_number']);
                    $chk = $conn->prepare("SELECT 1 FROM sale_simcards WHERE phone_number = ? LIMIT 1");
                    $chk->bind_param("s", $phone);
                    $chk->execute();
                    if ($chk->get_result()->num_rows > 0) { $already = true; }
                    $chk->close();
                    if ($already) break;
                }
            }
            if ($already) {
                $stats['already_imported']++;
                continue;
            }

            $conn->begin_transaction();
            try {
                // 1) Müşteriyi bul / oluştur
                $customer_id = null;
                $tax = trim($g['tax_number'] ?? '');

                if ($tax !== '') {
                    $stmt = $conn->prepare("SELECT id FROM customers WHERE tax_number = ?");
                    $stmt->bind_param("s", $tax);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res->num_rows > 0) {
                        $customer_id = $res->fetch_assoc()['id'];
                    }
                    $stmt->close();
                }

                if (!$customer_id) {
                    $stmt = $conn->prepare("SELECT id FROM customers WHERE UPPER(TRIM(name)) = UPPER(TRIM(?))");
                    $stmt->bind_param("s", $g['customer_name']);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res->num_rows > 0) {
                        $customer_id = $res->fetch_assoc()['id'];
                    }
                    $stmt->close();
                }

                if (!$customer_id) {
                    $finalTax = $tax !== '' ? $tax : ('BILINMIYOR-' . uniqid());
                    $addressParts = array_filter([$g['il'] ?? '', $g['ilce'] ?? '']);
                    $address = implode('/', $addressParts);
                    $phone = $g['phone'] ?? '';
                    $name = $g['customer_name'];
                    $stmt = $conn->prepare("INSERT INTO customers (name, tax_number, phone, address, created_by) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssi", $name, $finalTax, $phone, $address, $user_id);
                    $stmt->execute();
                    $customer_id = $conn->insert_id;
                    $stmt->close();
                    $stats['customers_created']++;
                }

                // 2) Ürünleri bul / oluştur
                $sale_products = [];
                $subtotal = 0.0;

                foreach (($g['products'] ?? []) as $p) {
                    $imei = trim($p['imei']);
                    $stmt = $conn->prepare("SELECT id, model FROM products WHERE imei_number = ?");
                    $stmt->bind_param("s", $imei);
                    $stmt->execute();
                    $res = $stmt->get_result();

                    if ($res->num_rows > 0) {
                        $row = $res->fetch_assoc();
                        $product_id = $row['id'];
                        $model = $row['model'];
                        $stats['products_reused']++;
                    } else {
                        $price = (float)$p['price'];
                        $cost_price = round($price / 1.20, 2);
                        $vat = round($price - $cost_price, 2);
                        $model = $p['model'];
                        $serial = $p['serial_number'] ?: null;
                        $stmt2 = $conn->prepare("INSERT INTO products (model, product_name, serial_number, imei_number, cost_price, vat, total_cost, category, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'Telematik', 'Satıldı', ?)");
                        $stmt2->bind_param("ssssdddi", $model, $model, $serial, $imei, $cost_price, $vat, $price, $user_id);
                        $stmt2->execute();
                        $product_id = $conn->insert_id;
                        $stmt2->close();
                        $stats['products_created']++;
                    }
                    $stmt->close();

                    $upd = $conn->prepare("UPDATE products SET status='Satıldı' WHERE id=?");
                    $upd->bind_param("i", $product_id);
                    $upd->execute();
                    $upd->close();

                    $price = (float)$p['price'];
                    $plate = $p['plate'] ?: null;
                    $sale_products[] = compact('product_id', 'imei', 'model', 'price', 'plate');
                    $subtotal += $price;
                }

                // 3) Sim kartları bul / oluştur
                $sale_simcards = [];

                foreach (($g['simcards'] ?? []) as $s) {
                    $phone = trim($s['phone_number']);
                    $stmt = $conn->prepare("SELECT id, operator FROM simcards WHERE phone_number = ?");
                    $stmt->bind_param("s", $phone);
                    $stmt->execute();
                    $res = $stmt->get_result();

                    if ($res->num_rows > 0) {
                        $row = $res->fetch_assoc();
                        $simcard_id = $row['id'];
                        $operator = $row['operator'];
                        $stats['simcards_reused']++;
                    } else {
                        $price = (float)$s['price'];
                        $cost_price = round($price / 1.20, 2);
                        $vat = round($price - $cost_price, 2);
                        $operator = $s['operator'];
                        $stmt2 = $conn->prepare("INSERT INTO simcards (phone_number, operator, company, category, status, cost_price, vat, total_cost, created_by) VALUES (?, ?, 'Mycb Teknoloji', 'Sim Kart', 'Satıldı', ?, ?, ?, ?)");
                        $stmt2->bind_param("ssdddi", $phone, $operator, $cost_price, $vat, $price, $user_id);
                        $stmt2->execute();
                        $simcard_id = $conn->insert_id;
                        $stmt2->close();
                        $stats['simcards_created']++;
                    }
                    $stmt->close();

                    $upd = $conn->prepare("UPDATE simcards SET status='Satıldı' WHERE id=?");
                    $upd->bind_param("i", $simcard_id);
                    $upd->execute();
                    $upd->close();

                    $price = (float)$s['price'];
                    $sale_simcards[] = compact('simcard_id', 'phone', 'operator', 'price');
                    $subtotal += $price;
                }

                // 4) Satışı oluştur
                $vat = round($subtotal * 0.20, 2);
                $total = round($subtotal + $vat, 2);
                $sale_date = $g['sale_date'];

                $stmt = $conn->prepare("INSERT INTO sales (sale_date, customer_id, subtotal, vat, total, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sidddi", $sale_date, $customer_id, $subtotal, $vat, $total, $user_id);
                $stmt->execute();
                $sale_id = $conn->insert_id;
                $stmt->close();

                foreach ($sale_products as $sp) {
                    $stmt = $conn->prepare("INSERT INTO sale_products (sale_id, product_id, imei_number, model, price, plate) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iissds", $sale_id, $sp['product_id'], $sp['imei'], $sp['model'], $sp['price'], $sp['plate']);
                    $stmt->execute();
                    $stmt->close();
                }
                foreach ($sale_simcards as $ss) {
                    $stmt = $conn->prepare("INSERT INTO sale_simcards (sale_id, simcard_id, phone_number, operator, price) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("iissd", $sale_id, $ss['simcard_id'], $ss['phone'], $ss['operator'], $ss['price']);
                    $stmt->execute();
                    $stmt->close();
                }

                $conn->commit();
                $stats['sales_created']++;
            } catch (Throwable $e) {
                $conn->rollback();
                $label = ($g['customer_name'] ?? '?') . ' / ' . ($g['sale_date'] ?? '?');
                $stats['row_errors'][] = "Grup #$gi ($label): " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>2025 Satış İçe Aktarma - CRM</title>
</head>
<body class="center-page">
    <div class="center-container" style="max-width: 700px;">
        <div class="center-header">
            <h1>2025 Satış İçe Aktarma</h1>
            <a href="dashboard.php" class="btn btn-secondary"><?php echo icon('x'); ?> Kapat</a>
        </div>

        <?php if ($fatal_error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($fatal_error); ?></div>
        <?php endif; ?>

        <?php if ($stats): ?>
            <div class="alert alert-success">
                <strong><?php echo $stats['sales_created']; ?></strong> satış başarıyla oluşturuldu.
            </div>
            <div class="info-grid" style="grid-template-columns: repeat(2, 1fr);">
                <div class="info-item"><label>Yeni Müşteri</label><p><?php echo $stats['customers_created']; ?></p></div>
                <div class="info-item"><label>Yeni Ürün</label><p><?php echo $stats['products_created']; ?></p></div>
                <div class="info-item"><label>Mevcut Ürün Kullanıldı</label><p><?php echo $stats['products_reused']; ?></p></div>
                <div class="info-item"><label>Yeni Sim Kart</label><p><?php echo $stats['simcards_created']; ?></p></div>
                <div class="info-item"><label>Mevcut Sim Kullanıldı</label><p><?php echo $stats['simcards_reused']; ?></p></div>
                <div class="info-item"><label>Zaten Yüklenmişti (Atlandı)</label><p><?php echo $stats['already_imported']; ?></p></div>
                <div class="info-item"><label>Hatalı Grup</label><p><?php echo count($stats['row_errors']); ?></p></div>
            </div>
            <?php if (!empty($stats['row_errors'])): ?>
                <div class="form-section">
                    <h2>Hatalar</h2>
                    <ul class="error-list">
                        <?php foreach ($stats['row_errors'] as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <div class="actions">
                <a href="sales-list.php" class="btn btn-primary"><?php echo icon('list'); ?> Satış Listesine Git</a>
            </div>
        <?php else: ?>
            <p class="subtitle">Hazırlanan <code>import_2025.json</code> dosyasını seçip yükleyin. İşlem birkaç dakika sürebilir, sayfadan ayrılmayın.</p>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Veri Dosyası (.json)</label>
                    <input type="file" name="data_file" accept=".json" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;"><?php echo icon('upload'); ?> İçe Aktarmayı Başlat</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
