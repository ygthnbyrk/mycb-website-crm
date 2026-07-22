<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = $_POST['customer_id'] ?? '';
    $name = trim($_POST['name']);
    $tax_number = trim($_POST['tax_number']);
    $email = trim($_POST['email']) ?: null;
    $phone = trim($_POST['phone']) ?: null;
    $address = trim($_POST['address']) ?: null;
    $user_id = $_SESSION['user_id'];
    
    if (empty($name) || empty($tax_number)) {
        $_SESSION['error'] = 'Müşteri adı ve vergi numarası zorunlu.';
        header('Location: customers.php');
        exit();
    }
    
    if (!empty($customer_id)) {
        // Güncelleme
        $check = $conn->prepare("SELECT id FROM customers WHERE tax_number = ? AND id != ?");
        $check->bind_param("si", $tax_number, $customer_id);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $_SESSION['error'] = 'Bu vergi numarası zaten kayıtlı.';
            header('Location: customers.php');
            exit();
        }
        
        $stmt = $conn->prepare("UPDATE customers SET name=?, tax_number=?, email=?, phone=?, address=? WHERE id=?");
        $stmt->bind_param("sssssi", $name, $tax_number, $email, $phone, $address, $customer_id);
        $stmt->execute();
        $_SESSION['success'] = 'Müşteri güncellendi.';
        
    } else {
        // Yeni kayıt
        $check = $conn->prepare("SELECT id FROM customers WHERE tax_number = ?");
        $check->bind_param("s", $tax_number);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $_SESSION['error'] = 'Bu vergi numarası zaten kayıtlı!';
            header('Location: customers.php');
            exit();
        }
        
        $stmt = $conn->prepare("INSERT INTO customers (name, tax_number, email, phone, address, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $name, $tax_number, $email, $phone, $address, $user_id);
        $stmt->execute();
        $_SESSION['success'] = 'Müşteri eklendi.';
    }
}

header('Location: customers.php');
exit();
?>
```

**Save Changes**

---

### ADIM 3: Dosya İzinlerini Kontrol Et

Her 3 dosyaya da:
1. **Sağ tıkla** → **"Change Permissions"** veya **"İzinler"**
2. **644** olarak ayarla
3. Kaydet

---

### ADIM 4: Dosyaların Varlığını Kontrol Et

**Dosya Yöneticisi**'nde **crm** klasöründe şunlar olmalı:
```
crm/
├── config.php ✓
├── index.php ✓
├── dashboard.php ✓
├── customers.php ✓
├── save-customer.php ← YENİ
├── get-customer.php ← YENİ
├── delete-customer.php ← YENİ
├── logout.php ✓