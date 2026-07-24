<?php
require_once 'config.php';
header('Content-Type: text/plain; charset=utf-8');

$check = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($check->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user' AFTER name");
    echo "role kolonu eklendi.\n";
} else {
    echo "role kolonu zaten vardi.\n";
}

$conn->query("UPDATE users SET role = 'admin' WHERE email = 'yigithan.bayrak@mycbteknoloji.com'");
$conn->query("UPDATE users SET role = 'user' WHERE email = 'bilgi@mycbteknoloji.com'");

$res = $conn->query("SELECT id, email, role FROM users");
while ($u = $res->fetch_assoc()) {
    echo $u['id'] . " | " . $u['email'] . " | " . $u['role'] . "\n";
}
