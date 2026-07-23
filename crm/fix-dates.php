<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

header('Content-Type: text/plain; charset=utf-8');

$fixes = [
    523 => '2025-01-14',
    524 => '2025-01-14',
    787 => '2025-10-30',
];

foreach ($fixes as $id => $newDate) {
    $stmt = $conn->prepare("SELECT sale_date FROM sales WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        echo "id=$id bulunamadi, atlandi\n";
        continue;
    }
    $old = $row['sale_date'];

    $upd = $conn->prepare("UPDATE sales SET sale_date=? WHERE id=?");
    $upd->bind_param("si", $newDate, $id);
    $upd->execute();
    $upd->close();

    echo "id=$id: $old -> $newDate (guncellendi)\n";
}
