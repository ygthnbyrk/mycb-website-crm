<?php
require_once 'config.php';
require_once 'partials/icons.php';
require_once 'partials/parasut-helpers.php';

// Müşteri senkronu admin + bilgi@ herkesin kullanabildiği Müşteriler sayfasından
// tetikleniyor - Paraşüt bağlantısını admin kurar ama günlük senkronu herkes çalıştırabilir.
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$token = parasut_get_valid_token($conn);
if (!$token) {
    $_SESSION['error'] = 'Paraşüt bağlantısı yok. Bir admin önce Kârlılık sayfasından bağlansın.';
    header('Location: customers.php');
    exit();
}

// İlk kurulumda Paraşüt'ün gerçek contact alan adlarını görmek için (admin-only,
// ham veri PII içeriyor): parasut-sync-customers.php?debug=1
if (isset($_GET['debug'])) {
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        header('Location: customers.php');
        exit();
    }
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
    header('Location: customers.php');
    exit();
}

// CRM'de bazı eski kayıtlarda vergi no bilinmediğinde "BILINMIYOR-xxxx" gibi bir
// yer tutucu girilmiş (boş string değil) - bunu da "doldurulabilir" say.
function is_blank_tax_value($v) {
    $v = trim((string)$v);
    if ($v === '') return true;
    if (stripos($v, 'bilinmiyor') === 0) return true;
    return false;
}

// --- CRM'deki mevcut müşterileri normalize edilmiş isimle önbelleğe al ---
$existing = [];
$res = $conn->query("SELECT id, name, tax_number, address FROM customers");
while ($row = $res->fetch_assoc()) {
    $existing[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'norm' => normalize_tr_upper($row['name']),
        'tax_number' => $row['tax_number'],
        'address' => $row['address'],
    ];
}

$updated_count = 0;
$already_ok_count = 0;
$unmatched = [];
$conflicts = [];

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

    $best_customer = null;
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
            $best_customer = $c;
        } elseif ($local_best > $second_pct) {
            $second_pct = $local_best;
        }
    }

    // Güvenli eşleşme: yüksek benzerlik VE en yakın ikinci adaydan belirgin fark
    $confident_match = $best_customer && $best_pct >= 85 && ($best_pct - $second_pct) >= 5;

    if (!$confident_match) {
        $unmatched[] = ['name' => $p_name ?: $p_short_name, 'tax_number' => $p_tax_number];
        continue;
    }

    $new_tax = (is_blank_tax_value($best_customer['tax_number']) && !empty($p_tax_number)) ? $p_tax_number : $best_customer['tax_number'];
    $new_address = (trim((string)$best_customer['address']) === '' && !empty($p_address)) ? $p_address : $best_customer['address'];

    // Vergi no değişecekse, aynı numara başka bir müşteride zaten kayıtlı mı bak
    // (tax_number unique) - çakışırsa o alanı atla, script'in tamamen çökmesini engelle.
    if ($new_tax !== $best_customer['tax_number']) {
        $dupe_check = $conn->prepare("SELECT id FROM customers WHERE tax_number = ? AND id != ?");
        $dupe_check->bind_param("si", $new_tax, $best_customer['id']);
        $dupe_check->execute();
        if ($dupe_check->get_result()->num_rows > 0) {
            $conflicts[] = ['name' => $best_customer['name'], 'tax_number' => $new_tax];
            $new_tax = $best_customer['tax_number'];
        }
        $dupe_check->close();
    }

    if ($new_tax !== $best_customer['tax_number'] || $new_address !== $best_customer['address']) {
        try {
            $stmt = $conn->prepare("UPDATE customers SET tax_number = ?, address = ? WHERE id = ?");
            $stmt->bind_param("ssi", $new_tax, $new_address, $best_customer['id']);
            $stmt->execute();
            $stmt->close();
            $updated_count++;
        } catch (mysqli_sql_exception $e) {
            $conflicts[] = ['name' => $best_customer['name'], 'tax_number' => $new_tax];
        }
    } else {
        $already_ok_count++;
    }
}

$_SESSION['parasut_sync_result'] = [
    'total_contacts' => count($contacts),
    'updated' => $updated_count,
    'already_ok' => $already_ok_count,
    'unmatched' => $unmatched,
    'conflicts' => $conflicts,
];
$_SESSION['success'] = count($contacts) . ' Paraşüt kişisi tarandı: ' . $updated_count . ' müşteri güncellendi, ' . count($unmatched) . ' eşleşmedi'
    . (!empty($conflicts) ? ', ' . count($conflicts) . ' çakışma (vergi no başka bir kayıtta zaten var - atlandı)' : '') . '.';
header('Location: customers.php');
exit();
?>
