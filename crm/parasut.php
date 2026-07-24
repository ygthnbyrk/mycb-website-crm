<?php
require_once 'config.php';
require_once 'partials/icons.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: hub.php');
    exit();
}

$user_name = $_SESSION['user_name'];
$error = '';
$success = '';

$conn->query("CREATE TABLE IF NOT EXISTS parasut_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    access_token TEXT,
    refresh_token TEXT,
    company_id VARCHAR(50),
    expires_at DATETIME,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS parasut_cache (
    year INT PRIMARY KEY,
    buckets_json LONGTEXT,
    total_revenue DECIMAL(12,2) DEFAULT 0,
    total_cost DECIMAL(12,2) DEFAULT 0,
    total_profit DECIMAL(12,2) DEFAULT 0,
    excluded_total DECIMAL(12,2) DEFAULT 0,
    excluded_count INT DEFAULT 0,
    synced_at DATETIME
)");

function parasut_token_row($conn) {
    $res = $conn->query("SELECT * FROM parasut_tokens ORDER BY id DESC LIMIT 1");
    return $res->num_rows ? $res->fetch_assoc() : null;
}

function parasut_save_token($conn, $access_token, $refresh_token, $expires_in, $company_id) {
    $expires_at = date('Y-m-d H:i:s', time() + (int)$expires_in);
    $conn->query("DELETE FROM parasut_tokens");
    $stmt = $conn->prepare("INSERT INTO parasut_tokens (access_token, refresh_token, company_id, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $access_token, $refresh_token, $company_id, $expires_at);
    $stmt->execute();
}

function parasut_token_request($params) {
    $ch = curl_init('https://api.parasut.com/oauth/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);
    if ($curl_err) return ['ok' => false, 'error' => $curl_err];
    $data = json_decode($response, true);
    if ($code !== 200 || !isset($data['access_token'])) {
        return ['ok' => false, 'error' => $data['error_description'] ?? $data['error'] ?? ('HTTP ' . $code)];
    }
    return ['ok' => true, 'data' => $data];
}

function parasut_get_valid_token($conn) {
    $row = parasut_token_row($conn);
    if (!$row || empty($row['access_token'])) return null;
    if (strtotime($row['expires_at']) > time() + 60) {
        return ['access_token' => $row['access_token'], 'company_id' => $row['company_id']];
    }
    $result = parasut_token_request([
        'grant_type' => 'refresh_token',
        'client_id' => PARASUT_CLIENT_ID,
        'client_secret' => PARASUT_CLIENT_SECRET,
        'refresh_token' => $row['refresh_token'],
    ]);
    if (!$result['ok']) return null;
    parasut_save_token($conn, $result['data']['access_token'], $result['data']['refresh_token'], $result['data']['expires_in'], $row['company_id']);
    return ['access_token' => $result['data']['access_token'], 'company_id' => $row['company_id']];
}

// Hiz siniri (429) durumunda birkac kez bekleyip tekrar dener
function parasut_api_get($access_token, $company_id, $path, $retries = 4) {
    for ($attempt = 0; $attempt <= $retries; $attempt++) {
        $ch = curl_init('https://api.parasut.com/v4/' . $company_id . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $body = json_decode($response, true);
        if ($code === 429 && $attempt < $retries) {
            usleep(1500000); // 1.5 sn bekle, tekrar dene
            continue;
        }
        return ['code' => $code, 'body' => $body];
    }
    return ['code' => $code ?? 0, 'body' => $body ?? null];
}

function normalize_tr_upper($s) {
    return mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string)$s)), 'UTF-8');
}

