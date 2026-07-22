<?php
require_once 'config.php';
require_once 'SimpleXLSXGen.php';
use Shuchkin\SimpleXLSXGen;

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Filtreleme parametrelerini al
$search = $_GET['search'] ?? '';
$filter_company = $_GET['company'] ?? '';
$filter_operator = $_GET['operator'] ?? '';

// Dinamik SQL oluşturma
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(phone_number LIKE ? OR company LIKE ? OR operator LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($filter_company)) {
    $where_conditions[] = "company = ?";
    $params[] = $filter_company;
    $types .= 's';
}

if (!empty($filter_operator)) {
    $where_conditions[] = "operator = ?";
    $params[] = $filter_operator;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Sorguyu hazırla
$sql = "SELECT phone_number, operator, company, category, status, cost_price, vat, total_cost, description, created_at 
        FROM simcards 
        $where_clause 
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);

// Parametreleri bind et
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Excel başlıkları
$data = [
    ['<b>Telefon Numarası</b>', '<b>Operatör</b>', '<b>Şirket</b>', '<b>Kategori</b>', '<b>Durum</b>', '<b>Maliyet</b>', '<b>KDV</b>', '<b>Toplam</b>', '<b>Açıklama</b>', '<b>Kayıt Tarihi</b>']
];

// Verileri ekle
while ($row = $result->fetch_assoc()) {
    $data[] = [
        $row['phone_number'],
        $row['operator'],
        $row['company'],
        $row['category'],
        $row['status'],
        $row['cost_price'],
        $row['vat'],
        $row['total_cost'],
        $row['description'] ?? '',
        $row['created_at']
    ];
}

// Dosya adını filtreler varsa belirt
$filename_suffix = '';
if ($filter_company || $filter_operator || $search) {
    $filename_suffix = '_filtreli';
}

$filename = 'simkartlar' . $filename_suffix . '_' . date('Y-m-d_H-i-s') . '.xlsx';

// Excel oluştur ve indir
$xlsx = SimpleXLSXGen::fromArray($data);
$xlsx->downloadAs($filename);

$stmt->close();
$conn->close();
exit;
?>