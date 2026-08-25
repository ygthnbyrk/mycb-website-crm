<?php
// Telegram Bot API ve Claude Vision (OCR) yardımcı fonksiyonları.
// config.php içindeki TELEGRAM_BOT_TOKEN / ANTHROPIC_API_KEY sabitlerini kullanır.

function tg_api_call($method, $params = []) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/" . $method;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function tg_send_message($chat_id, $text) {
    return tg_api_call('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $text,
    ]);
}

// Telegram file_id -> ham dosya içeriği (bot token client'a hiç sızmaz, hep sunucu tarafında indirilir)
function tg_download_file_bytes($file_id) {
    $info = tg_api_call('getFile', ['file_id' => $file_id]);
    if (empty($info['ok']) || empty($info['result']['file_path'])) {
        return null;
    }
    $file_path = $info['result']['file_path'];
    $url = "https://api.telegram.org/file/bot" . TELEGRAM_BOT_TOKEN . "/" . $file_path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $bytes = curl_exec($ch);
    curl_close($ch);
    return $bytes ?: null;
}

// 1-2 fotoğrafı Claude Sonnet 5'e gönderip cihaz etiketi (IMEI/seri) ve sim kart
// (telefon no/operatör) bilgisini yapılandırılmış JSON olarak döndürür.
function claude_ocr_device_and_sim($photo_bytes_list) {
    $content = [];
    foreach ($photo_bytes_list as $bytes) {
        if (!$bytes) continue;
        $content[] = [
            'type' => 'image',
            'source' => [
                'type' => 'base64',
                'media_type' => 'image/jpeg',
                'data' => base64_encode($bytes),
            ],
        ];
    }

    $content[] = [
        'type' => 'text',
        'text' => "Bu fotoğraf(lar) bir araç takip cihazının etiketini (IMEI/seri numarası barkodu) ve/veya bir SIM kart ambalajını (telefon numarası/operatör) gösterebilir.\n\n" .
            "CİHAZ ETİKETİ kuralları: Etikette 'IMEI' kelimesi yazmıyor olabilir. Seri numarasının (SN) hemen yanında veya altında duran, 14-15 haneli sayısal bir barkod/numara varsa bunu IMEI kabul et ve imei alanına yaz — sırf 'IMEI' etiketi görünmüyor diye imei alanını boş bırakma.\n\n" .
            "SIM KART AMBALAJI kuralları: Türkiye'deki operatör SIM ambalajlarında genellikle birden fazla sayısal barkod bulunur. ICCID (genelde 19-20 hane, çoğunlukla '89' ile başlar) işimize yaramıyor, onu okuma/raporlama, iccid diye bir alan yok. Asıl aradığın telefon numarası: genelde 10 haneli, başında ülke içi '0' OLMADAN basılmış olabilir (örnek: ambalajda '5496162935' yazıyorsa bu telefon numarasıdır — başına 0 ekleyerek '0549 616 29 35' formatında phone_number alanına yaz). Bu barkodu 'ürün kodu' veya 'parti numarası' sanıp atlama — ICCID'den farklı, ~10 haneli sayısal bir barkod gördüğünde bunun telefon numarası olma ihtimali yüksektir.\n\n" .
            "Her görsel için ne olduğunu belirle ve okuyabildiğin alanları çıkar. Görsel bulanık/açılıysa elinden geleni yap, emin olmadığın rakamları yine de en iyi tahminin olarak yaz ve notes alanına belirsizliği yaz. Hiçbir görselde cihaz veya sim yoksa ilgili found alanını false yap.",
    ];

    $schema = [
        'type' => 'object',
        'properties' => [
            'device' => [
                'type' => 'object',
                'properties' => [
                    'found' => ['type' => 'boolean'],
                    'imei' => ['type' => ['string', 'null']],
                    'serial_number' => ['type' => ['string', 'null']],
                    'model_guess' => ['type' => ['string', 'null']],
                ],
                'required' => ['found', 'imei', 'serial_number', 'model_guess'],
                'additionalProperties' => false,
            ],
            'simcard' => [
                'type' => 'object',
                'properties' => [
                    'found' => ['type' => 'boolean'],
                    'phone_number' => ['type' => ['string', 'null']],
                    'operator_guess' => ['type' => ['string', 'null']],
                ],
                'required' => ['found', 'phone_number', 'operator_guess'],
                'additionalProperties' => false,
            ],
            'notes' => ['type' => 'string'],
        ],
        'required' => ['device', 'simcard', 'notes'],
        'additionalProperties' => false,
    ];

    return claude_structured_request($content, $schema);
}

