<?php
// Türkiye cep telefonu numaralarını "0549 111 11 11" formatına normalize eder.
// Tanınmayan bir format gelirse (örn. sabit hat, eksik hane) olduğu gibi (trim edilmiş) bırakır.
function normalize_tr_phone($raw) {
    $digits = preg_replace('/\D/', '', (string)$raw);

    // 90XXXXXXXXXX (ülke kodu ile, 12 hane) -> ülke kodunu at
    if (strlen($digits) === 12 && substr($digits, 0, 2) === '90') {
        $digits = substr($digits, 2);
    }

    // 10 haneli, başında 0 yok (5XXXXXXXXX) -> başına 0 ekle
    if (strlen($digits) === 10 && $digits[0] === '5') {
        $digits = '0' . $digits;
    }

    if (strlen($digits) === 11 && $digits[0] === '0') {
        return substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7, 2) . ' ' . substr($digits, 9, 2);
    }

    return trim((string)$raw);
}
?>
