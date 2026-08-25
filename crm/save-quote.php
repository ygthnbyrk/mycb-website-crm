<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_tax_number = trim($_POST['customer_tax_number'] ?? '') ?: null;
    $customer_phone = trim($_POST['customer_phone'] ?? '') ?: null;
    $customer_email = trim($_POST['customer_email'] ?? '') ?: null;
    $customer_address = trim($_POST['customer_address'] ?? '') ?: null;
    $quote_date = $_POST['quote_date'] ?? date('Y-m-d');
    $valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
    $notes = trim($_POST['notes'] ?? '') ?: null;
    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $vat_total = floatval($_POST['vat_total'] ?? 0);
    $total = floatval($_POST['total'] ?? 0);
    $user_id = $_SESSION['user_id'];

    $items_data = json_decode($_POST['items_data'] ?? '[]', true);
    $items_data = is_array($items_data) ? $items_data : [];
    $items_data = array_values(array_filter($items_data, fn($it) => trim($it['name'] ?? '') !== ''));

    // Seçilen katalog ürünlerini (PDF'e eklenecek görsel/özellik sayfaları) doğrula
    $quote_catalog = require 'partials/quote-catalog.php';
    $selected_catalog = $_POST['catalog_images'] ?? [];
    $selected_catalog = is_array($selected_catalog) ? array_values(array_intersect($selected_catalog, array_keys($quote_catalog))) : [];
    $catalog_images = !empty($selected_catalog) ? implode(',', $selected_catalog) : null;

    if ($customer_name === '' || empty($items_data)) {
        $_SESSION['error'] = 'Müşteri adı ve en az bir kalem gerekli.';
        header('Location: create-quote.php');
        exit;
    }

    // Teklif numarası üret: TKL-YYYY-NNNN (o yıl içinde şimdiye kadar kullanılmış en
    // yüksek sıra numarasına göre - COUNT(*) kullanmıyoruz çünkü bir teklif silinirse
    // sayı geriler ve halen var olan bir numarayla çakışır).
    $year = date('Y', strtotime($quote_date));
    $max_stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(quote_number, '-', -1) AS UNSIGNED)) as maxnum FROM quotes WHERE quote_number LIKE ?");
    $like = "TKL-$year-%";
    $max_stmt->bind_param("s", $like);
    $max_stmt->execute();
    $max_num = (int)($max_stmt->get_result()->fetch_assoc()['maxnum'] ?? 0);
    $max_stmt->close();
    $quote_number = sprintf('TKL-%s-%04d', $year, $max_num + 1);

    $stmt = $conn->prepare("INSERT INTO quotes (quote_number, customer_id, customer_name, customer_tax_number, customer_phone, customer_email, customer_address, quote_date, valid_until, notes, catalog_images, subtotal, vat_total, total, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "sisssssssssdddi",
        $quote_number,
        $customer_id,
        $customer_name,
        $customer_tax_number,
        $customer_phone,
        $customer_email,
        $customer_address,
        $quote_date,
        $valid_until,
        $notes,
        $catalog_images,
        $subtotal,
        $vat_total,
        $total,
        $user_id
    );

    if ($stmt->execute()) {
        $quote_id = $conn->insert_id;
        $stmt->close();

        $item_stmt = $conn->prepare("INSERT INTO quote_items (quote_id, name, description, qty, unit_price, vat_rate, line_total, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($items_data as $i => $item) {
            $name = trim($item['name'] ?? '');
            $description = trim($item['description'] ?? '') ?: null;
            $qty = floatval($item['qty'] ?? 0);
            $unit_price = floatval($item['unit_price'] ?? 0);
            $vat_rate = floatval($item['vat_rate'] ?? 0);
            $line_total = $qty * $unit_price * (1 + $vat_rate / 100);

            $item_stmt->bind_param("issddddi", $quote_id, $name, $description, $qty, $unit_price, $vat_rate, $line_total, $i);
            $item_stmt->execute();
        }
        $item_stmt->close();

        $_SESSION['success'] = 'Teklif başarıyla oluşturuldu (' . $quote_number . ').';
        header('Location: quotes.php');
        exit;
    } else {
        $_SESSION['error'] = 'Teklif kaydedilirken hata oluştu.';
        header('Location: create-quote.php');
        exit;
    }
}

header('Location: create-quote.php');
exit;
?>