function parasut_fetch_all_invoices($access_token, $company_id) {
    $all = [];
    $included_by_id = [];
    $page = 1;
    $per_page = 25;
    $max_pages = 30;
    do {
        $path = '/sales_invoices?page[size]=' . $per_page . '&page[number]=' . $page . '&sort=-issue_date&include=details,details.product';
        $resp = parasut_api_get($access_token, $company_id, $path);
        if ($resp['code'] !== 200 || !isset($resp['body']['data'])) {
            $detail = '';
            if (isset($resp['body']['errors'][0])) {
                $e0 = $resp['body']['errors'][0];
                $detail = ($e0['title'] ?? '') . ': ' . ($e0['detail'] ?? '');
            }
            return ['ok' => false, 'error' => 'HTTP ' . $resp['code'] . ($detail ? ' — ' . $detail : '')];
        }
        foreach (($resp['body']['included'] ?? []) as $inc) {
            $included_by_id[$inc['type'] . ':' . $inc['id']] = $inc;
        }
        $batch = $resp['body']['data'];
        $all = array_merge($all, $batch);
        $page++;
        if (count($batch) === $per_page && $page <= $max_pages) usleep(400000); // sonraki sayfadan once kisa bekleme
    } while (count($batch) === $per_page && $page <= $max_pages);
    return ['ok' => true, 'invoices' => $all, 'included' => $included_by_id];
}

function parasut_aggregate_year($conn, $invoices, $included_by_id, $year) {
    $renewal_cost_by_year = [2026 => 540.0];

    $model_costs = [];
    $mres = $conn->query("SELECT model, AVG(cost_price) avg_cost FROM products WHERE cost_price > 0 GROUP BY model");
    while ($row = $mres->fetch_assoc()) {
        $model_costs[normalize_tr_upper($row['model'])] = (float)$row['avg_cost'];
    }
    $model_keys = array_keys($model_costs);
    usort($model_keys, function ($a, $b) { return mb_strlen($b) - mb_strlen($a); });

    $buckets = [];
    $excluded_total = 0.0;
    $excluded_count = 0;

    foreach ($invoices as $inv) {
        $attr = $inv['attributes'] ?? [];
        $issue_date = $attr['issue_date'] ?? '';
        if (substr($issue_date, 0, 4) !== (string)$year) continue;

        foreach (($inv['relationships']['details']['data'] ?? []) as $ref) {
            $key = $ref['type'] . ':' . $ref['id'];
            if (!isset($included_by_id[$key])) continue;
            $detail = $included_by_id[$key]['attributes'] ?? [];
            $prod_ref = $included_by_id[$key]['relationships']['product']['data'] ?? null;
            $product_name = null;
            if ($prod_ref) {
                $pkey = $prod_ref['type'] . ':' . $prod_ref['id'];
                $product_name = $included_by_id[$pkey]['attributes']['name'] ?? null;
            }
            $name = $product_name ?: ($detail['description'] ?? '(isimsiz)');
            $qty = (float)($detail['quantity'] ?? 0);
            $revenue = (float)($detail['net_total'] ?? 0);
            $upper = normalize_tr_upper($name);

            if (mb_strpos($upper, 'FIYAT FARK') !== false || mb_strpos($upper, 'FİYAT FARK') !== false) {
                $excluded_total += $revenue;
                $excluded_count++;
                continue;
            }

            if (mb_strpos($upper, 'YENILEME') !== false || mb_strpos($upper, 'YENİLEME') !== false) {
                $bkey = 'renewal';
                $has_cost = isset($renewal_cost_by_year[$year]);
                if (!isset($buckets[$bkey])) $buckets[$bkey] = ['group' => 'renewal', 'label' => 'Sim Kart Yenilemeleri', 'qty' => 0, 'revenue' => 0, 'cost' => 0, 'has_cost' => $has_cost];
                $buckets[$bkey]['qty'] += $qty;
                $buckets[$bkey]['revenue'] += $revenue;
                if ($has_cost) $buckets[$bkey]['cost'] += $qty * $renewal_cost_by_year[$year];
                continue;
            }

            if (mb_strpos($upper, 'SIM KART') !== false || mb_strpos($upper, 'SİM KART') !== false) {
                $bkey = 'simcard:' . $upper;
                if (!isset($buckets[$bkey])) $buckets[$bkey] = ['group' => 'simcard', 'label' => $name, 'qty' => 0, 'revenue' => 0, 'cost' => 0, 'has_cost' => false];
                $buckets[$bkey]['qty'] += $qty;
                $buckets[$bkey]['revenue'] += $revenue;
                continue;
            }

            $matched_model = null;
            foreach ($model_keys as $mk) {
                if (mb_strpos($upper, $mk) !== false) { $matched_model = $mk; break; }
            }
            if ($matched_model) {
                $bkey = 'device:' . $matched_model;
                if (!isset($buckets[$bkey])) $buckets[$bkey] = ['group' => 'device', 'label' => $name, 'qty' => 0, 'revenue' => 0, 'cost' => 0, 'has_cost' => true];
                $buckets[$bkey]['qty'] += $qty;
                $buckets[$bkey]['revenue'] += $revenue;
                $buckets[$bkey]['cost'] += $qty * $model_costs[$matched_model];
                continue;
            }

            $bkey = 'other:' . $upper;
            if (!isset($buckets[$bkey])) $buckets[$bkey] = ['group' => 'other', 'label' => $name, 'qty' => 0, 'revenue' => 0, 'cost' => 0, 'has_cost' => false];
            $buckets[$bkey]['qty'] += $qty;
            $buckets[$bkey]['revenue'] += $revenue;
        }
    }

    $total_revenue = 0.0;
    $total_cost = 0.0;
    $total_cost_known = 0.0;
    foreach ($buckets as $b) {
        $total_revenue += $b['revenue'];
        if ($b['has_cost']) {
            $total_cost += $b['cost'];
            $total_cost_known += $b['revenue'];
        }
    }
    uasort($buckets, function ($a, $b) { return $b['revenue'] <=> $a['revenue']; });

    return [
        'buckets' => $buckets,
        'total_revenue' => $total_revenue,
        'total_cost' => $total_cost,
        'total_profit' => $total_cost_known - $total_cost,
        'excluded_total' => $excluded_total,
        'excluded_count' => $excluded_count,
    ];
}

