<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: subscriptions.php');
    exit();
}

$subscription_id = intval($_POST['subscription_id']);
$status = $_POST['status'];
$notes = $_POST['notes'] ?? '';
$current_cycle = intval($_POST['current_cycle']);

error_log("=== UPDATE SUBSCRIPTION BAŞLADI ===");
error_log("Subscription ID: " . $subscription_id);
error_log("Status: " . $status);
error_log("POST DATA: " . print_r($_POST, true));

if ($status === 'Yenilendi') {
    // POST verilerini oku ve kontrol et
    $renewal_amount = isset($_POST['renewal_amount']) ? floatval($_POST['renewal_amount']) : 0;
    $vat = isset($_POST['vat']) ? floatval($_POST['vat']) : 0;
    $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
    $subscription_revenue = isset($_POST['subscription_revenue']) ? floatval($_POST['subscription_revenue']) : 0;
    
    error_log("Renewal Amount: " . $renewal_amount);
    error_log("VAT: " . $vat);
    error_log("Total Amount: " . $total_amount);
    error_log("Subscription Revenue: " . $subscription_revenue);
    
    // Kritik kontrol: Değerler 0 ise hata ver
    if ($renewal_amount <= 0) {
        error_log("✗ HATA: Renewal amount sıfır veya boş!");
        $_SESSION['error'] = 'Yenileme miktarı girilmemiş veya sıfır! Lütfen tutarları kontrol edin.';
        header('Location: edit-subscription.php?id=' . $subscription_id);
        exit();
    }
    
    // Eski abonelik bilgilerini çek
    $stmt = $conn->prepare("SELECT * FROM subscriptions WHERE id = ?");
    $stmt->bind_param("i", $subscription_id);
    $stmt->execute();
    $old_subscription = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$old_subscription) {
        error_log("✗ HATA: Abonelik bulunamadı!");
        $_SESSION['error'] = 'Abonelik bulunamadı.';
        header('Location: subscriptions.php');
        exit();
    }
    
    error_log("✓ Eski abonelik bulundu");
    
    // Yenileme geçmişine kaydet
    $stmt_history = $conn->prepare("INSERT INTO subscription_renewals (subscription_id, cycle, renewal_date, renewal_amount, vat, total_amount, subscription_revenue, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_history->bind_param("iisdddds", 
        $subscription_id, 
        $current_cycle, 
        $old_subscription['renewal_date'],
        $renewal_amount, 
        $vat, 
        $total_amount, 
        $subscription_revenue, 
        $notes
    );
    
    if ($stmt_history->execute()) {
        error_log("✓ Yenileme geçmişi kaydedildi");
    } else {
        error_log("✗ Yenileme geçmişi HATASI: " . $stmt_history->error);
    }
    $stmt_history->close();
    
    // ESKİ ABONELİĞİ GÜNCELLE: Durum + Gelir Bilgileri
    $stmt_update_old = $conn->prepare("UPDATE subscriptions SET 
        status = 'Yenilendi', 
        renewal_amount = ?,
        vat = ?,
        total_amount = ?,
        subscription_revenue = ?,
        notes = ? 
        WHERE id = ?");
    
    $stmt_update_old->bind_param("ddddsi", 
        $renewal_amount,
        $vat,
        $total_amount,
        $subscription_revenue,
        $notes, 
        $subscription_id
    );
    
    if ($stmt_update_old->execute()) {
        error_log("✓ Eski abonelik güncellendi:");
        error_log("  - Status: Yenilendi");
        error_log("  - Renewal Amount: " . $renewal_amount);
        error_log("  - Subscription Revenue: " . $subscription_revenue);
    } else {
        error_log("✗ Eski abonelik güncelleme HATASI: " . $stmt_update_old->error);
        $_SESSION['error'] = 'Abonelik güncellenirken hata oluştu: ' . $stmt_update_old->error;
        header('Location: subscriptions.php');
        exit();
    }
    $stmt_update_old->close();
    
    // YENİ ABONELİK KAYDI OLUŞTUR
    $new_cycle = $current_cycle + 1;
    $new_renewal_date = date('Y-m-d', strtotime($old_subscription['renewal_date'] . ' + 12 months'));
    
    error_log("=== YENİ ABONELİK OLUŞTURULUYOR ===");
    error_log("New Cycle: " . $new_cycle);
    error_log("New Renewal Date: " . $new_renewal_date);
    
    // Yeni abonelik için değerleri sıfırla (henüz yenilenecek)
    $new_renewal_amount = 0;
    $new_vat = 0;
    $new_total_amount = 0;
    $new_subscription_revenue = 0;
    
    $stmt_new = $conn->prepare("INSERT INTO subscriptions (
        sale_id, 
        customer_id, 
        product_id, 
        simcard_id, 
        item_type, 
        item_name, 
        item_detail, 
        cycle, 
        initial_sale_date, 
        renewal_date, 
        renewal_amount, 
        vat, 
        total_amount, 
        subscription_revenue, 
        status, 
        notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Aktif', '')");
    
    if (!$stmt_new) {
        error_log("✗ PREPARE HATASI: " . $conn->error);
        $_SESSION['error'] = 'Yeni abonelik hazırlanırken hata: ' . $conn->error;
        header('Location: subscriptions.php');
        exit();
    }
    
    $stmt_new->bind_param("iiiisssissdddd",
        $old_subscription['sale_id'],
        $old_subscription['customer_id'],
        $old_subscription['product_id'],
        $old_subscription['simcard_id'],
        $old_subscription['item_type'],
        $old_subscription['item_name'],
        $old_subscription['item_detail'],
        $new_cycle,
        $old_subscription['initial_sale_date'],
        $new_renewal_date,
        $new_renewal_amount,
        $new_vat,
        $new_total_amount,
        $new_subscription_revenue
    );
    
    if ($stmt_new->execute()) {
        $new_subscription_id = $conn->insert_id;
        error_log("✓✓✓ YENİ ABONELİK BAŞARILI - Insert ID: " . $new_subscription_id);
        $_SESSION['success'] = 'Abonelik başarıyla yenilendi! ' . 
                               'Yenileme Geliri: ₺' . number_format($subscription_revenue, 2) . ' | ' .
                               'Yeni döngü: ' . $new_cycle . ' | ' .
                               'Sonraki yenileme: ' . date('d.m.Y', strtotime($new_renewal_date));
    } else {
        error_log("✗✗✗ YENİ ABONELİK HATASI: " . $stmt_new->error);
        $_SESSION['error'] = 'Yeni abonelik oluşturulurken hata: ' . $stmt_new->error;
    }
    $stmt_new->close();
    
} elseif ($status === 'İptal') {
    // Aboneliği iptal et
    $stmt_cancel = $conn->prepare("UPDATE subscriptions SET status = 'İptal', notes = ? WHERE id = ?");
    $stmt_cancel->bind_param("si", $notes, $subscription_id);
    
    if ($stmt_cancel->execute()) {
        error_log("✓ Abonelik iptal edildi");
        $_SESSION['success'] = 'Abonelik başarıyla iptal edildi.';
    } else {
        error_log("✗ İptal HATASI: " . $stmt_cancel->error);
        $_SESSION['error'] = 'Abonelik iptal edilirken hata oluştu.';
    }
    $stmt_cancel->close();
    
} else {
    error_log("✗ Geçersiz durum: " . $status);
    $_SESSION['error'] = 'Geçersiz durum seçimi.';
}

error_log("=== UPDATE SUBSCRIPTION BİTTİ ===");
header('Location: subscriptions.php');
exit();
?>