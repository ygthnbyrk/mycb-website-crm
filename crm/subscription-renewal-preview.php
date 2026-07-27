<?php
require_once 'config.php';
require_once 'partials/icons.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if (!isset($_POST['excel_data']) || empty($_POST['excel_data'])) {
    die('Excel verisi bulunamadı. Lütfen dosyayı tekrar yükleyin. <a href="subscription-renewal-upload.php">Geri dön</a>');
}

$excel_data = json_decode($_POST['excel_data'], true);
if (empty($excel_data) || count($excel_data) < 2) {
    die('Excel dosyası boş veya sadece başlık satırı içeriyor. <a href="subscription-renewal-upload.php">Geri dön</a>');
}

// Tam haric tutulacak firmalar (isim iceriyor mu kontrolu)
$excluded_companies = [
    'EREN ENERJİ ELEKTRİK ÜRETİM',
    'KDZ EREĞLİ HAZIR BETON',
    'OEN ENERJİ ELEKTRİK ELEKTRONİK',
];

function normalize_upper($s) {
    return mb_strtoupper(trim((string)$s), 'UTF-8');
}

function parse_tr_date($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return null;
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $raw, $m)) {
        if (checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
    }
    if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})$/', $raw, $m)) {
        $y = (int)$m[3];
        if ($y < 100) $y = $y > 50 ? 1900 + $y : 2000 + $y;
        if (checkdate((int)$m[2], (int)$m[1], $y)) {
            return sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
        }
    }
    // SheetJS'in bazen urettigi "A/G/YY SS:DD" (orn. "3/1/26 0:00") formati - Ay/Gun/Yil (ABD sirasi)
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})\s+\d{1,2}:\d{2}/', $raw, $m)) {
        $mo = (int)$m[1];
        $da = (int)$m[2];
        $y = (int)$m[3];
        if ($y < 100) $y = $y > 50 ? 1900 + $y : 2000 + $y;
        if (checkdate($mo, $da, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $mo, $da);
        }
    }
    if (is_numeric($raw)) {
        $n = (int)$raw;
        if ($n > 0 && $n < 100000) {
            return date('Y-m-d', ($n - 25569) * 86400);
        }
    }
    return null;
}

