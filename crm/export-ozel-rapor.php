<?php
require_once 'config.php';
require_once 'partials/custom-reports.php';
require_once 'SimpleXLSXGen.php';

use Shuchkin\SimpleXLSXGen;

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: hub.php');
    exit();
}

$reports = get_custom_reports();
$slug = $_GET['rapor'] ?? '';
if (!isset($reports[$slug])) {
    http_response_code(404);
    exit('Rapor bulunamadı');
}

$def = $reports[$slug];
$param_values = [];
foreach (($def['params'] ?? []) as $key => $spec) {
    $raw = $_GET[$key] ?? $spec['default'];
    $param_values[$key] = ($spec['type'] === 'number') ? (int)$raw : (string)$raw;
}

$rows = $def['run']($conn, $param_values);

$data = [array_map(fn($h) => '<b>' . $h . '</b>', $def['columns'])];
foreach ($rows as $row) {
    $data[] = $row;
}

$filename = ($def['filename'] ?? $slug) . '_' . date('Y-m-d_H-i-s') . '.xlsx';

$xlsx = SimpleXLSXGen::fromArray($data);
$xlsx->downloadAs($filename);

$conn->close();
exit;
