<?php
require_once 'config.php';
require_once 'partials/quote-pdf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    die('Geçersiz teklif.');
}

$stmt = $conn->prepare("SELECT * FROM quotes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$quote = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quote) {
    http_response_code(404);
    die('Teklif bulunamadı.');
}

$items_stmt = $conn->prepare("SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order ASC, id ASC");
$items_stmt->bind_param("i", $id);
$items_stmt->execute();
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

function trMoney($n) {
    return number_format((float)$n, 2, ',', '.');
}
function trVat($n) {
    $n = (float)$n;
    return (floor($n) == $n) ? number_format($n, 0, ',', '.') : number_format($n, 1, ',', '.');
}

$COL_NAME = 100;
$COL_MID = 45;
$COL_TOTAL = 35;

$pdf = new QuotePdf();
$pdf->AddPage();

// Teklif meta bilgisi
$pdf->SetFont('DejaVu', 'B', 12);
$pdf->SetTextColor(20, 20, 20);
$pdf->Cell(0, 6, $quote['quote_number'], 0, 1, 'L');
$pdf->SetFont('DejaVu', '', 9);
$pdf->SetTextColor(120, 120, 120);
$pdf->Cell(0, 5, 'Teklif Tarihi: ' . date('d.m.Y', strtotime($quote['quote_date'])), 0, 1, 'L');
if ($quote['valid_until']) {
    $pdf->Cell(0, 5, 'Geçerlilik Tarihi: ' . date('d.m.Y', strtotime($quote['valid_until'])), 0, 1, 'L');
}
$pdf->Ln(4);

// Müşteri bilgileri
$pdf->SectionTitle('Müşteri Bilgileri');
$pdf->SetFont('DejaVu', 'B', 11);
$pdf->SetTextColor(20, 20, 20);
$pdf->Cell(0, 6, $quote['customer_name'], 0, 1, 'L');
$pdf->SetFont('DejaVu', '', 9.5);
$pdf->SetTextColor(90, 90, 90);
if (!empty($quote['customer_tax_number'])) {
    $pdf->Cell(0, 5, 'Vergi No: ' . $quote['customer_tax_number'], 0, 1, 'L');
}
if (!empty($quote['customer_phone'])) {
    $pdf->Cell(0, 5, 'Telefon: ' . $quote['customer_phone'], 0, 1, 'L');
}
if (!empty($quote['customer_email'])) {
    $pdf->Cell(0, 5, 'E-posta: ' . $quote['customer_email'], 0, 1, 'L');
}
if (!empty($quote['customer_address'])) {
    $pdf->MultiCell(0, 5, 'Adres: ' . $quote['customer_address'], 0, 'L');
}
$pdf->Ln(4);

// Kalemler
$pdf->SectionTitle('Teklif Kalemleri');
$pdf->SetFont('DejaVu', 'B', 8.5);
$pdf->SetTextColor(130, 130, 130);
$pdf->Cell($COL_NAME, 5, 'AÇIKLAMA', 0, 0, 'L');
$pdf->Cell($COL_MID, 5, 'MİKTAR x FİYAT (KDV)', 0, 0, 'R');
$pdf->Cell($COL_TOTAL, 5, 'TOPLAM', 0, 1, 'R');
$pdf->Ln(1);

foreach ($items as $item) {
    $pdf->SetFont('DejaVu', 'B', 10);
    $pdf->SetTextColor(20, 20, 20);
    $pdf->Cell($COL_NAME, 6.5, $item['name'], 0, 0, 'L');

    $pdf->SetFont('DejaVu', '', 9);
    $pdf->SetTextColor(110, 110, 110);
    $midText = trVat($item['qty']) . ' x ' . trMoney($item['unit_price']) . ' ₺  (%' . trVat($item['vat_rate']) . ')';
    $pdf->Cell($COL_MID, 6.5, $midText, 0, 0, 'R');

    $pdf->SetFont('DejaVu', 'B', 10);
    $pdf->SetTextColor(30, 30, 30);
    $pdf->Cell($COL_TOTAL, 6.5, trMoney($item['line_total']) . ' ₺', 0, 1, 'R');

    if (!empty($item['description'])) {
        $pdf->SetFont('DejaVu', '', 8.5);
        $pdf->SetTextColor(140, 140, 140);
        $pdf->MultiCell(0, 4.2, $item['description'], 0, 'L');
    }

    $pdf->SetDrawColor(240, 240, 240);
    $pdf->SetLineWidth(0.2);
    $pdf->Line(QuotePdf::MARGIN, $pdf->GetY() + 1, QuotePdf::PAGE_WIDTH - QuotePdf::MARGIN, $pdf->GetY() + 1);
    $pdf->Ln(4);
}

