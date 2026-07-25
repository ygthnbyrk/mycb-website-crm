<?php
require_once 'config.php';
require_once 'partials/icons.php';
require_once 'partials/parasut-helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: hub.php');
    exit();
}

$token = parasut_get_valid_token($conn);
if (!$token) {
    $_SESSION['error'] = 'Paraşüt bağlantısı yok. Önce Kârlılık sayfasından bağlanın.';
    header('Location: parasut.php');
    exit();
}

// İlk kurulumda Paraşüt'ün gerçek contact alan adlarını görmek için:
// parasut-sync-customers.php?debug=1
if (isset($_GET['debug'])) {
    $resp = parasut_api_get($token['access_token'], $token['company_id'], '/contacts?page[size]=3&page[number]=1');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resp['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

// --- Tüm Paraşüt kişilerini sayfalayarak çek ---
$contacts = [];
$page = 1;
$per_page = 25;
$max_pages = 80; // ~2000 kişi, güvenli üst sınır
$fetch_error = null;

do {
    $path = '/contacts?page[size]=' . $per_page . '&page[number]=' . $page;
    $resp = parasut_api_get($token['access_token'], $token['company_id'], $path);
    if ($resp['code'] !== 200 || !isset($resp['body']['data'])) {
        $fetch_error = 'HTTP ' . $resp['code'] . ' - ' . json_encode($resp['body']['errors'] ?? $resp['body'] ?? '');
        break;
    }
    foreach ($resp['body']['data'] as $item) {
        $contacts[] = $item['attributes'] ?? [];
    }
    $has_more = count($resp['body']['data']) === $per_page;
    $page++;
    if ($has_more) usleep(400000); // rate limit'e nazik davran
} while ($has_more && $page <= $max_pages);

if ($fetch_error) {
    $_SESSION['error'] = 'Paraşüt kişileri çekilemedi: ' . $fetch_error;
    header('Location: parasut.php');
    exit();
}

// --- CRM'deki mevcut müşterileri normalize edilmiş isimle önbelleğe al ---
$existing = [];
$res = $conn->query("SELECT id, name FROM customers");
while ($row = $res->fetch_assoc()) {
    $existing[] = ['id' => $row['id'], 'name' => $row['name'], 'norm' => normalize_tr_upper($row['name'])];
}

$updated_count = 0;
$already_ok_count = 0;
$unmatched = [];

foreach ($contacts as $attrs) {
    // Sadece gerçek müşteriler - tedarikçileri (account_type=supplier) ve arşivlenmiş
    // kayıtları CRM müşteri listesine karıştırma.
    if (($attrs['account_type'] ?? '') !== 'customer') continue;
    if (!empty($attrs['archived'])) continue;

    $p_name = trim((string)($attrs['name'] ?? ''));
    $p_short_name = trim((string)($attrs['short_name'] ?? ''));
    if (empty($p_name) && empty($p_short_name)) continue;

    $p_tax_number = trim((string)($attrs['tax_number'] ?? ''));
    $p_address_parts = array_filter([
        trim((string)($attrs['address'] ?? '')),
        trim((string)($attrs['district'] ?? '')),
        trim((string)($attrs['city'] ?? '')),
    ]);
    $p_address = implode(' / ', $p_address_parts);

    // Paraşüt'te hem tam unvan (name) hem kısa ad (short_name) var; CRM'de hangisine
    // benziyorsa onunla eşleştir - ikisinden en yüksek benzerlik skorunu al.
    $norm_candidates = array_filter([normalize_tr_upper($p_name), normalize_tr_upper($p_short_name)]);

    $best_id = null;
    $best_pct = 0.0;
    $second_pct = 0.0;
    foreach ($existing as $c) {
        $local_best = 0.0;
        foreach ($norm_candidates as $norm_p_name) {
            similar_text($norm_p_name, $c['norm'], $pct);
            if ($pct > $local_best) $local_best = $pct;
        }
        if ($local_best > $best_pct) {
            $second_pct = $best_pct;
            $best_pct = $local_best;
            $best_id = $c['id'];
        } elseif ($local_best > $second_pct) {
            $second_pct = $local_best;
        }
    }

    // Güvenli eşleşme: yüksek benzerlik VE en yakın ikinci adaydan belirgin fark
    $confident_match = $best_id && $best_pct >= 85 && ($best_pct - $second_pct) >= 5;

    if (!$confident_match) {
        $unmatched[] = ['name' => $p_name ?: $p_short_name, 'tax_number' => $p_tax_number];
        continue;
    }

    $stmt = $conn->prepare("UPDATE customers SET
        tax_number = COALESCE(NULLIF(tax_number, ''), NULLIF(?, '')),
        address = COALESCE(NULLIF(address, ''), NULLIF(?, ''))
        WHERE id = ?");
    $stmt->bind_param("ssi", $p_tax_number, $p_address, $best_id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $updated_count++;
    } else {
        $already_ok_count++;
    }
    $stmt->close();
}

$_SESSION['parasut_sync_result'] = [
    'total_contacts' => count($contacts),
    'updated' => $updated_count,
    'already_ok' => $already_ok_count,
    'unmatched' => $unmatched,
];
$_SESSION['success'] = count($contacts) . ' Paraşüt kişisi tarandı: ' . $updated_count . ' müşteri güncellendi, ' . count($unmatched) . ' eşleşmedi (aşağıda listelendi).';
header('Location: parasut.php');
exit();
?>
