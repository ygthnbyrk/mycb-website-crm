<?php
$is_debug = getenv('APP_DEBUG') === '1';
error_reporting($is_debug ? E_ALL : 0);
ini_set('display_errors', $is_debug ? 1 : 0);
session_start();

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'mycbyigb_crm_user');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'mycbyigb_crm_device');

define('PARASUT_CLIENT_ID', getenv('PARASUT_CLIENT_ID') ?: '');
define('PARASUT_CLIENT_SECRET', getenv('PARASUT_CLIENT_SECRET') ?: '');
define('PARASUT_REDIRECT_URI', 'urn:ietf:wg:oauth:2.0:oob');

define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: '');
define('TELEGRAM_WEBHOOK_SECRET', getenv('TELEGRAM_WEBHOOK_SECRET') ?: '');
define('TELEGRAM_ALERT_CHAT_ID', getenv('TELEGRAM_ALERT_CHAT_ID') ?: '');
define('CRON_SECRET', getenv('CRON_SECRET') ?: '');
define('ANTHROPIC_API_KEY', getenv('ANTHROPIC_API_KEY') ?: '');

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset("utf8mb4");

    
    if ($conn->connect_error) {
        die("Bağlantı hatası: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

date_default_timezone_set('Europe/Istanbul');