<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $simcard_id = $_POST['simcard_id'] ?? '';
    $phone_number = trim($_POST['phone_number']);
    $operator = trim($_POST['operator']);
    $company = trim($_POST['company']);
    $category = trim($_POST['category']);
    $status = trim($_POST['status']);
    $cost_price = floatval($_POST['cost_price']);
    $vat = floatval($_POST['vat']);
    $total_cost = floatval($_POST['total_cost']);
    $description = trim($_POST['description']) ?: null;
    $user_id = $_SESSION['user_id'];
    
    // Zorunlu alan kontrolü
    if (empty($phone_number) || empty($operator) || empty($company) || empty($category) || empty($status) || $cost_price <= 0) {
        $_SESSION['error'] = 'Zorunlu alanları doldurun.';
        header('Location: simcards.php');
        exit;
    }
    
    if (!empty($simcard_id)) {
        // GÜNCELLEME
        // Telefon numarası kontrolü (kendi kaydı hariç)
        $check = $conn->prepare("SELECT id FROM simcards WHERE phone_number = ? AND id != ?");
        $check->bind_param("si", $phone_number, $simcard_id);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $_SESSION['error'] = 'Bu telefon numarası ile kayıtlı başka bir sim kart var.';
            header('Location: simcards.php');
            exit;
        }
        $check->close();
        
        $stmt = $conn->prepare("UPDATE simcards SET phone_number=?, operator=?, company=?, category=?, status=?, cost_price=?, vat=?, total_cost=?, description=? WHERE id=?");
        $stmt->bind_param("sssssdddsi", $phone_number, $operator, $company, $category, $status, $cost_price, $vat, $total_cost, $description, $simcard_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Sim kart başarıyla güncellendi.';
        } else {
            $_SESSION['error'] = 'Sim kart güncellenirken hata oluştu.';
        }
        
    } else {
        // YENİ KAYIT
        // Telefon numarası kontrolü
        $check = $conn->prepare("SELECT id FROM simcards WHERE phone_number = ?");
        $check->bind_param("s", $phone_number);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $_SESSION['error'] = 'Bu telefon numarası ile kayıtlı bir sim kart zaten var!';
            header('Location: simcards.php');
            exit;
        }
        $check->close();
        
        $stmt = $conn->prepare("INSERT INTO simcards (phone_number, operator, company, category, status, cost_price, vat, total_cost, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssdddsi", $phone_number, $operator, $company, $category, $status, $cost_price, $vat, $total_cost, $description, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Sim kart başarıyla eklendi.';
        } else {
            $_SESSION['error'] = 'Sim kart eklenirken hata oluştu.';
        }
    }
    
    $stmt->close();
}

header('Location: simcards.php');
exit;
?>