$rows = [];
$kastamonu_seen = 0;
for ($i = 1; $i < count($excel_data); $i++) {
    $r = $excel_data[$i];
    while (count($r) < 8) $r[] = '';
    $musteri = trim($r[0]);
    $seri_no = trim($r[1]);
    $sahiplik = trim($r[2]);
    $sure_raw = trim($r[3]);
    $liste_fiyat = trim($r[4]);
    $indirimli_fiyat = trim($r[5]);
    $tarih_raw = trim($r[6]);
    $durum_raw = trim($r[7]);

    if ($musteri === '' && $seri_no === '') continue;

    $is_iptal = mb_strpos(normalize_upper($durum_raw), 'İPTAL') !== false || mb_strpos(normalize_upper($durum_raw), 'IPTAL') !== false;

    $row = [
        'no' => $i,
        'musteri' => $musteri,
        'seri_no' => $seri_no,
        'sure_raw' => $sure_raw,
        'liste_fiyat' => $liste_fiyat,
        'indirimli_fiyat' => $indirimli_fiyat,
        'tarih_raw' => $tarih_raw,
        'durum_raw' => $durum_raw,
        'is_iptal' => $is_iptal,
        'excluded' => false,
        'exclude_reason' => '',
        'match' => null,
        'error' => '',
    ];

    $musteri_upper = normalize_upper($musteri);
    foreach ($excluded_companies as $ec) {
        if (mb_strpos($musteri_upper, normalize_upper($ec)) !== false) {
            $row['excluded'] = true;
            $row['exclude_reason'] = 'Bu firma tamamen hariç tutuluyor';
            break;
        }
    }
    if (!$row['excluded'] && mb_strpos($musteri_upper, 'TAŞIKO') !== false) {
        $kastamonu_seen++;
        if ($kastamonu_seen <= 2) {
            $row['excluded'] = true;
            $row['exclude_reason'] = 'Kastamonu Taşıko - ilk 2 (rastgele hariç tutulan)';
        }
    }

    $seri_no_clean = preg_replace('/[^0-9A-Za-z]/', '', $seri_no);

    if (!$row['excluded'] && $is_iptal) {
        if ($seri_no_clean === '') {
            $row['error'] = 'Seri No (IMEI) boş';
        } else {
            $stmt = $conn->prepare("SELECT id, status FROM subscriptions WHERE REPLACE(REPLACE(REPLACE(REPLACE(TRIM(item_detail),' ',''),CHAR(13),''),CHAR(10),''),CHAR(9),'') = ? AND item_type = 'product'");
            $stmt->bind_param("s", $seri_no_clean);
            $stmt->execute();
            $sub = $stmt->get_result()->fetch_assoc();
            if (!$sub) {
                $row['error'] = 'İptal - CRM\'de bu IMEI için abonelik kaydı yok, işlem yapılmadı';
            } else {
                $row['match'] = [
                    'action' => 'cancel',
                    'sub_id' => $sub['id'],
                    'old_status' => $sub['status'],
                ];
            }
        }
    } elseif (!$row['excluded']) {
        if ($seri_no_clean === '') {
            $row['error'] = 'Seri No (IMEI) boş';
        } else {
            // 1) Once mevcut abonelik kaydi var mi bak (item_detail = IMEI)
            $stmt = $conn->prepare("SELECT * FROM subscriptions WHERE REPLACE(REPLACE(REPLACE(REPLACE(TRIM(item_detail),' ',''),CHAR(13),''),CHAR(10),''),CHAR(9),'') = ? AND item_type = 'product'");
            $stmt->bind_param("s", $seri_no_clean);
            $stmt->execute();
            $sub = $stmt->get_result()->fetch_assoc();

            $action = null;
            $sub_id = null;
            $customer_id = null;
            $product_id = null;
            $sale_id = null;
            $item_name = null;
            $initial_sale_date = null;

            if ($sub) {
                $action = 'update';
                $sub_id = $sub['id'];
                $customer_id = $sub['customer_id'];
                $product_id = $sub['product_id'];
                $sale_id = $sub['sale_id'];
                $item_name = $sub['item_name'];
                $initial_sale_date = $sub['initial_sale_date'];
            } else {
                // 2) Abonelik kaydi yok - IMEI'yi products'ta bulup hangi satisa/musteriye ait oldugunu bul
                $pstmt = $conn->prepare("SELECT id, model FROM products WHERE REPLACE(REPLACE(REPLACE(REPLACE(TRIM(imei_number),' ',''),CHAR(13),''),CHAR(10),''),CHAR(9),'') = ?");
                $pstmt->bind_param("s", $seri_no_clean);
                $pstmt->execute();
                $prod = $pstmt->get_result()->fetch_assoc();

                if (!$prod) {
                    $row['error'] = 'Bu IMEI ne aboneliklerde ne ürünlerde bulunamadı';
                } else {
                    $spstmt = $conn->prepare("SELECT sp.sale_id, s.customer_id, s.sale_date FROM sale_products sp INNER JOIN sales s ON sp.sale_id = s.id WHERE sp.product_id = ? ORDER BY s.sale_date DESC LIMIT 1");
                    $spstmt->bind_param("i", $prod['id']);
                    $spstmt->execute();
                    $sp = $spstmt->get_result()->fetch_assoc();

                    if (!$sp) {
                        $row['error'] = 'Bu IMEI ürünlerde var ama hiç satılmamış (satış kaydı yok)';
                    } else {
                        $action = 'insert';
                        $product_id = $prod['id'];
                        $item_name = $prod['model'];
                        $customer_id = $sp['customer_id'];
                        $sale_id = $sp['sale_id'];
                        $initial_sale_date = $sp['sale_date'];
                    }
                }
            }

            if ($action) {
                $years = (mb_strpos($sure_raw, '2') !== false) ? 2 : 1;

                $base_price = null;
                if ($indirimli_fiyat !== '' && is_numeric(str_replace(',', '.', $indirimli_fiyat))) {
                    $base_price = (float)str_replace(',', '.', $indirimli_fiyat);
                } elseif ($liste_fiyat !== '' && is_numeric(str_replace(',', '.', $liste_fiyat))) {
                    $base_price = (float)str_replace(',', '.', $liste_fiyat);
                }

                $tarih = parse_tr_date($tarih_raw);

                if ($base_price === null) {
                    $row['error'] = 'Ne İndirimli Fiyat ne Liste Fiyatı sayısal/dolu';
                } elseif ($tarih === null) {
                    $row['error'] = "Tarih anlaşılamadı: '$tarih_raw'";
                } elseif (!$customer_id) {
                    $row['error'] = 'Müşteri bulunamadı (satış kaydında customer_id yok)';
                } else {
                    $dt = new DateTime($tarih);
                    $dt->modify("+{$years} years");
                    $new_renewal_date = $dt->format('Y-m-d');

                    $our_revenue = round($base_price * 0.20, 2);
                    $vat = round($base_price * 0.20, 2);
                    $total_amount = round($base_price + $vat, 2);

                    $row['match'] = [
                        'action' => $action,
                        'sub_id' => $sub_id,
                        'customer_id' => $customer_id,
                        'product_id' => $product_id,
                        'sale_id' => $sale_id,
                        'item_name' => $item_name,
                        'imei' => $seri_no_clean,
                        'initial_sale_date' => $initial_sale_date,
                        'old_renewal_date' => $sub['renewal_date'] ?? null,
                        'years' => $years,
                        'base_date' => $tarih,
                        'new_renewal_date' => $new_renewal_date,
                        'base_price' => $base_price,
                        'renewal_amount' => $base_price,
                        'vat' => $vat,
                        'total_amount' => $total_amount,
                        'our_revenue' => $our_revenue,
                    ];
                }
            }
        }
    }

    $rows[] = $row;
}

