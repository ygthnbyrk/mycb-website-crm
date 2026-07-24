<?php
require_once 'config.php';
header('Content-Type: text/plain; charset=utf-8');

$stmt = $conn->prepare("UPDATE users SET name = ? WHERE email = 'yigithan.bayrak@mycbteknoloji.com'");
$name = "Yiğithan Bayrak";
$stmt->bind_param("s", $name);
$stmt->execute();

$res = $conn->query("SELECT id, email, name FROM users WHERE email = 'yigithan.bayrak@mycbteknoloji.com'");
$u = $res->fetch_assoc();
echo $u['id'] . " | " . $u['email'] . " | " . $u['name'] . "\n";
