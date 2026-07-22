<?php
require_once 'config.php';
require_once 'SimpleXLSXGen.php';

use Shuchkin\SimpleXLSXGen;

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Filtreler
$search = $_GET['search'] ?? '';
$year = $_GET['year'] ?? '';

// Satışları çek
$sql = "SELECT s.*, c.name as customer_name, c.tax_number 
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        WHERE 1=1";

$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (c.name LIKE ? OR 
                   s.id IN (SELECT sale_id FROM sale_products WHERE imei_number LIKE ? OR plate LIKE ?))";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($year)) {
    $sql .= " AND YEAR(s.sale_date) = ?";
    $params[] = $year;
    $types .= 'i';
}

$sql .= " ORDER BY s.sale_date DESC, s.id DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
} else {
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$sales = $stmt->get_result();
$stmt->close();

// Excel verilerini hazırla
$data = [
    ['<b>Satış ID</b>', '<b>Tarih</b>', '<b>Müşteri</b>', '<b>Vergi No</b>', '<b>Ürünler</b>', '<b>IMEI</b>', '<b>Plaka</b>', '<b>Sim Kartlar</b>', '<b>Operatör</b>', '<b>Ara Toplam</b>', '<b>KDV</b>', '<b>Toplam</b>', '<b>Eşleştirme</b>']
];

while ($sale = $sales->fetch_assoc()) {
    // Satış ürünlerini çek
    $products_sql = "SELECT * FROM sale_products WHERE sale_id = ?";
    $stmt_p = $conn->prepare($products_sql);
    $stmt_p->bind_param("i", $sale['id']);
    $stmt_p->execute();
    $products = $stmt_p->get_result();
    
    $product_list = [];
    $imei_list = [];
    $plate_list = [];
    
    while ($p = $products->fetch_assoc()) {
        $product_list[] = $p['model'];
        $imei_list[] = $p['imei_number'];
        $plate_list[] = $p['plate'] ? $p['plate'] : '-';
    }
    $stmt_p->close();
    
    // Satış sim kartlarını çek
    $simcards_sql = "SELECT * FROM sale_simcards WHERE sale_id = ?";
    $stmt_s = $conn->prepare($simcards_sql);
    $stmt_s->bind_param("i", $sale['id']);
    $stmt_s->execute();
    $simcards = $stmt_s->get_result();
    
    $simcard_list = [];
    $operator_list = [];
    
    while ($s = $simcards->fetch_assoc()) {
        $simcard_list[] = $s['phone_number'];
        $operator_list[] = $s['operator'];
    }
    $stmt_s->close();
    
    // Eşleştirmeleri çek
    $mappings_sql = "SELECT * FROM sale_mappings WHERE sale_id = ?";
    $stmt_m = $conn->prepare($mappings_sql);
    $stmt_m->bind_param("i", $sale['id']);
    $stmt_m->execute();
    $mappings_result = $stmt_m->get_result();
    
    $mapping_list = [];
    while ($m = $mappings_result->fetch_assoc()) {
        $mapping_list[] = substr($m['imei_number'], -4) . " → " . $m['phone_number'];
    }
    $stmt_m->close();
    
    // Excel satırı
    $data[] = [
        $sale['id'],
        date('d.m.Y', strtotime($sale['sale_date'])),
        $sale['customer_name'],
        $sale['tax_number'],
        implode("\n", $product_list),
        implode("\n", $imei_list),
        implode("\n", $plate_list),
        implode("\n", $simcard_list),
        implode("\n", $operator_list),
        number_format($sale['subtotal'], 2, ',', '.'),
        number_format($sale['vat'], 2, ',', '.'),
        number_format($sale['total'], 2, ',', '.'),
        implode("\n", $mapping_list)
    ];
}

$filename = 'satislar_' . date('Y-m-d_H-i-s') . '.xlsx';

$xlsx = SimpleXLSXGen::fromArray($data);
$xlsx->downloadAs($filename);

$conn->close();
exit;
?>