$pdf->Ln(2);

// Toplamlar
$pdf->SetFont('DejaVu', '', 10);
$pdf->SetTextColor(90, 90, 90);
$pdf->Cell($COL_NAME + $COL_MID, 6, 'Ara Toplam', 0, 0, 'R');
$pdf->Cell($COL_TOTAL, 6, trMoney($quote['subtotal']) . ' ₺', 0, 1, 'R');

$pdf->Cell($COL_NAME + $COL_MID, 6, 'KDV Toplamı', 0, 0, 'R');
$pdf->Cell($COL_TOTAL, 6, trMoney($quote['vat_total']) . ' ₺', 0, 1, 'R');

$pdf->SetDrawColor(220, 38, 38);
$pdf->SetLineWidth(0.3);
$pdf->Line(QuotePdf::MARGIN + $COL_NAME, $pdf->GetY() + 1, QuotePdf::PAGE_WIDTH - QuotePdf::MARGIN, $pdf->GetY() + 1);
$pdf->Ln(3);

$pdf->SetFont('DejaVu', 'B', 13);
$pdf->SetTextColor(20, 20, 20);
$pdf->Cell($COL_NAME + $COL_MID, 8, 'GENEL TOPLAM', 0, 0, 'R');
$pdf->Cell($COL_TOTAL, 8, trMoney($quote['total']) . ' ₺', 0, 1, 'R');

// Notlar / Şartlar
if (!empty($quote['notes'])) {
    $pdf->Ln(6);
    $pdf->SectionTitle('Notlar / Şartlar');
    $pdf->SetFont('DejaVu', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->MultiCell(0, 5, $quote['notes'], 0, 'L');
}

// Katalog sayfaları: seçilen ürünlerin görselleri + teknik özellikleri
if (!empty($quote['catalog_images'])) {
    $quote_catalog = require 'partials/quote-catalog.php';
    $selected_keys = array_filter(explode(',', $quote['catalog_images']));

    foreach ($selected_keys as $key) {
        if (!isset($quote_catalog[$key])) continue;
        $product = $quote_catalog[$key];

        $pdf->AddPage();

        $pdf->SetFont('DejaVu', 'B', 16);
        $pdf->SetTextColor(20, 20, 20);
        $pdf->Cell(0, 9, $product['label'], 0, 1, 'L');
        $pdf->Ln(2);

        if (!empty($product['specs'])) {
            $pdf->SetFont('DejaVu', 'B', 10);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->Cell(0, 6, 'TEKNİK ÖZELLİKLER', 0, 1, 'L');
            $pdf->SetFont('DejaVu', '', 9.5);
            $pdf->SetTextColor(60, 60, 60);
            foreach ($product['specs'] as $spec) {
                $pdf->SetX(QuotePdf::MARGIN);
                $pdf->Cell(4, 5.5, '•', 0, 0, 'L');
                $pdf->SetX(QuotePdf::MARGIN + 5);
                $pdf->MultiCell(QuotePdf::CONTENT_WIDTH - 5, 5.5, $spec, 0, 'L');
            }
            $pdf->Ln(4);
        }

        if (!empty($product['images'])) {
            $imgPaths = array_map(fn($img) => __DIR__ . '/assets/images/products/' . $img, $product['images']);
            $pdf->ImageRow($imgPaths, count($imgPaths) > 1 ? 65 : 90);
        }
    }
}

$filename = 'Teklif-' . $quote['quote_number'] . '.pdf';
$pdf->Output('D', $filename);
exit;
?>
