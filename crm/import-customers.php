<?php
require_once 'config.php';
require_once 'SimpleXLSX.php';

use Shuchkin\SimpleXLSX;

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    $user_id = $_SESSION['user_id'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'Dosya yüklenirken hata oluştu.';
        header('Location: customers.php');
        exit;
    }
    
    $allowedExtensions = ['xlsx', 'xls'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        $_SESSION['error'] = 'Sadece Excel dosyaları yüklenebilir (.xlsx, .xls)';
        header('Location: customers.php');
        exit;
    }
    
    try {
        if ($xlsx = SimpleXLSX::parse($file['tmp_name'])) {
            $rows = $xlsx->rows();
            
            // İlk satır başlık
            array_shift($rows);
            
            $success_count = 0;
            $error_count = 0;
            $duplicate_count = 0;
            
            foreach ($rows as $row) {
                if (empty($row[0]) || empty($row[1])) {
                    continue;
                }
                
                $name = trim($row[0]);
                $tax_number = trim($row[1]);
                $email = !empty($row[2]) ? trim($row[2]) : null;
                $phone = !empty($row[3]) ? trim($row[3]) : null;
                $address = !empty($row[4]) ? trim($row[4]) : null;
                
                // Vergi numarası kontrolü
                $check = $conn->prepare("SELECT id FROM customers WHERE tax_number = ?");
                $check->bind_param("s", $tax_number);
                $check->execute();
                
                if ($check->get_result()->num_rows > 0) {
                    $duplicate_count++;
                    $check->close();
                    continue;
                }
                $check->close();
                
                // Müşteri ekle
                $stmt = $conn->prepare("INSERT INTO customers (name, tax_number, email, phone, address, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssi", $name, $tax_number, $email, $phone, $address, $user_id);
                
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                $stmt->close();
            }
            
            $message = "✅ $success_count müşteri eklendi.";
            if ($duplicate_count > 0) {
                $message .= " ⚠️ $duplicate_count tekrarlı (atlandı).";
            }
            if ($error_count > 0) {
                $message .= " ❌ $error_count hata.";
            }
            
            $_SESSION['success'] = $message;
            
        } else {
            $_SESSION['error'] = 'Excel okunamadı: ' . SimpleXLSX::parseError();
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Hata: ' . $e->getMessage();
    }
}

header('Location: customers.php');
exit;
?>