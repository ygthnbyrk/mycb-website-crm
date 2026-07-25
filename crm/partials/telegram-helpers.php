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
        'text' => "Bu fotoğraf(lar) bir araç takip cihazının etiketini (IMEI/seri numarası barkodu) ve/veya bir SIM kart ambalajını (telefon numarası/operatör) gösterebilir. " .
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
                    'iccid' => ['type' => ['string', 'null']],
                    'operator_guess' => ['type' => ['string', 'null']],
                ],
                'required' => ['found', 'phone_number', 'iccid', 'operator_guess'],
                'additionalProperties' => false,
            ],
            'notes' => ['type' => 'string'],
        ],
        'required' => ['device', 'simcard', 'notes'],
        'additionalProperties' => false,
    ];

    $body = [
        'model' => 'claude-sonnet-5',
        'max_tokens' => 1024,
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
    if ($decoded === null || empty($decoded['content'][0]['text'])) {
        $result['error'] = 'Claude yanıtı beklenmeyen formatta.';
        return $result;
    }

    $parsed = json_decode($decoded['content'][0]['text'], true);
    if ($parsed === null) {
        $result['error'] = 'Claude JSON çıktısı parse edilemedi.';
        return $result;
    }

    $result['ok'] = true;
    $result['parsed'] = $parsed;
    return $result;
}
?>
