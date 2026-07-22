<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

require_once __DIR__ . '/SimpleXLSXGen.php';

$rows = [
  ['model','product_name','serial_number','imei_number','cost_price','vat','total_cost','category','description'],
  ['T0','Örnek Ürün','','123456789012345',1000,20,1200,'Telematik','İsteğe bağlı açıklama']
];

$xlsx = \Shuchkin\SimpleXLSXGen::fromArray($rows);
$xlsx->downloadAs('products_template.xlsx');
exit;