<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? '';
    $model = trim($_POST['model']);
    $product_name = trim($_POST['product_name']);
    $serial_number = trim($_POST['serial_number']) ?: null;
    $imei_number = trim($_POST['imei_number']);
    $cost_price = floatval($_POST['cost_price']);
    $vat = floatval($_POST['vat']);
    $total_cost = floatval($_POST['total_cost']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']) ?: null;
    $user_id = $_SESSION['user_id'];
    
    // Zorunlu alan kontrolü
    if (empty($model) || empty($product_name) || empty($imei_number) || empty($category) || $cost_price <= 0) {
        $_SESSION['error'] = 'Zorunlu alanları doldurun.';
        header('Location: products.php');
        exit;
    }
    
    if (!empty($product_id)) {
        // GÜNCELLEME
        // IMEI kontrolü (kendi kaydı hariç)
        $check = $conn->prepare("SELECT id FROM products WHERE imei_number = ? AND id != ?");
        $check->bind_param("si", $imei_number, $product_id);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $_SESSION['error'] = 'Bu IMEI numarası ile kayıtlı başka bir ürün var.';
            header('Location: products.php');
            exit;
        }
        $check->close();
        
        $stmt = $conn->prepare("UPDATE products SET model=?, product_name=?, serial_number=?, imei_number=?, cost_price=?, vat=?, total_cost=?, category=?, description=? WHERE id=?");
        $stmt->bind_param("ssssdddssi", $model, $product_name, $serial_number, $imei_number, $cost_price, $vat, $total_cost, $category, $description, $product_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Ürün başarıyla güncellendi.';
        } else {
            $_SESSION['error'] = 'Ürün güncellenirken hata oluştu.';
        }
        
    } else {
        // YENİ KAYIT
        // IMEI kontrolü
        $check = $conn->prepare("SELECT id FROM products WHERE imei_number = ?");
        $check->bind_param("s", $imei_number);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $_SESSION['error'] = 'Bu IMEI numarası ile kayıtlı bir ürün zaten var!';
            header('Location: products.php');
            exit;
        }
        $check->close();
        
        $stmt = $conn->prepare("INSERT INTO products (model, product_name, serial_number, imei_number, cost_price, vat, total_cost, category, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssdddssi", $model, $product_name, $serial_number, $imei_number, $cost_price, $vat, $total_cost, $category, $description, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Ürün başarıyla eklendi.';
        } else {
            $_SESSION['error'] = 'Ürün eklenirken hata oluştu.';
        }
    }
    
    $stmt->close();
}

header('Location: products.php');
exit;
?>