<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: sales-list.php');
    exit();
}

$sale_id = intval($_POST['sale_id']);
$new_sale_date = $_POST['sale_date'];
$subtotal = floatval($_POST['subtotal']);
$vat = floatval($_POST['vat']);
$total = floatval($_POST['total']);

// Validasyon
if (empty($new_sale_date) || $subtotal < 0 || $vat < 0) {
    $_SESSION['error'] = 'Lütfen tüm alanları doğru doldurun.';
    header('Location: edit-sale.php?id=' . $sale_id);
    exit();
}

// ÖNCELİKLE ESKİ SATIŞ TARİHİNİ AL
$stmt_old = $conn->prepare("SELECT sale_date FROM sales WHERE id = ?");
if (!$stmt_old) {
    $_SESSION['error'] = 'SQL Hatası (sales select): ' . $conn->error;
    header('Location: edit-sale.php?id=' . $sale_id);
    exit();
}
$stmt_old->bind_param("i", $sale_id);
$stmt_old->execute();
$old_sale_result = $stmt_old->get_result()->fetch_assoc();
$old_sale_date = $old_sale_result['sale_date'];
$stmt_old->close();

// DEBUG LOG
error_log("=== SATIŞ GÜNCELLEME BAŞLADI ===");
error_log("Sale ID: " . $sale_id);
error_log("Eski tarih: " . $old_sale_date);
error_log("Yeni tarih: " . $new_sale_date);

// Transaction başlat
$conn->begin_transaction();

try {
    // 1. Satışı güncelle
    $stmt = $conn->prepare("UPDATE sales SET 
        sale_date = ?,
        subtotal = ?,
        vat = ?,
        total = ?
        WHERE id = ?");
    
    if (!$stmt) {
        throw new Exception('SQL Hatası (sales update): ' . $conn->error);
    }
    
    $stmt->bind_param("sdddi", $new_sale_date, $subtotal, $vat, $total, $sale_id);
    $stmt->execute();
    error_log("✓ Satış güncellendi");
    $stmt->close();

    // 2. EĞER SATIŞ TARİHİ DEĞİŞTİYSE ABONELİKLERİ GÜNCELLE
    if ($old_sale_date !== $new_sale_date) {
        
        error_log(">>> Tarih değişti, abonelikler güncellenecek");
        
        // Tarih farkını hesapla
        $old_date_obj = new DateTime($old_sale_date);
        $new_date_obj = new DateTime($new_sale_date);
        $date_diff = $old_date_obj->diff($new_date_obj);
        
        // Gün farkı
        $days_diff = $date_diff->days;
        if ($new_date_obj < $old_date_obj) {
            $days_diff = -$days_diff;
        }
        
        error_log("Tarih farkı: " . $days_diff . " gün");
        
        // Bu satışa bağlı abonelikleri bul
        $stmt_subs = $conn->prepare("SELECT id, initial_sale_date, renewal_date 
                                     FROM subscriptions 
                                     WHERE sale_id = ?");
        
        if (!$stmt_subs) {
            throw new Exception('SQL Hatası (subscriptions select): ' . $conn->error);
        }
        
        $stmt_subs->bind_param("i", $sale_id);
        $stmt_subs->execute();
        $subscriptions_result = $stmt_subs->get_result();
        $subscription_count = 0;
        
        error_log("Bulunan abonelik sayısı: " . $subscriptions_result->num_rows);
        
        // Her aboneliği güncelle
        while ($sub = $subscriptions_result->fetch_assoc()) {
            error_log("--- Abonelik ID: " . $sub['id'] . " güncelleniyor");
            error_log("Eski initial_sale_date: " . $sub['initial_sale_date']);
            error_log("Eski renewal_date: " . $sub['renewal_date']);
            
            // Yeni tarihleri hesapla
            $new_initial_date = date('Y-m-d', strtotime($sub['initial_sale_date'] . " $days_diff days"));
            $new_renewal_date = date('Y-m-d', strtotime($sub['renewal_date'] . " $days_diff days"));
            
            error_log("Yeni initial_sale_date: " . $new_initial_date);
            error_log("Yeni renewal_date: " . $new_renewal_date);
            
            // Aboneliği güncelle
            $stmt_update_sub = $conn->prepare("UPDATE subscriptions SET 
                initial_sale_date = ?,
                renewal_date = ?
                WHERE id = ?");
            
            if (!$stmt_update_sub) {
                throw new Exception('SQL Hatası (subscriptions update): ' . $conn->error);
            }
            
            $stmt_update_sub->bind_param("ssi", 
                $new_initial_date, 
                $new_renewal_date, 
                $sub['id']
            );
            
            if ($stmt_update_sub->execute()) {
                error_log("✓ Abonelik güncellendi - Etkilenen satır: " . $stmt_update_sub->affected_rows);
                $subscription_count++;
            } else {
                error_log("✗ Abonelik güncelleme hatası: " . $stmt_update_sub->error);
                throw new Exception('Abonelik güncelleme hatası: ' . $stmt_update_sub->error);
            }
            
            $stmt_update_sub->close();
        }
        
        $stmt_subs->close();
        
        error_log("Toplam güncellenen abonelik: " . $subscription_count);
        
        // Başarı mesajı
        if ($subscription_count > 0) {
            $_SESSION['success'] = "✅ Satış başarıyla güncellendi! 🔄 $subscription_count abonelik tarihi de otomatik olarak güncellendi.";
        } else {
            $_SESSION['success'] = '✅ Satış başarıyla güncellendi! (Bu satışa bağlı abonelik bulunamadı)';
        }
    } else {
        error_log(">>> Tarih değişmedi, abonelik güncellenmeyecek");
        $_SESSION['success'] = '✅ Satış başarıyla güncellendi!';
    }
    
    // Transaction'ı commit et
    $conn->commit();
    error_log("=== TRANSACTION COMMIT EDİLDİ ===");
    header('Location: sales-list.php');
    
} catch (Exception $e) {
    // Hata durumunda rollback
    $conn->rollback();
    error_log("=== HATA - ROLLBACK YAPILDI ===");
    error_log("Hata mesajı: " . $e->getMessage());
    $_SESSION['error'] = '❌ Hata: ' . $e->getMessage();
    header('Location: edit-sale.php?id=' . $sale_id);
}

exit();
?>