$to_apply = [];
foreach ($rows as $r) {
    if (!$r['excluded'] && $r['error'] === '' && $r['match']) {
        $m = $r['match'];
        if ($m['action'] === 'cancel') {
            $to_apply[] = [
                'action' => 'cancel',
                'sub_id' => $m['sub_id'],
            ];
        } else {
            $to_apply[] = [
                'action' => $m['action'],
                'sub_id' => $m['sub_id'],
                'customer_id' => $m['customer_id'],
                'product_id' => $m['product_id'],
                'sale_id' => $m['sale_id'],
                'item_name' => $m['item_name'],
                'imei' => $m['imei'],
                'initial_sale_date' => $m['initial_sale_date'],
                'renewal_date' => $m['new_renewal_date'],
                'renewal_amount' => $m['renewal_amount'],
                'vat' => $m['vat'],
                'total_amount' => $m['total_amount'],
                'subscription_revenue' => $m['our_revenue'],
            ];
        }
    }
}
$_SESSION['subscription_renewal_batch'] = $to_apply;

$count_excluded = count(array_filter($rows, fn($r) => $r['excluded']));
$count_error = count(array_filter($rows, fn($r) => !$r['excluded'] && $r['error'] !== ''));
$count_cancel = count(array_filter($to_apply, fn($x) => $x['action'] === 'cancel'));
$count_ok = count(array_filter($to_apply, fn($x) => $x['action'] !== 'cancel'));
$total_revenue = array_sum(array_column(array_filter($to_apply, fn($x) => $x['action'] !== 'cancel'), 'subscription_revenue'));
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Abonelik Yenileme Önizleme - CRM</title>
</head>
<body>
    <?php $active_page = 'subscription-renewal'; include 'partials/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1><?php echo icon('search'); ?> Abonelik Yenileme Önizleme</h1>
                <p class="welcome"><?php echo count($rows); ?> satır işlendi</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card green">
                <div class="icon"><?php echo icon('check'); ?></div>
                <h3>Yenilenecek</h3>
                <div class="number"><?php echo $count_ok; ?></div>
                <small>Eşleşti, hariç değil</small>
            </div>
            <div class="stat-card orange">
                <div class="icon"><?php echo icon('x'); ?></div>
                <h3>Hariç Tutulan</h3>
                <div class="number"><?php echo $count_excluded; ?></div>
                <small>Elle dokunulmayacak</small>
            </div>
            <div class="stat-card red">
                <div class="icon"><?php echo icon('x'); ?></div>
                <h3>Hatalı/Eşleşmeyen</h3>
                <div class="number"><?php echo $count_error; ?></div>
                <small>İşlenmeyecek</small>
            </div>
            <div class="stat-card purple">
                <div class="icon"><?php echo icon('x'); ?></div>
                <h3>İptal Edilecek</h3>
                <div class="number"><?php echo $count_cancel; ?></div>
                <small>Gelir yazılmayacak</small>
            </div>
            <div class="stat-card blue">
                <div class="icon"><?php echo icon('dollar'); ?></div>
                <h3>Bizim Gelirimiz</h3>
                <div class="number"><?php echo number_format($total_revenue, 0, ',', '.'); ?> ₺</div>
                <small>%20 toplamı</small>
            </div>
        </div>

        <div class="table-wrap" style="overflow-x:auto;max-height:650px;overflow-y:auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Durum</th>
                        <th>Müşteri</th>
                        <th>Seri No</th>
                        <th>Süre</th>
                        <th>Eski Tarih</th>
                        <th>Yeni Tarih</th>
                        <th>Liste / İndirimli</th>
                        <th>Yenileme Tutarı</th>
                        <th>Bizim Gelirimiz (%20)</th>
                        <th>Not</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo $r['no']; ?></td>
                            <td>
                                <?php if ($r['excluded']): ?>
                                    <span class="badge badge-orange">Hariç</span>
                                <?php elseif ($r['error'] !== ''): ?>
                                    <span class="badge badge-red">Hata</span>
                                <?php elseif ($r['match'] && $r['match']['action'] === 'cancel'): ?>
                                    <span class="badge badge-gray">İptal Edilecek</span>
                                <?php elseif ($r['match'] && $r['match']['action'] === 'insert'): ?>
                                    <span class="badge badge-blue">Yeni Kayıt</span>
                                <?php else: ?>
                                    <span class="badge badge-green">Yenilenecek</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($r['musteri']); ?></td>
                            <td><?php echo htmlspecialchars($r['seri_no']); ?></td>
                            <td><?php echo htmlspecialchars($r['sure_raw']); ?></td>
                            <?php if ($r['match'] && $r['match']['action'] === 'cancel'): ?>
                                <td>-</td>
                                <td>-</td>
                                <td><?php echo htmlspecialchars($r['liste_fiyat']); ?> / <?php echo htmlspecialchars($r['indirimli_fiyat'] ?: '-'); ?></td>
                                <td>-</td>
                                <td>-</td>
                            <?php else: ?>
                                <td><?php echo $r['match'] ? htmlspecialchars($r['match']['old_renewal_date'] ?? 'yok (yeni kayıt)') : '-'; ?></td>
                                <td><?php echo $r['match'] ? '<strong>' . htmlspecialchars($r['match']['new_renewal_date']) . '</strong>' : '-'; ?></td>
                                <td><?php echo htmlspecialchars($r['liste_fiyat']); ?> / <?php echo htmlspecialchars($r['indirimli_fiyat'] ?: '-'); ?></td>
                                <td><?php echo $r['match'] ? number_format($r['match']['renewal_amount'], 2, ',', '.') . ' ₺' : '-'; ?></td>
                                <td><?php echo $r['match'] ? '<strong style="color:var(--accent);">' . number_format($r['match']['our_revenue'], 2, ',', '.') . ' ₺</strong>' : '-'; ?></td>
                            <?php endif; ?>
                            <td style="font-size:12px;color:var(--text-muted);">
                                <?php echo htmlspecialchars($r['excluded'] ? $r['exclude_reason'] : $r['error']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="actions" style="margin-top:20px;">
            <?php if ($count_ok > 0 || $count_cancel > 0): ?>
                <form method="POST" action="subscription-renewal-process.php" style="display:inline;">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('<?php echo $count_ok; ?> abonelik yenilenecek, <?php echo $count_cancel; ?> abonelik iptal edilecek. Devam edilsin mi?');">
                        <?php echo icon('check'); ?> Uygula (<?php echo $count_ok; ?> Yenile, <?php echo $count_cancel; ?> İptal)
                    </button>
                </form>
            <?php else: ?>
                <button type="button" class="btn btn-primary" disabled>İşlenecek Kayıt Yok</button>
            <?php endif; ?>
            <a href="subscription-renewal-upload.php" class="btn btn-secondary">Yeni Dosya Yükle</a>
        </div>
    </div>
</body>
</html>
