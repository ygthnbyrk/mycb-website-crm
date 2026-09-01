<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$like = '%' . $q . '%';
$stmt = $conn->prepare(
    "SELECT id, model, product_name, category, cost_price
     FROM products
     WHERE category IN ('Kamera', 'Aksesuar', 'Hizmet')
       AND status = 'Stokta'
       AND (model LIKE ? OR product_name LIKE ?)
     ORDER BY category, model
     LIMIT 15"
);
$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($r = $result->fetch_assoc()) {
    $rows[] = [
        'id' => (int)$r['id'],
        'model' => $r['model'],
        'product_name' => $r['product_name'],
        'category' => $r['category'],
        'cost_price' => (float)$r['cost_price'],
    ];
}

echo json_encode($rows);
