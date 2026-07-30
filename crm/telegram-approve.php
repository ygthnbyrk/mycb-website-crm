<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: telegram-review.php');
    exit;
}

$pending_id = intval($_POST['pending_id'] ?? 0);
$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM telegram_pending_matches WHERE id = ? AND status = 'pending'");
$stmt->bind_param("i", $pending_id);
$stmt->execute();
$pending = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pending) {
    $_SESSION['error'] = 'Kayıt bulunamadı ya da zaten işlenmiş.';
    header('Location: telegram-review.php');
    exit;
}

if ($action === 'reject') {
    $stmt = $conn->prepare("UPDATE telegram_pending_matches SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param("ii", $user_id, $pending_id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success'] = 'Kayıt reddedildi.';
    header('Location: telegram-review.php');
    exit;
}

if ($action !== 'approve') {
    header('Location: telegram-review.php');
    exit;
}

$customer_id = intval($_POST['customer_id'] ?? 0);
$sale_date = $_POST['sale_date'] ?? date('Y-m-d');
$plate = trim($_POST['plate'] ?? '');

if ($customer_id <= 0) {
    $_SESSION['error'] = 'Müşteri seçilmeden onaylanamaz.';
    header('Location: telegram-review.php');
    exit;
}

$conn->begin_transaction();

try {
    // --- Ürün: mevcut ya da yeni ---
    $product_mode = $_POST['product_mode'] ?? '';
    $product_id = null;
    $product_imei = null;
    $product_model = null;
    $product_price = floatval($_POST['product_price'] ?? 0);

    if ($product_mode === 'existing') {
        $product_id = intval($_POST['product_id'] ?? 0);
        if ($product_id <= 0) throw new Exception('Cihaz seçilmedi.');
        $stmt = $conn->prepare("SELECT imei_number, model FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$p) throw new Exception('Seçilen cihaz bulunamadı.');
        $product_imei = $p['imei_number'];
        $product_model = $p['model'];
    } elseif ($product_mode === 'new') {
        $product_model = trim($_POST['p_model'] ?? '');
        $product_name = trim($_POST['p_product_name'] ?? $product_model);
        $product_imei = trim($_POST['p_imei'] ?? '');
        $serial_number = trim($_POST['p_serial'] ?? '') ?: null;
        $category = trim($_POST['p_category'] ?? 'Telematik');
        $cost_price = floatval($_POST['p_cost_price'] ?? 0);
        $vat = floatval($_POST['p_vat'] ?? ($cost_price * 0.20));
        $total_cost = floatval($_POST['p_total_cost'] ?? ($cost_price + $vat));

        if (empty($product_model) || empty($product_imei)) throw new Exception('Yeni cihaz için model ve IMEI zorunlu.');

        $check = $conn->prepare("SELECT id FROM products WHERE imei_number = ?");
        $check->bind_param("s", $product_imei);
        $check->execute();
        if ($check->get_result()->num_rows > 0) throw new Exception('Bu IMEI ile kayıtlı bir ürün zaten var - "Mevcut" seçip ara.');
        $check->close();

        $stmt = $conn->prepare("INSERT INTO products (model, product_name, serial_number, imei_number, cost_price, vat, total_cost, category, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Stokta', ?)");
        $stmt->bind_param("ssssdddsi", $product_model, $product_name, $serial_number, $product_imei, $cost_price, $vat, $total_cost, $category, $user_id);
        $stmt->execute();
        $product_id = $conn->insert_id;
        $stmt->close();
    } elseif ($product_mode === 'none') {
        $product_price = 0;
    } else {
        throw new Exception('Cihaz bilgisi eksik.');
    }
    $has_product = $product_mode !== 'none';

    // --- Sim kart: mevcut ya da yeni ---
    $simcard_mode = $_POST['simcard_mode'] ?? '';
    $simcard_id = null;
    $simcard_phone = null;
    $simcard_operator = null;
    $simcard_price = floatval($_POST['simcard_price'] ?? 0);

    if ($simcard_mode === 'existing') {
        $simcard_id = intval($_POST['simcard_id'] ?? 0);
        if ($simcard_id <= 0) throw new Exception('Sim kart seçilmedi.');
        $stmt = $conn->prepare("SELECT phone_number, operator FROM simcards WHERE id = ?");
        $stmt->bind_param("i", $simcard_id);
        $stmt->execute();
        $s = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$s) throw new Exception('Seçilen sim kart bulunamadı.');
        $simcard_phone = $s['phone_number'];
        $simcard_operator = $s['operator'];
    } elseif ($simcard_mode === 'new') {
        $simcard_phone = trim($_POST['s_phone'] ?? '');
        $simcard_operator = trim($_POST['s_operator'] ?? '');
        $company = trim($_POST['s_company'] ?? '');
        $category = trim($_POST['s_category'] ?? 'Sim Kart');
        $cost_price = floatval($_POST['s_cost_price'] ?? 0);
        $vat = floatval($_POST['s_vat'] ?? ($cost_price * 0.20));
        $total_cost = floatval($_POST['s_total_cost'] ?? ($cost_price + $vat));

        if (empty($simcard_phone) || empty($simcard_operator) || empty($company)) throw new Exception('Yeni sim kart için telefon, operatör ve şirket zorunlu.');

        $check = $conn->prepare("SELECT id FROM simcards WHERE phone_number = ?");
        $check->bind_param("s", $simcard_phone);
        $check->execute();
        if ($check->get_result()->num_rows > 0) throw new Exception('Bu numara ile kayıtlı bir sim kart zaten var - "Mevcut" seçip ara.');
        $check->close();

        $stmt = $conn->prepare("INSERT INTO simcards (phone_number, operator, company, category, status, cost_price, vat, total_cost, created_by) VALUES (?, ?, ?, ?, 'Stokta', ?, ?, ?, ?)");
        $stmt->bind_param("ssssdddi", $simcard_phone, $simcard_operator, $company, $category, $cost_price, $vat, $total_cost, $user_id);
        $stmt->execute();
        $simcard_id = $conn->insert_id;
        $stmt->close();
    } elseif ($simcard_mode === 'none') {
        $simcard_price = 0;
    } else {
        throw new Exception('Sim kart bilgisi eksik.');
    }
    $has_simcard = $simcard_mode !== 'none';

    if (!$has_product && !$has_simcard) {
        throw new Exception('En az cihaz veya sim karttan biri seçilmeli.');
    }

    // --- Satış oluştur (create-sale.php / save-sale.php ile aynı mantık) ---
    // Aynı müşteri için aynı tarihte, bu Telegram akışıyla daha önce onaylanmış bir satış
    // varsa (örn. aynı gün birden fazla cihaz/sim fotoğrafı geldiyse) yeni satış açmak
    // yerine o satışa ekleriz - sales-list.php'de tek satır altında birleşik görünsün diye.
    $subtotal = $product_price + $simcard_price;
    $vat_amount = $subtotal * 0.20;
    $total = $subtotal + $vat_amount;

    $find_sale_stmt = $conn->prepare("SELECT s.id FROM sales s
        WHERE s.customer_id = ? AND s.sale_date = ?
        AND EXISTS (SELECT 1 FROM telegram_pending_matches tpm WHERE tpm.sale_id = s.id)
        ORDER BY s.id DESC LIMIT 1");
    $find_sale_stmt->bind_param("is", $customer_id, $sale_date);
    $find_sale_stmt->execute();
    $existing_sale = $find_sale_stmt->get_result()->fetch_assoc();
    $find_sale_stmt->close();

    if ($existing_sale) {
        $sale_id = $existing_sale['id'];
        $update_sale_stmt = $conn->prepare("UPDATE sales SET subtotal = subtotal + ?, vat = vat + ?, total = total + ? WHERE id = ?");
        $update_sale_stmt->bind_param("dddi", $subtotal, $vat_amount, $total, $sale_id);
        $update_sale_stmt->execute();
        $update_sale_stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO sales (sale_date, customer_id, subtotal, vat, total, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sidddi", $sale_date, $customer_id, $subtotal, $vat_amount, $total, $user_id);
        $stmt->execute();
        $sale_id = $conn->insert_id;
        $stmt->close();
    }

    $renewal_date = date('Y-m-d', strtotime($sale_date . ' + 24 months'));

    if ($has_product) {
        $stmt = $conn->prepare("INSERT INTO sale_products (sale_id, product_id, imei_number, model, price, plate) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissds", $sale_id, $product_id, $product_imei, $product_model, $product_price, $plate);
        $stmt->execute();
        $stmt->close();

        $conn->query("UPDATE products SET status = 'Satıldı' WHERE id = " . intval($product_id));

        $stmt = $conn->prepare("INSERT INTO subscriptions (sale_id, customer_id, product_id, item_type, item_name, item_detail, initial_sale_date, renewal_date) VALUES (?, ?, ?, 'product', ?, ?, ?, ?)");
        $stmt->bind_param("iiissss", $sale_id, $customer_id, $product_id, $product_model, $product_imei, $sale_date, $renewal_date);
        $stmt->execute();
        $stmt->close();
    }

    if ($has_simcard) {
        $stmt = $conn->prepare("INSERT INTO sale_simcards (sale_id, simcard_id, phone_number, operator, price) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iissd", $sale_id, $simcard_id, $simcard_phone, $simcard_operator, $simcard_price);
        $stmt->execute();
        $stmt->close();

        $conn->query("UPDATE simcards SET status = 'Satıldı' WHERE id = " . intval($simcard_id));

        $stmt = $conn->prepare("INSERT INTO subscriptions (sale_id, customer_id, simcard_id, item_type, item_name, item_detail, initial_sale_date, renewal_date) VALUES (?, ?, ?, 'simcard', ?, ?, ?, ?)");
        $stmt->bind_param("iiissss", $sale_id, $customer_id, $simcard_id, $simcard_operator, $simcard_phone, $sale_date, $renewal_date);
        $stmt->execute();
        $stmt->close();
    }

    if ($has_product && $has_simcard) {
        $stmt = $conn->prepare("INSERT INTO sale_mappings (sale_id, product_id, simcard_id, imei_number, phone_number) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiss", $sale_id, $product_id, $simcard_id, $product_imei, $simcard_phone);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare("UPDATE telegram_pending_matches SET status = 'approved', sale_id = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param("iii", $sale_id, $user_id, $pending_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    $_SESSION['success'] = 'Satış oluşturuldu ve onaylandı.';
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = 'Onaylanamadı: ' . $e->getMessage();
}

header('Location: telegram-review.php');
exit;
?>
