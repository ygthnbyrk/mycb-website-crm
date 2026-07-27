<?php
require_once 'config.php';
require_once 'partials/telegram-helpers.php';

$incoming_key = $_GET['key'] ?? '';
if (!CRON_SECRET || !hash_equals(CRON_SECRET, $incoming_key)) {
    http_response_code(401);
    exit('unauthorized');
}

// Haftalık özet: hâlâ 'Aktif' görünen ama yenileme tarihi geçmiş tüm abonelikler.
// 30/20/10/3 günlük uyarılardan farklı olarak burada "gönderildi" takibi yok -
// her hafta o anki gecikmiş listesinin tamamı tekrar gönderilir.
$today = date('Y-m-d');

$stmt = $conn->prepare("SELECT s.item_type, s.item_name, s.item_detail, s.renewal_date, c.name AS customer_name
                         FROM subscriptions s
                         LEFT JOIN customers c ON s.customer_id = c.id
                         WHERE s.status = 'Aktif' AND s.renewal_date < ?
                         ORDER BY s.renewal_date ASC");
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

$overdue = ['product' => [], 'simcard' => []];

while ($row = $result->fetch_assoc()) {
    $days_overdue = (int)round((strtotime($today) - strtotime($row['renewal_date'])) / 86400);
    $type_key = $row['item_type'] === 'simcard' ? 'simcard' : 'product';
    $overdue[$type_key][] = [
        'customer_name' => $row['customer_name'] ?: 'Bilinmiyor',
        'item_name' => $row['item_name'],
        'item_detail' => $row['item_detail'],
        'renewal_date' => $row['renewal_date'],
        'days_overdue' => $days_overdue,
    ];
}

function format_overdue_lines($items, $label) {
    $lines = [];
    foreach ($items as $it) {
        $lines[] = "• {$it['customer_name']} — {$it['item_name']} ({$label}{$it['item_detail']}) — {$it['days_overdue']} gün gecikmiş (Yenileme: " . date('d.m.Y', strtotime($it['renewal_date'])) . ")";
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

$count_product = count($overdue['product']);
$count_simcard = count($overdue['simcard']);

if (TELEGRAM_ALERT_CHAT_ID) {
    if ($count_product > 0) {
        send_chunked(TELEGRAM_ALERT_CHAT_ID, "⏰ CİHAZ Gecikmiş Abonelikler (Haftalık Özet)", format_overdue_lines($overdue['product'], 'IMEI: '));
    } else {
        tg_send_message(TELEGRAM_ALERT_CHAT_ID, "⏰ CİHAZ Gecikmiş Abonelikler (Haftalık Özet)\n\nGecikmiş cihaz aboneliği yok 👍");
    }

    if ($count_simcard > 0) {
        send_chunked(TELEGRAM_ALERT_CHAT_ID, "⏰ SIM KART Gecikmiş Abonelikler (Haftalık Özet)", format_overdue_lines($overdue['simcard'], 'Tel: '));
    } else {
        tg_send_message(TELEGRAM_ALERT_CHAT_ID, "⏰ SIM KART Gecikmiş Abonelikler (Haftalık Özet)\n\nGecikmiş sim kart aboneliği yok 👍");
    }
}

echo "OK: cihaz=$count_product, sim=$count_simcard gecikmiş kayıt bildirildi.\n";
?>
