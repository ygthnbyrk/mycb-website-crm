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
        header('Location: simcards.php');
        exit;
    }
    
    $allowedExtensions = ['xlsx', 'xls'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        $_SESSION['error'] = 'Sadece Excel dosyaları yüklenebilir (.xlsx, .xls)';
        header('Location: simcards.php');
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
                if (empty($row[0])) {
                    continue;
                }
                
                $phone_number = trim($row[0]);
                $operator = trim($row[1]);
                $company = trim($row[2]);
                $category = trim($row[3]);
                $status = trim($row[4]);
                $cost_price = floatval($row[5]);
                $vat = $cost_price * 0.20;
                $total_cost = $cost_price + $vat;
                $description = !empty($row[8]) ? trim($row[8]) : null;
                
                // Telefon numarası kontrolü
                $check = $conn->prepare("SELECT id FROM simcards WHERE phone_number = ?");
                $check->bind_param("s", $phone_number);
                $check->execute();
                
                if ($check->get_result()->num_rows > 0) {
                    $duplicate_count++;
                    $check->close();
                    continue;
                }
                $check->close();
                
                // Sim kart ekle
                $stmt = $conn->prepare("INSERT INTO simcards (phone_number, operator, company, category, status, cost_price, vat, total_cost, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssdddsi", $phone_number, $operator, $company, $category, $status, $cost_price, $vat, $total_cost, $description, $user_id);
                
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                $stmt->close();
            }
            
            $message = "✅ $success_count sim kart eklendi.";
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

header('Location: simcards.php');
exit;
?>