// Baglanma islemi (kod + sirket id gonderildi)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'connect') {
    $auth_code = trim($_POST['auth_code'] ?? '');
    $company_id = trim($_POST['company_id'] ?? '');
    if (empty($auth_code) || empty($company_id)) {
        $error = 'Yetkilendirme kodu ve şirket ID zorunlu.';
    } else {
        $result = parasut_token_request([
            'grant_type' => 'authorization_code',
            'client_id' => PARASUT_CLIENT_ID,
            'client_secret' => PARASUT_CLIENT_SECRET,
            'redirect_uri' => PARASUT_REDIRECT_URI,
            'code' => $auth_code,
        ]);
        if ($result['ok']) {
            parasut_save_token($conn, $result['data']['access_token'], $result['data']['refresh_token'], $result['data']['expires_in'], $company_id);
            $success = 'Paraşüt bağlantısı kuruldu.';
        } else {
            $error = 'Bağlantı kurulamadı: ' . htmlspecialchars($result['error']);
        }
    }
}

// Baglantiyi kes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'disconnect') {
    $conn->query("DELETE FROM parasut_tokens");
    $conn->query("DELETE FROM parasut_cache");
    $success = 'Bağlantı kesildi.';
}

$PARASUT_START_YEAR = 2025;
$parasut_years = range($PARASUT_START_YEAR, (int)date('Y'));
$p_selected_year = isset($_GET['pyear']) ? (int)$_GET['pyear'] : (int)date('Y');
if (!in_array($p_selected_year, $parasut_years)) $p_selected_year = end($parasut_years);

