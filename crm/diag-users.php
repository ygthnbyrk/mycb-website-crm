<?php
require_once 'config.php';
header('Content-Type: text/plain; charset=utf-8');

$cols = $conn->query("SHOW COLUMNS FROM users");
echo "=== users tablosu kolonlari ===\n";
while ($c = $cols->fetch_assoc()) {
    echo $c['Field'] . " | " . $c['Type'] . "\n";
}

echo "\n=== kullanicilar ===\n";
$res = $conn->query("SELECT id, email, name, last_login FROM users");
while ($u = $res->fetch_assoc()) {
    echo $u['id'] . " | " . $u['email'] . " | " . $u['name'] . " | " . $u['last_login'] . "\n";
}
