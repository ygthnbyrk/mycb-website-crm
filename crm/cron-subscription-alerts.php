<?php
require_once 'config.php';
require_once 'partials/telegram-helpers.php';

$incoming_key = $_GET['key'] ?? '';
if (!CRON_SECRET || !hash_equals(CRON_SECRET, $incoming_key)) {
    http_response_code(401);
    exit('unauthorized');
}

// En acilden en az acile: bir abonelik için o an geçerli en küçük (en acil),
// henüz gönderilmemiş eşik bulunup gönderilir - eşikler arası günlerde sessiz kalınır.
$thresholds = [3, 10, 20, 30];
$today = date('Y-m-d');

$stmt = $conn->prepare("SELECT s.id, s.item_type, s.item_name, s.item_detail, s.renewal_date, c.name AS customer_name
                         FROM subscriptions s
                         LEFT JOIN customers c ON s.customer_id = c.id
                         WHERE s.status = 'Aktif'");
$stmt->execute();
$result = $stmt->get_result();

$check_stmt = $conn->prepare("SELECT 1 FROM subscription_renewal_alerts WHERE subscription_id = ? AND threshold_days = ?");
$insert_stmt = $conn->prepare("INSERT IGNORE INTO subscription_renewal_alerts (subscription_id, threshold_days) VALUES (?, ?)");

$to_alert = ['product' => [], 'simcard' => []];

while ($row = $result->fetch_assoc()) {
    $days_left = (int)round((strtotime($row['renewal_date']) - strtotime($today)) / 86400);
    if ($days_left < 0) continue;

    foreach ($thresholds as $t) {
        if ($days_left > $t) continue;

        $check_stmt->bind_param("ii", $row['id'], $t);
        $check_stmt->execute();
        $already_sent = $check_stmt->get_result()->fetch_assoc();
        if ($already_sent) continue;

        $type_key = $row['item_type'] === 'simcard' ? 'simcard' : 'product';
        $to_alert[$type_key][] = [
            'customer_name' => $row['customer_name'] ?: 'Bilinmiyor',
            'item_name' => $row['item_name'],
            'item_detail' => $row['item_detail'],
            'renewal_date' => $row['renewal_date'],
            'days_left' => $days_left,
        ];

        $insert_stmt->bind_param("ii", $row['id'], $t);
        $insert_stmt->execute();
        break;
    }
}

function format_alert_lines($items, $label) {
    $lines = [];
    foreach ($items as $it) {
        $lines[] = "• {$it['customer_name']} — {$it['item_name']} ({$label}{$it['item_detail']}) — {$it['days_left']} gün kaldı (Yenileme: " . date('d.m.Y', strtotime($it['renewal_date'])) . ")";
    }
    return $lines;
}

function send_chunked($chat_id, $title, $lines) {
    $chunk_size = 25;
    $chunks = array_chunk($lines, $chunk_size);
    foreach ($chunks as $i => $chunk) {
        $header = $title . (count($chunks) > 1 ? ' (' . ($i + 1) . '/' . count($chunks) . ')' : '');
        tg_send_message($chat_id, $header . "\n\n" . implode("\n", $chunk));
    }
}

$sent_product = count($to_alert['product']);
$sent_simcard = count($to_alert['simcard']);

if ($sent_product > 0 && TELEGRAM_ALERT_CHAT_ID) {
    send_chunked(TELEGRAM_ALERT_CHAT_ID, "🔔 CİHAZ Abonelik Yenileme Uyarısı", format_alert_lines($to_alert['product'], 'IMEI: '));
}

if ($sent_simcard > 0 && TELEGRAM_ALERT_CHAT_ID) {
    send_chunked(TELEGRAM_ALERT_CHAT_ID, "🔔 SIM KART Abonelik Yenileme Uyarısı", format_alert_lines($to_alert['simcard'], 'Tel: '));
}

echo "OK: cihaz=$sent_product, sim=$sent_simcard uyarı gönderildi.\n";
?>