// Senkronizasyon islemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync') {
    $sync_year = (int)($_POST['sync_year'] ?? $p_selected_year);
    $auth = parasut_get_valid_token($conn);
    if (!$auth) {
        $error = 'Erişim jetonu yenilenemedi, bağlantıyı tekrar kur.';
    } else {
        $fetched = parasut_fetch_all_invoices($auth['access_token'], $auth['company_id']);
        if (!$fetched['ok']) {
            $error = 'Senkronizasyon başarısız: ' . htmlspecialchars($fetched['error']);
        } else {
            $agg = parasut_aggregate_year($conn, $fetched['invoices'], $fetched['included'], $sync_year);
            $stmt = $conn->prepare("REPLACE INTO parasut_cache (year, buckets_json, total_revenue, total_cost, total_profit, excluded_total, excluded_count, synced_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $buckets_json = json_encode($agg['buckets'], JSON_UNESCAPED_UNICODE);
            $stmt->bind_param("isddddi", $sync_year, $buckets_json, $agg['total_revenue'], $agg['total_cost'], $agg['total_profit'], $agg['excluded_total'], $agg['excluded_count']);
            $stmt->execute();
            $success = $sync_year . ' yılı senkronize edildi.';
            $p_selected_year = $sync_year;
        }
    }
}

$token_row = parasut_token_row($conn);
$is_connected = $token_row && !empty($token_row['access_token']);

$cache_row = null;
if ($is_connected) {
    $cstmt = $conn->prepare("SELECT * FROM parasut_cache WHERE year = ?");
    $cstmt->bind_param("i", $p_selected_year);
    $cstmt->execute();
    $cres = $cstmt->get_result();
    $cache_row = $cres->num_rows ? $cres->fetch_assoc() : null;
}
$cached_buckets = $cache_row ? json_decode($cache_row['buckets_json'], true) : [];

