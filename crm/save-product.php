<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Ürünler sayfası hem Araç Takip (Telematik) hem Teknoloji (Kamera/Aksesuar/Hizmet)
// tarafından ayrı sayfalarda kullanılıyor; kaydettikten sonra geldiği sayfaya dönsün.
$allowed_redirects = ['products.php', 'teknoloji-urunler.php'];
$redirect_to = in_array($_POST['from'] ?? '', $allowed_redirects, true) ? $_POST['from'] : 'products.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? '';
    $model = trim($_POST['model']);
    $product_name = trim($_POST['product_name']);
    $serial_number = trim($_POST['serial_number']) ?: null;
    $imei_number = trim($_POST['imei_number']) ?: null;
    $cost_price = floatval($_POST['cost_price']);
    $vat = floatval($_POST['vat']);
    $total_cost = floatval($_POST['total_cost']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']) ?: null;
    $user_id = $_SESSION['user_id'];
    $quantity = max(1, intval($_POST['quantity'] ?? 1));

    // IMEI sadece Telematik (araç takip) kategorisinde zorunlu; Aksesuar/Hizmet/Kamera
    // kalemlerinin (kablo, Montaj, bazı kameralar) IMEI'si olmaz.
    $imei_required = ($category === 'Telematik');

    // Zorunlu alan kontrolü
    if (empty($model) || empty($product_name) || ($imei_required && empty($imei_number)) || empty($category) || $cost_price < 0) {
        $_SESSION['error'] = 'Zorunlu alanları doldurun.';
        header('Location: ' . $redirect_to);
        exit;
    }

    if (!empty($product_id)) {
        // GÜNCELLEME
        // IMEI kontrolü (kendi kaydı hariç, sadece IMEI girilmişse)
        if (!empty($imei_number)) {
            $check = $conn->prepare("SELECT id FROM products WHERE imei_number = ? AND id != ?");
            $check->bind_param("si", $imei_number, $product_id);
            $check->execute();

            if ($check->get_result()->num_rows > 0) {
                $_SESSION['error'] = 'Bu IMEI numarası ile kayıtlı başka bir ürün var.';
                header('Location: ' . $redirect_to);
                exit;
            }
            $check->close();
        }

        $stmt = $conn->prepare("UPDATE products SET model=?, product_name=?, serial_number=?, imei_number=?, cost_price=?, vat=?, total_cost=?, category=?, description=? WHERE id=?");
        $stmt->bind_param("ssssdddssi", $model, $product_name, $serial_number, $imei_number, $cost_price, $vat, $total_cost, $category, $description, $product_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Ürün başarıyla güncellendi.';
        } else {
            $_SESSION['error'] = 'Ürün güncellenirken hata oluştu.';
        }
        
    } else {
        // YENİ KAYIT
        // IMEI kontrolü (sadece IMEI girilmişse)
        if (!empty($imei_number)) {
            $check = $conn->prepare("SELECT id FROM products WHERE imei_number = ?");
            $check->bind_param("s", $imei_number);
            $check->execute();

            if ($check->get_result()->num_rows > 0) {
                $_SESSION['error'] = 'Bu IMEI numarası ile kayıtlı bir ürün zaten var!';
                header('Location: ' . $redirect_to);
                exit;
            }
            $check->close();
        }

        // Aynı IMEI'yi birden fazla kayda kopyalamamak için: IMEI girilmişse adet 1'e sabitlenir.
        if (!empty($imei_number)) {
            $quantity = 1;
        }

        $stmt = $conn->prepare("INSERT INTO products (model, product_name, serial_number, imei_number, cost_price, vat, total_cost, category, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssdddssi", $model, $product_name, $serial_number, $imei_number, $cost_price, $vat, $total_cost, $category, $description, $user_id);

        $inserted = 0;
        for ($i = 0; $i < $quantity; $i++) {
            if ($stmt->execute()) {
                $inserted++;
            }
        }

        if ($inserted === $quantity) {
            $_SESSION['success'] = $quantity > 1 ? "$inserted adet ürün başarıyla eklendi." : 'Ürün başarıyla eklendi.';
        } elseif ($inserted > 0) {
            $_SESSION['error'] = "$inserted / $quantity ürün eklendi, bir kısmında hata oluştu.";
        } else {
            $_SESSION['error'] = 'Ürün eklenirken hata oluştu.';
        }
    }
    
    $stmt->close();
}

header('Location: ' . $redirect_to);
exit;
?>