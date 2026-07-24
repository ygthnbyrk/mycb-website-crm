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

function parasut_api_get($access_token, $company_id, $path) {
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
    return ['code' => $code, 'body' => json_decode($response, true)];
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
    $success = 'Bağlantı kesildi.';
}

$token_row = parasut_token_row($conn);
$is_connected = $token_row && !empty($token_row['access_token']);

function normalize_tr_upper($s) {
    return mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string)$s)), 'UTF-8');
}

function parasut_fetch_all_invoices($access_token, $company_id) {
    $all = [];
    $included_by_id = [];
    $page = 1;
    $per_page = 50;
    $max_pages = 30;
    do {
        $path = '/sales_invoices?page[size]=' . $per_page . '&page[number]=' . $page . '&sort=-issue_date&include=details,details.product';
        $resp = parasut_api_get($access_token, $company_id, $path);
        if ($resp['code'] !== 200 || !isset($resp['body']['data'])) {
            return ['ok' => false, 'error' => 'HTTP ' . $resp['code']];
        }
        foreach (($resp['body']['included'] ?? []) as $inc) {
            $included_by_id[$inc['type'] . ':' . $inc['id']] = $inc;
        }
        $batch = $resp['body']['data'];
        $all = array_merge($all, $batch);
        $page++;
    } while (count($batch) === $per_page && $page <= $max_pages);
    return ['ok' => true, 'invoices' => $all, 'included' => $included_by_id];
}

$PARASUT_START_YEAR = 2025;
$parasut_years = range($PARASUT_START_YEAR, (int)date('Y'));
$p_selected_year = isset($_GET['pyear']) ? (int)$_GET['pyear'] : (int)date('Y');
if (!in_array($p_selected_year, $parasut_years)) $p_selected_year = end($parasut_years);

$renewal_cost_by_year = [2026 => 540.0];

$fetch_error = '';
$buckets = [];
$excluded_total = 0.0;
$excluded_count = 0;
$total_revenue = 0.0;
$total_cost = 0.0;
$total_cost_known = 0.0;

if ($is_connected && !isset($_POST['action'])) {
    $auth = parasut_get_valid_token($conn);
    if (!$auth) {
        $fetch_error = 'Erişim jetonu yenilenemedi, bağlantıyı tekrar kur.';
    } else {
        $fetched = parasut_fetch_all_invoices($auth['access_token'], $auth['company_id']);
        if (!$fetched['ok']) {
            $fetch_error = 'Fatura verisi çekilemedi (' . htmlspecialchars($fetched['error']) . '). Şirket ID doğru mu kontrol et.';
        } else {
            $included_by_id = $fetched['included'];

            $model_costs = [];
            $mres = $conn->query("SELECT model, AVG(cost_price) avg_cost FROM products WHERE cost_price > 0 GROUP BY model");
            while ($row = $mres->fetch_assoc()) {
                $model_costs[normalize_tr_upper($row['model'])] = (float)$row['avg_cost'];
            }
            $model_keys = array_keys($model_costs);
            usort($model_keys, function ($a, $b) { return mb_strlen($b) - mb_strlen($a); });

            foreach ($fetched['invoices'] as $inv) {
                $attr = $inv['attributes'] ?? [];
                $issue_date = $attr['issue_date'] ?? '';
                if (substr($issue_date, 0, 4) !== (string)$p_selected_year) continue;

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
                        $has_cost = isset($renewal_cost_by_year[$p_selected_year]);
                        if (!isset($buckets[$bkey])) $buckets[$bkey] = ['group' => 'renewal', 'label' => 'Sim Kart Yenilemeleri', 'qty' => 0, 'revenue' => 0, 'cost' => 0, 'has_cost' => $has_cost];
                        $buckets[$bkey]['qty'] += $qty;
                        $buckets[$bkey]['revenue'] += $revenue;
                        if ($has_cost) $buckets[$bkey]['cost'] += $qty * $renewal_cost_by_year[$p_selected_year];
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

            foreach ($buckets as $b) {
                $total_revenue += $b['revenue'];
                if ($b['has_cost']) {
                    $total_cost += $b['cost'];
                    $total_cost_known += $b['revenue'];
                }
            }
            uasort($buckets, function ($a, $b) { return $b['revenue'] <=> $a['revenue']; });
        }
    }
}
$total_profit = $total_cost_known - $total_cost;

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
                <div class="detail-item"><span>Jeton geçerlilik</span><span><?php echo htmlspecialchars($token_row['expires_at']); ?></span></div>
                <form method="POST" style="margin-top:14px;">
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

            <?php if ($fetch_error): ?>
                <div class="alert alert-error"><?php echo $fetch_error; ?></div>
            <?php else: ?>
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="icon"><?php echo icon('dollar'); ?></div>
                        <h3>Paraşüt Cirosu</h3>
                        <div class="number"><?php echo number_format($total_revenue, 0, ',', '.'); ?> ₺</div>
                        <small><?php echo $p_selected_year; ?> toplamı</small>
                    </div>
                    <div class="stat-card orange">
                        <div class="icon"><?php echo icon('package'); ?></div>
                        <h3>Tahmini Maliyet</h3>
                        <div class="number"><?php echo number_format($total_cost, 0, ',', '.'); ?> ₺</div>
                        <small>Sadece maliyeti bilinen kalemler</small>
                    </div>
                    <div class="stat-card green">
                        <div class="icon"><?php echo icon('chart'); ?></div>
                        <h3>Tahmini Kâr</h3>
                        <div class="number"><?php echo number_format($total_profit, 0, ',', '.'); ?> ₺</div>
                        <small><?php echo $total_cost_known > 0 ? '%' . round(($total_profit / $total_cost_known) * 100) . ' marj' : '—'; ?></small>
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
                    $rows = array_filter($buckets, function ($b) use ($gkey) { return $b['group'] === $gkey; });
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

                <?php if ($excluded_count > 0): ?>
                    <p style="font-size:12.5px;color:var(--text-muted);">
                        Not: "Fiyat Farkı" türü <?php echo $excluded_count; ?> kalem (<?php echo number_format($excluded_total, 0, ',', '.'); ?> ₺) hesaba katılmadı.
                    </p>
                <?php endif; ?>

                <?php if (empty($buckets) && $excluded_count === 0): ?>
                    <div class="no-data"><?php echo $p_selected_year; ?> yılı için fatura bulunamadı</div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
