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

$sample_invoices = [];
$sample_error = '';
if ($is_connected && !isset($_POST['action'])) {
    $auth = parasut_get_valid_token($conn);
    if ($auth) {
        $resp = parasut_api_get($auth['access_token'], $auth['company_id'], '/sales_invoices?page[size]=10&sort=-issue_date&include=details,details.product');
        if ($resp['code'] === 200 && isset($resp['body']['data'])) {
            $sample_invoices = $resp['body']['data'];
            $included_by_id = [];
            foreach (($resp['body']['included'] ?? []) as $inc) {
                $included_by_id[$inc['type'] . ':' . $inc['id']] = $inc;
            }
            $debug_raw_detail = null;
            foreach ($sample_invoices as &$inv) {
                $inv['_details'] = [];
                foreach (($inv['relationships']['details']['data'] ?? []) as $ref) {
                    $key = $ref['type'] . ':' . $ref['id'];
                    if (isset($included_by_id[$key])) {
                        $detail = $included_by_id[$key];
                        $product_name = null;
                        $prod_ref = $detail['relationships']['product']['data'] ?? null;
                        if ($prod_ref) {
                            $pkey = $prod_ref['type'] . ':' . $prod_ref['id'];
                            $product_name = $included_by_id[$pkey]['attributes']['name'] ?? null;
                        }
                        $detail['attributes']['_product_name'] = $product_name;
                        $inv['_details'][] = $detail['attributes'] ?? [];
                        if ($debug_raw_detail === null) $debug_raw_detail = $detail;
                    }
                }
            }
            unset($inv);
        } else {
            $sample_error = 'Fatura verisi çekilemedi (HTTP ' . $resp['code'] . '). Şirket ID doğru mu kontrol et.';
        }
    } else {
        $sample_error = 'Erişim jetonu yenilenemedi, bağlantıyı tekrar kur.';
    }
}

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

            <div class="detail-card">
                <h3><?php echo icon('list'); ?> Son Faturalar (Ham Veri Önizleme)</h3>
                <?php if ($sample_error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($sample_error); ?></div>
                <?php elseif (empty($sample_invoices)): ?>
                    <div class="no-data">Fatura bulunamadı</div>
                <?php else: ?>
                    <?php foreach ($sample_invoices as $inv):
                        $attr = $inv['attributes'] ?? [];
                    ?>
                        <div class="trend-row">
                            <div class="trend-row-head">
                                <span><?php echo htmlspecialchars($attr['item_type'] ?? '') . ' · ' . htmlspecialchars($attr['issue_date'] ?? ''); ?></span>
                                <span><?php echo number_format((float)($attr['net_total'] ?? 0), 2, ',', '.'); ?> <?php echo htmlspecialchars($attr['currency'] ?? 'TRY'); ?></span>
                            </div>
                            <?php if (!empty($inv['_details'])): ?>
                                <div style="margin-top:6px;padding-left:10px;border-left:2px solid var(--bg-hover);">
                                    <?php foreach ($inv['_details'] as $d): ?>
                                        <div style="font-size:12.5px;color:var(--text-muted);padding:3px 0;">
                                            <?php echo htmlspecialchars($d['_product_name'] ?? $d['description'] ?? '(ürün/açıklama yok)'); ?>
                                            — <?php echo htmlspecialchars($d['quantity'] ?? '?'); ?> adet ×
                                            <?php echo number_format((float)($d['unit_price'] ?? 0), 2, ',', '.'); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($debug_raw_detail)): ?>
            <div class="detail-card" style="margin-top:16px;">
                <h3>Geçici Debug — Ham Kalem Verisi</h3>
                <pre style="font-size:11.5px;white-space:pre-wrap;color:var(--text-muted);"><?php echo htmlspecialchars(json_encode($debug_raw_detail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
