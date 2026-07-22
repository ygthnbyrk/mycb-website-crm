<?php
// Form işleme dosyası
header('Content-Type: application/json');

// CSRF koruması için session başlat (opsiyonel)
// session_start();

// Form verilerini al
$name = isset($_POST['name']) ? htmlspecialchars(trim($_POST['name'])) : '';
$email = isset($_POST['email']) ? htmlspecialchars(trim($_POST['email'])) : '';
$phone = isset($_POST['phone']) ? htmlspecialchars(trim($_POST['phone'])) : '';
$message = isset($_POST['message']) ? htmlspecialchars(trim($_POST['message'])) : '';

// Validasyon
$errors = [];

if (empty($name)) {
    $errors[] = 'İsim alanı boş olamaz';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Geçerli bir e-posta adresi giriniz';
}

if (empty($phone)) {
    $errors[] = 'Telefon numarası boş olamaz';
}

if (empty($message)) {
    $errors[] = 'Mesaj alanı boş olamaz';
}

// Hata varsa geri dön
if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'errors' => $errors
    ]);
    exit;
}

// E-posta gönderimi
$to = 'info@mycbteknoloji.com'; // Kendi email adresinizi yazın
$subject = 'MYCB Teknoloji - Yeni İletişim Formu Mesajı';
$email_message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { padding: 20px; }
            .field { margin-bottom: 15px; }
            .label { font-weight: bold; color: #333; }
            .value { color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2>Yeni İletişim Formu Mesajı</h2>
            <div class='field'>
                <span class='label'>İsim:</span>
                <span class='value'>$name</span>
            </div>
            <div class='field'>
                <span class='label'>E-posta:</span>
                <span class='value'>$email</span>
            </div>
            <div class='field'>
                <span class='label'>Telefon:</span>
                <span class='value'>$phone</span>
            </div>
            <div class='field'>
                <span class='label'>Mesaj:</span>
                <div class='value'>$message</div>
            </div>
        </div>
    </body>
    </html>
";

$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= "From: $email" . "\r\n";

// E-postayı gönder
$mail_sent = mail($to, $subject, $email_message, $headers);

if ($mail_sent) {
    echo json_encode([
        'success' => true,
        'message' => 'Mesajınız başarıyla gönderildi!'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Mesaj gönderilemedi. Lütfen tekrar deneyin.'
    ]);
}
?>