// Vergi levhası (Ltd/A.Ş için VKN, şahıs işletmesi için T.C. kimlik no) OCR'ı.
// $mime_type: 'image/jpeg' (Telegram photo) ya da 'application/pdf' (Telegram document).
function claude_ocr_tax_certificate($file_bytes, $mime_type) {
    $block_type = $mime_type === 'application/pdf' ? 'document' : 'image';

    $content = [
        [
            'type' => $block_type,
            'source' => [
                'type' => 'base64',
                'media_type' => $mime_type,
                'data' => base64_encode($file_bytes),
            ],
        ],
        [
            'type' => 'text',
            'text' => "Bu bir Türkiye vergi levhası (JPG fotoğraf ya da PDF) olabilir, ya da bambaşka bir şey (cihaz etiketi, SIM ambalajı, alakasız bir belge) olabilir. Levhanın standart alanları: ADI SOYADI, TİCARET ÜNVANI, İŞ YERİ ADRESİ, VERGİ TÜRÜ, VERGİ DAİRESİ, VERGİ KİMLİK NO (VKN — barkodlu, 10 hane), TC KİMLİK NO (11 hane).\n\n" .
                "1) entity_type — ÖNCE 'VERGİ TÜRÜ' alanına bak: 'KURUMLAR VERGİSİ' yazıyorsa 'tuzel'; 'YILLIK GELİR VERGİSİ' (veya benzeri bir gelir vergisi türü) yazıyorsa 'sahis'. Emin olamazsan ADI SOYADI ve TC KİMLİK NO alanlarına bak: tüzel şirketlerde bu ikisi BOŞTUR (sadece TİCARET ÜNVANI ve VERGİ KİMLİK NO dolu olur); şahıs işletmelerinde ADI SOYADI ve TC KİMLİK NO DOLUDUR (VERGİ KİMLİK NO da ayrıca dolu olabilir, bu normaldir). Belge bir vergi levhası değilse ya da hiç anlayamadıysan 'bilinmiyor' yaz.\n" .
                "2) name — Önce TİCARET ÜNVANI alanındaki işletme/şirket adını yaz (varsa, hem tüzel hem şahısta genelde doludur). TİCARET ÜNVANI boşsa ADI SOYADI'nı yaz. Okuyamıyorsan null bırak.\n" .
                "3) tax_number — entity_type='sahis' ise MUTLAKA 'TC KİMLİK NO' alanındaki 11 haneli numarayı yaz (VKN'yi DEĞİL — şahıs levhasında VKN de dolu olsa bile bu sistemde şahıslar için TC kimlik no kullanılıyor). entity_type='tuzel' ise 'VERGİ KİMLİK NO' alanındaki 10 haneli numarayı yaz (TC KİMLİK NO alanı tüzelde zaten boştur). Okuyamıyorsan null bırak.\n" .
                "4) address — İŞ YERİ ADRESİ alanındaki tam adresi yaz. Okuyamıyorsan null bırak.\n" .
                "5) notes — Emin olamadığın kısımları buraya yaz. Belge bir vergi levhası DEĞİLSE (örn. cihaz etiketi, SIM ambalajı, başka bir şey), bunu açıkça belirt.\n\n" .
                "Uydurma, emin olmadığın alanları boş (null) bırak.",
        ],
    ];

    $schema = [
        'type' => 'object',
        'properties' => [
            'entity_type' => ['type' => 'string', 'enum' => ['tuzel', 'sahis', 'bilinmiyor']],
            'name' => ['type' => ['string', 'null']],
            'tax_number' => ['type' => ['string', 'null']],
            'address' => ['type' => ['string', 'null']],
            'notes' => ['type' => 'string'],
        ],
        'required' => ['entity_type', 'name', 'tax_number', 'address', 'notes'],
        'additionalProperties' => false,
    ];

    return claude_structured_request($content, $schema);
}

// Ortak: content bloklarını + json_schema'yı Claude Sonnet 5'e gönderir, metin
// bloğunu tipine göre bulup (index'e güvenmeden - adaptive thinking önce bir
// "thinking" bloğu döndürebilir) JSON olarak parse eder.
function claude_structured_request($content, $schema, $max_tokens = 1024) {
    $body = [
        'model' => 'claude-sonnet-5',
        'max_tokens' => $max_tokens,
        'messages' => [
            ['role' => 'user', 'content' => $content],
        ],
        'output_config' => [
            'format' => [
                'type' => 'json_schema',
                'schema' => $schema,
            ],
        ],
    ];

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $raw = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = ['ok' => false, 'parsed' => null, 'raw' => $raw, 'error' => null];

    if ($http_code !== 200) {
        $result['error'] = "Claude API HTTP $http_code: " . $raw;
        return $result;
    }

    $decoded = json_decode($raw, true);
    if ($decoded === null || empty($decoded['content'])) {
        $result['error'] = 'Claude yanıtı beklenmeyen formatta.';
        return $result;
    }

    $text_block = null;
    foreach ($decoded['content'] as $block) {
        if (($block['type'] ?? '') === 'text' && !empty($block['text'])) {
            $text_block = $block['text'];
            break;
        }
    }

    if ($text_block === null) {
        $result['error'] = 'Claude yanıtında metin bloğu bulunamadı (stop_reason: ' . ($decoded['stop_reason'] ?? '?') . ').';
        return $result;
    }

    $parsed = json_decode($text_block, true);
    if ($parsed === null) {
        $result['error'] = 'Claude JSON çıktısı parse edilemedi.';
        return $result;
    }

    $result['ok'] = true;
    $result['parsed'] = $parsed;
    return $result;
}

// Bekleyen bir cihaz/sim eşleştirme kaydını (fotoğraf indir -> OCR -> stokla eşleştir)
// işler ve 'pending' durumuna çeker. Webhook'tan (yeni fotoğraf geldiğinde) ve
// telegram-collecting-action.php'den (2. fotoğraf hiç gelmeyen kayıt elle "yine de
// işle" denildiğinde, tek fotoğrafla) çağrılır.
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
