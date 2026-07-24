<?php
require_once 'config.php';
header('Content-Type: text/plain; charset=utf-8');

$res = $conn->query("SELECT id, name, address FROM customers ORDER BY id DESC LIMIT 40");
while ($c = $res->fetch_assoc()) {
    echo $c['id'] . " | " . $c['name'] . " | [" . $c['address'] . "]\n";
}
