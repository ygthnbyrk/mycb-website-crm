<?php
// Paraşüt OAuth token yönetimi ve genel API GET yardımcıları.
// parasut.php (Kârlılık) ve parasut-sync-customers.php (Müşteri Senkronu)
// tarafından ortak kullanılır.

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

// Hiz siniri (429) durumunda birkac kez bekleyip tekrar dener
function parasut_api_get($access_token, $company_id, $path, $retries = 4) {
    for ($attempt = 0; $attempt <= $retries; $attempt++) {
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
        $body = json_decode($response, true);
        if ($code === 429 && $attempt < $retries) {
            usleep(1500000); // 1.5 sn bekle, tekrar dene
            continue;
        }
        return ['code' => $code, 'body' => $body];
    }
    return ['code' => $code ?? 0, 'body' => $body ?? null];
}

function normalize_tr_upper($s) {
    return mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string)$s)), 'UTF-8');
}
?>
