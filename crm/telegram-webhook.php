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

function process_pending_telegram_row($conn, $row_id) {
    $stmt = $conn->prepare("SELECT * FROM telegram_pending_matches WHERE id = ?");
    $stmt->bind_param("i", $row_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return;

    $photo_bytes = [];
    if (!empty($row['photo_1_file_id'])) {
        $photo_bytes[] = tg_download_file_bytes($row['photo_1_file_id']);
    }
    if (!empty($row['photo_2_file_id'])) {
        $photo_bytes[] = tg_download_file_bytes($row['photo_2_file_id']);
    }
    $photo_bytes = array_filter($photo_bytes);

    if (empty($photo_bytes)) {
        mark_row_error($conn, $row_id, 'Fotoğraf(lar) Telegram sunucusundan indirilemedi.');
        tg_send_message($row['telegram_chat_id'], '⚠️ Fotoğraf indirilemedi, kayıt CRM\'de manuel gözden geçirme gerektirecek.');
        return;
    }

    $ocr = claude_ocr_device_and_sim($photo_bytes);

    if (!$ocr['ok']) {
        mark_row_error($conn, $row_id, $ocr['error']);
        tg_send_message($row['telegram_chat_id'], "⚠️ Otomatik okuma başarısız oldu, CRM'de manuel gözden geçirme gerekecek.\nHata: " . $ocr['error']);
        return;
    }

    $parsed = $ocr['parsed'];
    $device = $parsed['device'] ?? [];
    $sim = $parsed['simcard'] ?? [];
    $notes = $parsed['notes'] ?? '';

    // Tek foto (albüm değil) gönderilmiş ve ne cihaz ne sim bulunduysa, muhtemelen
    // bu bir vergi levhası fotoğrafı - caption'a bağımlı kalmadan o akışa devret.
    $is_solo_photo = empty($row['photo_2_file_id']);
    if ($is_solo_photo && empty($device['found']) && empty($sim['found'])) {
        redirect_solo_photo_to_tax_certificate($conn, $row_id, $row);
        return;
    }

    $imei = !empty($device['found']) ? trim((string)($device['imei'] ?? '')) : null;
    $serial = !empty($device['found']) ? trim((string)($device['serial_number'] ?? '')) : null;
    $model_guess = !empty($device['found']) ? trim((string)($device['model_guess'] ?? '')) : null;
    $phone = !empty($sim['found']) ? trim((string)($sim['phone_number'] ?? '')) : null;
    $operator_guess = !empty($sim['found']) ? trim((string)($sim['operator_guess'] ?? '')) : null;

    // Caption'dan müşteri adı / plaka ayıkla ("Özka HAFRİYAT / 67 EK 682" formatı)
    $customer_name_raw = null;
    $plate_raw = null;
    if (!empty($row['caption_raw'])) {
        $parts = explode('/', $row['caption_raw'], 2);
        $customer_name_raw = trim($parts[0]);
        $plate_raw = isset($parts[1]) ? trim($parts[1]) : null;
    }

    // IMEI ile stoktaki ürünü eşleştir
    $matched_product_id = null;
    if (!empty($imei)) {
        $stmt = $conn->prepare("SELECT id FROM products WHERE REPLACE(imei_number, ' ', '') = REPLACE(?, ' ', '') AND status = 'Stokta' LIMIT 1");
        $stmt->bind_param("s", $imei);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($r) $matched_product_id = $r['id'];
    }

    // Telefon numarasıyla stoktaki sim kartı eşleştir
    $matched_simcard_id = null;
    if (!empty($phone)) {
        $phone_digits = preg_replace('/\D/', '', $phone);
        $stmt = $conn->prepare("SELECT id FROM simcards WHERE REPLACE(REPLACE(REPLACE(phone_number, ' ', ''), '+', ''), '-', '') LIKE CONCAT('%', ?, '%') AND status = 'Stokta' LIMIT 1");
        $stmt->bind_param("s", $phone_digits);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($r) $matched_simcard_id = $r['id'];
    }

    // Müşteri adıyla eşleştir (bulanık, tek sonuç bulunursa)
    $matched_customer_id = null;
    if (!empty($customer_name_raw)) {
        $like = "%$customer_name_raw%";
        $stmt = $conn->prepare("SELECT id FROM customers WHERE name LIKE ? LIMIT 2");
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (count($results) === 1) $matched_customer_id = $results[0]['id'];
    }

    $stmt = $conn->prepare("UPDATE telegram_pending_matches SET
        customer_name_raw = ?, plate_raw = ?,
        ocr_imei = ?, ocr_serial = ?, ocr_model_guess = ?,
        ocr_phone_number = ?, ocr_operator_guess = ?, ocr_notes = ?,
        ocr_raw_response = ?, matched_product_id = ?, matched_simcard_id = ?, matched_customer_id = ?,
        status = 'pending'
        WHERE id = ?");
    $stmt->bind_param(
        "sssssssssiiii",
        $customer_name_raw, $plate_raw,
        $imei, $serial, $model_guess,
        $phone, $operator_guess, $notes,
        $ocr['raw'], $matched_product_id, $matched_simcard_id, $matched_customer_id,
        $row_id
    );
    $stmt->execute();
    $stmt->close();

    $summary = "📥 Yeni kayıt CRM onayına düştü.\n";
    $summary .= "Müşteri: " . ($customer_name_raw ?: '(okunamadı)') . ($plate_raw ? " / $plate_raw" : '') . "\n";
    $summary .= "Cihaz: " . ($imei ? "IMEI $imei" . ($matched_product_id ? ' ✅ stokta bulundu' : ' ⚠️ stokta eşleşmedi') : '(bulunamadı)') . "\n";
    $summary .= "Sim: " . ($phone ? "$phone" . ($matched_simcard_id ? ' ✅ stokta bulundu' : ' ⚠️ stokta eşleşmedi') : '(bulunamadı)');
    tg_send_message($row['telegram_chat_id'], $summary);
}

function mark_row_error($conn, $row_id, $error_message) {
    $stmt = $conn->prepare("UPDATE telegram_pending_matches SET status = 'pending', error_message = ? WHERE id = ?");
    $stmt->bind_param("si", $error_message, $row_id);
    $stmt->execute();
    $stmt->close();
}

// Tek foto olarak gelen ve cihaz/sim etiketi olmadığı anlaşılan bir görseli
// telegram_pending_matches'ten silip telegram_pending_customers'a taşır ve
// vergi levhası olarak işler.
function redirect_solo_photo_to_tax_certificate($conn, $row_id, $row) {
    $stmt = $conn->prepare("INSERT INTO telegram_pending_customers (telegram_chat_id, telegram_file_id, telegram_media_type, caption_raw) VALUES (?, ?, 'photo', ?)");
    $stmt->bind_param("iss", $row['telegram_chat_id'], $row['photo_1_file_id'], $row['caption_raw']);
    $stmt->execute();
    $new_id = $conn->insert_id;
    $stmt->close();

    $del = $conn->prepare("DELETE FROM telegram_pending_matches WHERE id = ?");
    $del->bind_param("i", $row_id);
    $del->execute();
    $del->close();

    process_pending_telegram_customer_row($conn, $new_id, 'image/jpeg');
}

function process_pending_telegram_customer_row($conn, $row_id, $mime_type) {
    $stmt = $conn->prepare("SELECT * FROM telegram_pending_customers WHERE id = ?");
    $stmt->bind_param("i", $row_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return;

    $bytes = tg_download_file_bytes($row['telegram_file_id']);
    if (!$bytes) {
        mark_customer_row_error($conn, $row_id, 'Belge Telegram sunucusundan indirilemedi.');
        tg_send_message($row['telegram_chat_id'], '⚠️ Vergi levhası indirilemedi, CRM\'de manuel gözden geçirme gerekecek.');
        return;
    }

    $ocr = claude_ocr_tax_certificate($bytes, $mime_type);

    if (!$ocr['ok']) {
        mark_customer_row_error($conn, $row_id, $ocr['error']);
        tg_send_message($row['telegram_chat_id'], "⚠️ Vergi levhası okunamadı, CRM'de manuel gözden geçirme gerekecek.\nHata: " . $ocr['error']);
        return;
    }

    $parsed = $ocr['parsed'];
    $entity_type = $parsed['entity_type'] ?? null;
    $name = trim((string)($parsed['name'] ?? ''));
    $tax_number = trim((string)($parsed['tax_number'] ?? ''));
    $address = trim((string)($parsed['address'] ?? ''));
    $notes = $parsed['notes'] ?? '';

    // İsimle mevcut müşteri eşleştir (bulanık, tek sonuç bulunursa - güncelleme senaryosu)
    $matched_customer_id = null;
    if (!empty($name)) {
        $like = "%$name%";
        $stmt = $conn->prepare("SELECT id FROM customers WHERE name LIKE ? LIMIT 2");
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (count($results) === 1) $matched_customer_id = $results[0]['id'];
    }

    $stmt = $conn->prepare("UPDATE telegram_pending_customers SET
        ocr_entity_type = ?, ocr_name = ?, ocr_tax_number = ?, ocr_address = ?, ocr_notes = ?,
        ocr_raw_response = ?, matched_customer_id = ?, status = 'pending'
        WHERE id = ?");
    $stmt->bind_param("ssssssii", $entity_type, $name, $tax_number, $address, $notes, $ocr['raw'], $matched_customer_id, $row_id);
    $stmt->execute();
    $stmt->close();

    $summary = "📄 Vergi levhası CRM onayına düştü.\n";
    $summary .= "İsim/Unvan: " . ($name ?: '(okunamadı)') . "\n";
    $summary .= "Vergi/T.C. No: " . ($tax_number ?: '(okunamadı)') . "\n";
    $summary .= $matched_customer_id ? "Mevcut müşteriyle eşleşti, bilgileri güncellenecek." : "Yeni müşteri olarak eklenecek.";
    tg_send_message($row['telegram_chat_id'], $summary);
}

function mark_customer_row_error($conn, $row_id, $error_message) {
    $stmt = $conn->prepare("UPDATE telegram_pending_customers SET status = 'pending', error_message = ? WHERE id = ?");
    $stmt->bind_param("si", $error_message, $row_id);
    $stmt->execute();
    $stmt->close();
}
?>
