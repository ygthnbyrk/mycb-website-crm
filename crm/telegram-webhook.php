<?php
require_once 'config.php';
require_once 'partials/telegram-helpers.php';

// Telegram'dan geldiğini doğrula (secret_token, webhook kaydedilirken ayarlanır)
$incoming_secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if (!TELEGRAM_WEBHOOK_SECRET || !hash_equals(TELEGRAM_WEBHOOK_SECRET, $incoming_secret)) {
    http_response_code(401);
    exit;
}

$update = json_decode(file_get_contents('php://input'), true);
$message = $update['message'] ?? $update['channel_post'] ?? null;

if (!$message) {
    http_response_code(200);
    exit;
}

// Vergi levhası her zaman PDF ek dosyası (document) olarak gelir - cihaz/sim
// fotoğrafları hiçbir zaman document olarak gelmez, sadece kamera fotoğrafı (photo)
// olarak gelir. Vergi levhası JPG olarak gönderilirse (caption'a hiçbir şey yazmaya
// gerek yok) aşağıdaki tek-foto akışı, cihaz/sim bulunamazsa otomatik bu akışa döner.
if (!empty($message['document'])) {
    $tax_file_id = $message['document']['file_id'];
    $tax_mime_type = $message['document']['mime_type'] ?? 'application/pdf';
    $tax_chat_id = $message['chat']['id'];
    $tax_caption = $message['caption'] ?? null;

    $stmt = $conn->prepare("INSERT INTO telegram_pending_customers (telegram_chat_id, telegram_file_id, telegram_media_type, caption_raw) VALUES (?, ?, 'document', ?)");
    $stmt->bind_param("iss", $tax_chat_id, $tax_file_id, $tax_caption);
    $stmt->execute();
    $tax_row_id = $conn->insert_id;
    $stmt->close();

    process_pending_telegram_customer_row($conn, $tax_row_id, $tax_mime_type);

    http_response_code(200);
    exit;
}

// Fotoğraf içermeyen mesajları (metin, sticker vb.) sessizce yoksay
if (empty($message['photo'])) {
    http_response_code(200);
    exit;
}

$chat_id = $message['chat']['id'];
$media_group_id = $message['media_group_id'] ?? null;
$caption = $message['caption'] ?? null;

// Telegram photo dizisi küçükten büyüğe sıralı gelir; en büyüğü al
$largest_photo = end($message['photo']);
$file_id = $largest_photo['file_id'];

$row_id = null;
$ready_to_process = false;

if ($media_group_id) {
    // Albüm (2 fotoğraf birlikte) - önce gelen fotoğrafla eşleşen bekleyen kayıt var mı?
    $stmt = $conn->prepare("SELECT id, photo_1_file_id, photo_2_file_id, caption_raw FROM telegram_pending_matches WHERE telegram_media_group_id = ? AND status = 'collecting' LIMIT 1");
    $stmt->bind_param("s", $media_group_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $row_id = $existing['id'];
        $new_caption = $existing['caption_raw'] ?: $caption;

        if (empty($existing['photo_1_file_id'])) {
            $stmt = $conn->prepare("UPDATE telegram_pending_matches SET photo_1_file_id = ?, caption_raw = ? WHERE id = ?");
            $stmt->bind_param("ssi", $file_id, $new_caption, $row_id);
            $stmt->execute();
            $stmt->close();
        } elseif (empty($existing['photo_2_file_id'])) {
            $stmt = $conn->prepare("UPDATE telegram_pending_matches SET photo_2_file_id = ?, caption_raw = ? WHERE id = ?");
            $stmt->bind_param("ssi", $file_id, $new_caption, $row_id);
            $stmt->execute();
            $stmt->close();
            $ready_to_process = true; // ikinci fotoğraf da geldi, artık işleyebiliriz
        }
        // İkisi de doluysa (3. foto vb.) yoksay
    } else {
        $stmt = $conn->prepare("INSERT INTO telegram_pending_matches (telegram_chat_id, telegram_media_group_id, photo_1_file_id, caption_raw, status) VALUES (?, ?, ?, ?, 'collecting')");
        $stmt->bind_param("isss", $chat_id, $media_group_id, $file_id, $caption);
        $stmt->execute();
        $row_id = $conn->insert_id;
        $stmt->close();
    }
} else {
    // Tek foto olarak gönderilmiş (albüm değil) - elimizdekiyle hemen işle
    $stmt = $conn->prepare("INSERT INTO telegram_pending_matches (telegram_chat_id, photo_1_file_id, caption_raw, status) VALUES (?, ?, ?, 'collecting')");
    $stmt->bind_param("iss", $chat_id, $file_id, $caption);
    $stmt->execute();
    $row_id = $conn->insert_id;
    $stmt->close();
    $ready_to_process = true;
}

if ($ready_to_process && $row_id) {
    process_pending_telegram_row($conn, $row_id);
}

http_response_code(200);
exit;
?>