$authorize_url = 'https://api.parasut.com/oauth/authorize?client_id=' . urlencode(PARASUT_CLIENT_ID) . '&redirect_uri=' . urlencode(PARASUT_REDIRECT_URI) . '&response_type=code';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Kârlılık (Paraşüt) - CRM</title>
</head>
<body>
    <?php $active_page = 'parasut'; include 'partials/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1>Kârlılık — Paraşüt Entegrasyonu</h1>
                <p class="welcome">Hoş geldiniz, <?php echo htmlspecialchars($user_name); ?>!</p>
            </div>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <?php if (!$is_connected): ?>
            <div class="detail-card" style="max-width:640px;">
                <h3><?php echo icon('link'); ?> Paraşüt'e Bağlan</h3>
                <p style="font-size:13.5px;color:var(--text-secondary);margin-bottom:16px;">
                    1) Aşağıdaki bağlantıya tıklayıp Paraşüt hesabınla giriş yap ve yetkilendir.<br>
                    2) Paraşüt sana bir <strong>kod</strong> gösterecek, onu kopyala.<br>
                    3) Şirket ID'yi Paraşüt'e giriş yaptığında adres çubuğunda görürsün (<code>app.parasut.com/<strong>ŞİRKET_ID</strong>/...</code>).
                </p>
                <a href="<?php echo htmlspecialchars($authorize_url); ?>" target="_blank" class="btn btn-secondary" style="margin-bottom:20px;">
                    <?php echo icon('arrow-right'); ?> Paraşüt'te Yetkilendir
                </a>
                <form method="POST">
                    <input type="hidden" name="action" value="connect">
                    <div class="form-group">
                        <label>Yetkilendirme Kodu</label>
                        <input type="text" name="auth_code" required placeholder="Paraşüt'ün gösterdiği kod">
                    </div>
                    <div class="form-group">
                        <label>Şirket ID</label>
                        <input type="text" name="company_id" required placeholder="Örn. 123456">
                    </div>
                    <button type="submit" class="btn btn-primary">Bağlantıyı Tamamla</button>
                </form>
            </div>
        <?php else: ?>
            <div class="detail-card" style="margin-bottom:16px;">
                <h3><?php echo icon('check'); ?> Bağlantı Durumu</h3>
                <div class="detail-item"><span>Şirket ID</span><span><?php echo htmlspecialchars($token_row['company_id']); ?></span></div>
                <form method="POST" style="margin-top:14px;display:inline-block;margin-right:10px;">
                    <input type="hidden" name="action" value="disconnect">
                    <button type="submit" class="btn btn-secondary" onclick="return confirm('Paraşüt bağlantısını kesmek istediğine emin misin?');">
                        <?php echo icon('x'); ?> Bağlantıyı Kes
                    </button>
                </form>
            </div>

            <div class="year-tabs">
                <?php foreach ($parasut_years as $y): ?>
                    <a href="parasut.php?pyear=<?php echo $y; ?>" class="year-tab<?php echo $y === $p_selected_year ? ' active' : ''; ?>"><?php echo $y; ?></a>
                <?php endforeach; ?>
            </div>

            <div class="detail-card" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <span style="font-size:13px;color:var(--text-muted);">
                    <?php echo $cache_row ? 'Son senkronizasyon: ' . htmlspecialchars($cache_row['synced_at']) : 'Bu yıl için henüz senkronize edilmedi.'; ?>
                </span>
                <form method="POST">
                    <input type="hidden" name="action" value="sync">
                    <input type="hidden" name="sync_year" value="<?php echo $p_selected_year; ?>">
                    <button type="submit" class="btn btn-primary"><?php echo icon('refresh'); ?> <?php echo $p_selected_year; ?> Yılını Senkronize Et</button>
                </form>
            </div>

            <?php if (!$cache_row): ?>
                <div class="no-data">Veri yok — yukarıdaki butonla senkronize et.</div>
            <?php else: ?>
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="icon"><?php echo icon('dollar'); ?></div>
                        <h3>Paraşüt Cirosu</h3>
                        <div class="number"><?php echo number_format((float)$cache_row['total_revenue'], 0, ',', '.'); ?> ₺</div>
                        <small><?php echo $p_selected_year; ?> toplamı</small>
                    </div>
                    <div class="stat-card orange">
                        <div class="icon"><?php echo icon('package'); ?></div>
                        <h3>Tahmini Maliyet</h3>
                        <div class="number"><?php echo number_format((float)$cache_row['total_cost'], 0, ',', '.'); ?> ₺</div>
                        <small>Sadece maliyeti bilinen kalemler</small>
                    </div>
                    <div class="stat-card green">
                        <div class="icon"><?php echo icon('chart'); ?></div>
                        <h3>Tahmini Kâr</h3>
                        <div class="number"><?php echo number_format((float)$cache_row['total_profit'], 0, ',', '.'); ?> ₺</div>
                        <small>&nbsp;</small>
                    </div>
                </div>

                <?php
                $groups = [
                    'device' => ['title' => 'Cihaz Satışları (kâr hesaplı)', 'icon' => 'package'],
                    'renewal' => ['title' => 'Sim Kart Yenilemeleri', 'icon' => 'refresh'],
                    'simcard' => ['title' => 'Yeni Sim Kart Satışları (maliyet girilmedi)', 'icon' => 'sim'],
                    'other' => ['title' => 'Eşleşmeyen/Diğer Kalemler', 'icon' => 'list'],
                ];
                ?>
                <?php foreach ($groups as $gkey => $ginfo):
                    $rows = array_filter($cached_buckets, function ($b) use ($gkey) { return $b['group'] === $gkey; });
                    if (empty($rows)) continue;
                ?>
                    <div class="detail-card" style="margin-bottom:16px;">
                        <h3><?php echo icon($ginfo['icon']); ?> <?php echo $ginfo['title']; ?></h3>
                        <?php foreach ($rows as $b): ?>
                            <div class="detail-item">
                                <span><?php echo htmlspecialchars($b['label']); ?> · <?php echo (int)$b['qty']; ?> adet</span>
                                <span>
                                    <?php echo number_format($b['revenue'], 0, ',', '.'); ?> ₺
                                    <?php if ($b['has_cost']): ?>
                                        <span style="color:var(--text-muted);font-weight:500;"> (kâr: <?php echo number_format($b['revenue'] - $b['cost'], 0, ',', '.'); ?> ₺)</span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);font-weight:500;"> (maliyet yok)</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <?php if ($cache_row['excluded_count'] > 0): ?>
                    <p style="font-size:12.5px;color:var(--text-muted);">
                        Not: "Fiyat Farkı" türü <?php echo $cache_row['excluded_count']; ?> kalem (<?php echo number_format((float)$cache_row['excluded_total'], 0, ',', '.'); ?> ₺) hesaba katılmadı.
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
