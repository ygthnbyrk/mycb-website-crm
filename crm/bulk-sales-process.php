<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['bulk_sales_data'])) {
    $_SESSION['error'] = "Geçersiz istek. Lütfen tekrar deneyin.";
    header('Location: bulk-sales-upload.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$sales_data = $_SESSION['bulk_sales_data'];

// Geçerli satış yoksa
if (empty($sales_data)) {
    $_SESSION['error'] = "İşlenecek geçerli satış bulunamadı.";
    header('Location: bulk-sales-upload.php');
    exit();
}

$success_count = 0;
$error_count = 0;
$total_items = 0;

// Transaction başlat
$conn->begin_transaction();

try {
    // ============================================
    // ADIM 1: Verileri Müşteri + Tarih bazında grupla
    // ============================================
    $grouped_sales = [];
    
    foreach ($sales_data as $sale) {
        // Hatalı satırları atla
        if (isset($sale['has_error']) && $sale['has_error']) {
            $error_count++;
            continue;
        }
        
        // Müşteri ve tarih kontrolü
        if (empty($sale['customer_id']) || empty($sale['parsed_date'])) {
            $error_count++;
            continue;
        }
        
        // Gruplama anahtarı: müşteri_id + tarih
        $group_key = $sale['customer_id'] . '_' . $sale['parsed_date'];
        
        if (!isset($grouped_sales[$group_key])) {
            $grouped_sales[$group_key] = [
                'customer_id' => $sale['customer_id'],
                'parsed_date' => $sale['parsed_date'],
                'products' => [],
                'simcards' => [],
                'total_price' => 0
            ];
        }
        
        // Ürün varsa ekle
        if (!empty($sale['product_id'])) {
            $grouped_sales[$group_key]['products'][] = [
                'product_id' => $sale['product_id'],
                'product_name' => $sale['product_name'],
                'imei' => $sale['imei'],
                'plaka' => $sale['plaka'],
                'price' => $sale['urun_fiyati']
            ];
            $grouped_sales[$group_key]['total_price'] += floatval($sale['urun_fiyati']);
        }
        
        // SIM varsa ekle
        if (!empty($sale['simcard_id'])) {
            $grouped_sales[$group_key]['simcards'][] = [
                'simcard_id' => $sale['simcard_id'],
                'sim_name' => $sale['sim_name'],
                'sim_telefon' => $sale['sim_telefon'],
                'sim_telefon_display' => $sale['sim_telefon_display'] ?? $sale['sim_telefon'],
                'price' => $sale['sim_fiyati']
            ];
            $grouped_sales[$group_key]['total_price'] += floatval($sale['sim_fiyati']);
        }
    }
    
    // ============================================
    // ADIM 2: Her grup için tek satış kaydı oluştur
    // ============================================
    foreach ($grouped_sales as $group_key => $group) {
        // Toplam fiyat hesapla
        $subtotal = $group['total_price'];
        $vat_rate = 0.20; // %20 KDV
        $vat = $subtotal * $vat_rate;
        $total = $subtotal + $vat;
        
        // Satış kaydı oluştur
        $stmt_sale = $conn->prepare("INSERT INTO sales (created_by, customer_id, sale_date, subtotal, vat, total) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt_sale) {
            throw new Exception("SQL hazırlama hatası: " . $conn->error);
        }
        
        $stmt_sale->bind_param("iisddd", 
            $user_id,
            $group['customer_id'],
            $group['parsed_date'],
            $subtotal,
            $vat,
            $total
        );
        
        if (!$stmt_sale->execute()) {
            throw new Exception("Satış kaydı oluşturulamadı: " . $stmt_sale->error);
        }
        
        $sale_id = $conn->insert_id;
        $stmt_sale->close();
        
        // ============================================
        // ADIM 3: Ürünleri işle
        // ============================================
        foreach ($group['products'] as $product) {
            // Ürün durumunu güncelle
            $stmt_product = $conn->prepare("UPDATE products SET status = 'Satıldı' WHERE id = ?");
            if (!$stmt_product) {
                throw new Exception("SQL hatası: " . $conn->error);
            }
            $stmt_product->bind_param("i", $product['product_id']);
            if (!$stmt_product->execute()) {
                throw new Exception("Ürün durumu güncellenemedi: " . $stmt_product->error);
            }
            $stmt_product->close();
            
            // Eşleştirme kaydet (sale_products tablosu)
            $stmt_match = $conn->prepare("INSERT INTO sale_products (sale_id, product_id, model, imei_number, plate, price) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt_match) {
                throw new Exception("SQL hatası: " . $conn->error);
            }
            $stmt_match->bind_param("iisssd", 
                $sale_id, 
                $product['product_id'],
                $product['product_name'],
                $product['imei'],
                $product['plaka'],
                $product['price']
            );
            if (!$stmt_match->execute()) {
                throw new Exception("Ürün eşleştirme kaydedilemedi: " . $stmt_match->error);
            }
            $stmt_match->close();
            
            // Abonelik oluştur (24 ay)
            $renewal_date_product = date('Y-m-d', strtotime($group['parsed_date'] . ' + 24 months'));
            $stmt_sub = $conn->prepare("INSERT INTO subscriptions (
                sale_id, customer_id, product_id, item_type, item_name, item_detail, 
                cycle, initial_sale_date, renewal_date, status, created_at
            ) VALUES (?, ?, ?, 'product', ?, ?, 1, ?, ?, 'Aktif', NOW())");
            if (!$stmt_sub) {
                throw new Exception("SQL hatası: " . $conn->error);
            }
            $stmt_sub->bind_param("iiissss",
                $sale_id,
                $group['customer_id'],
                $product['product_id'],
                $product['product_name'],
                $product['imei'],
                $group['parsed_date'],
                $renewal_date_product
            );
            if (!$stmt_sub->execute()) {
                throw new Exception("Ürün aboneliği oluşturulamadı: " . $stmt_sub->error);
            }
            $stmt_sub->close();
            
            $total_items++;
        }
        
        // ============================================
        // ADIM 4: SIM kartları işle
        // ============================================
        foreach ($group['simcards'] as $simcard) {
            // SIM durumunu güncelle
            $stmt_sim = $conn->prepare("UPDATE simcards SET status = 'Satıldı' WHERE id = ?");
            if (!$stmt_sim) {
                throw new Exception("SQL hatası: " . $conn->error);
            }
            $stmt_sim->bind_param("i", $simcard['simcard_id']);
            if (!$stmt_sim->execute()) {
                throw new Exception("SIM durumu güncellenemedi: " . $stmt_sim->error);
            }
            $stmt_sim->close();
            
            // Eşleştirme kaydet (sale_simcards tablosu)
            $stmt_match = $conn->prepare("INSERT INTO sale_simcards (sale_id, simcard_id, phone_number, operator, price) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt_match) {
                throw new Exception("SQL hatası: " . $conn->error);
            }
            $sim_display = $simcard['sim_telefon_display'];
            $stmt_match->bind_param("iissd", 
                $sale_id, 
                $simcard['simcard_id'],
                $sim_display,
                $simcard['sim_name'],
                $simcard['price']
            );
            if (!$stmt_match->execute()) {
                throw new Exception("SIM eşleştirme kaydedilemedi: " . $stmt_match->error);
            }
            $stmt_match->close();
            
            // Abonelik oluştur (24 ay)
            $renewal_date_sim = date('Y-m-d', strtotime($group['parsed_date'] . ' + 24 months'));
            $stmt_sub = $conn->prepare("INSERT INTO subscriptions (
                sale_id, customer_id, simcard_id, item_type, item_name, item_detail, 
                cycle, initial_sale_date, renewal_date, status, created_at
            ) VALUES (?, ?, ?, 'simcard', ?, ?, 1, ?, ?, 'Aktif', NOW())");
            if (!$stmt_sub) {
                throw new Exception("SQL hatası: " . $conn->error);
            }
            $stmt_sub->bind_param("iiissss",
                $sale_id,
                $group['customer_id'],
                $simcard['simcard_id'],
                $simcard['sim_name'],
                $sim_display,
                $group['parsed_date'],
                $renewal_date_sim
            );
            if (!$stmt_sub->execute()) {
                throw new Exception("SIM aboneliği oluşturulamadı: " . $stmt_sub->error);
            }
            $stmt_sub->close();
            
            $total_items++;
        }
        
        $success_count++;
    }
    
    // Her şey başarılı, commit yap
    $conn->commit();
    
    // Session'daki veriyi temizle
    unset($_SESSION['bulk_sales_data']);
    
    // Başarı mesajı
    $_SESSION['success'] = "✅ Toplu satış başarıyla tamamlandı! $success_count adet satış kaydı oluşturuldu ($total_items ürün/sim işlendi).";
    
    header('Location: sales-list.php');
    exit();
    
} catch (Exception $e) {
    // Hata oldu, rollback yap
    $conn->rollback();
    
    // Detaylı hata mesajı
    $error_msg = "❌ İşlem sırasında hata oluştu: " . $e->getMessage();
    error_log("Bulk Sales Error: " . $e->getMessage());
    
    $_SESSION['error'] = $error_msg;
    header('Location: bulk-sales-upload.php');
    exit();
}
?>