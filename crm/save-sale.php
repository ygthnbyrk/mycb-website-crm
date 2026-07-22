<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sale_date = $_POST['sale_date'];
    $customer_id = intval($_POST['customer_id']);
    $subtotal = floatval($_POST['subtotal']);
    $vat = floatval($_POST['vat']);
    $total = floatval($_POST['total']);
    $user_id = $_SESSION['user_id'];
    
    $products_data = json_decode($_POST['products_data'], true);
    $simcards_data = json_decode($_POST['simcards_data'], true);
    $mappings_data = json_decode($_POST['mappings_data'], true);
    
    // Satış kaydı oluştur
    $stmt = $conn->prepare("INSERT INTO sales (sale_date, customer_id, subtotal, vat, total, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sidddi", $sale_date, $customer_id, $subtotal, $vat, $total, $user_id);
    
    if ($stmt->execute()) {
        $sale_id = $conn->insert_id;
        error_log("Satış oluşturuldu - Sale ID: " . $sale_id);
        
        // Ürünleri kaydet
        if (!empty($products_data)) {
            foreach ($products_data as $product) {
                // Sale_products'a ekle
                $stmt_product = $conn->prepare("INSERT INTO sale_products (sale_id, product_id, imei_number, model, price, plate) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_product->bind_param("iissds", 
                    $sale_id, 
                    $product['id'], 
                    $product['imei'], 
                    $product['model'], 
                    $product['price'], 
                    $product['plate']
                );
                $stmt_product->execute();
                $stmt_product->close();
                
                // Ürün durumunu güncelle
                $update_product = $conn->prepare("UPDATE products SET status = 'Satıldı' WHERE id = ?");
                $update_product->bind_param("i", $product['id']);
                $update_product->execute();
                $update_product->close();
                
                // Abonelik oluştur - ÜRÜN
                $renewal_date = date('Y-m-d', strtotime($sale_date . ' + 24 months'));
                
                error_log("=== ÜRÜN ABONELİK OLUŞTURULUYOR ===");
                error_log("Sale ID: " . $sale_id . ", Customer ID: " . $customer_id . ", Product ID: " . $product['id']);
                
                $stmt_sub = $conn->prepare("INSERT INTO subscriptions (sale_id, customer_id, product_id, item_type, item_name, item_detail, initial_sale_date, renewal_date) VALUES (?, ?, ?, 'product', ?, ?, ?, ?)");
                
                if ($stmt_sub) {
                    $stmt_sub->bind_param("iiissss", 
                        $sale_id, 
                        $customer_id, 
                        $product['id'],
                        $product['model'],
                        $product['imei'],
                        $sale_date,
                        $renewal_date
                    );
                    
                    if ($stmt_sub->execute()) {
                        error_log("✓ Ürün aboneliği BAŞARILI - Insert ID: " . $conn->insert_id);
                    } else {
                        error_log("✗ Ürün aboneliği HATASI: " . $stmt_sub->error);
                    }
                    $stmt_sub->close();
                } else {
                    error_log("✗ PREPARE HATASI: " . $conn->error);
                }
            }
        }
        
        // Sim kartları kaydet
        if (!empty($simcards_data)) {
            foreach ($simcards_data as $sim) {
                // Sale_simcards'a ekle
                $stmt_simcard = $conn->prepare("INSERT INTO sale_simcards (sale_id, simcard_id, phone_number, operator, price) VALUES (?, ?, ?, ?, ?)");
                $stmt_simcard->bind_param("iissd", 
                    $sale_id, 
                    $sim['id'], 
                    $sim['phone'], 
                    $sim['operator'], 
                    $sim['price']
                );
                $stmt_simcard->execute();
                $stmt_simcard->close();
                
                // Sim kart durumunu güncelle
                $update_simcard = $conn->prepare("UPDATE simcards SET status = 'Satıldı' WHERE id = ?");
                $update_simcard->bind_param("i", $sim['id']);
                $update_simcard->execute();
                $update_simcard->close();
                
                // Abonelik oluştur - SIM KART
                $renewal_date = date('Y-m-d', strtotime($sale_date . ' + 24 months'));
                
                error_log("=== SIM KART ABONELİK OLUŞTURULUYOR ===");
                error_log("Sale ID: " . $sale_id . ", Customer ID: " . $customer_id . ", Simcard ID: " . $sim['id']);
                
                $stmt_sub = $conn->prepare("INSERT INTO subscriptions (sale_id, customer_id, simcard_id, item_type, item_name, item_detail, initial_sale_date, renewal_date) VALUES (?, ?, ?, 'simcard', ?, ?, ?, ?)");
                
                if ($stmt_sub) {
                    $stmt_sub->bind_param("iiissss", 
                        $sale_id, 
                        $customer_id, 
                        $sim['id'],
                        $sim['operator'],
                        $sim['phone'],
                        $sale_date,
                        $renewal_date
                    );
                    
                    if ($stmt_sub->execute()) {
                        error_log("✓ Sim kart aboneliği BAŞARILI - Insert ID: " . $conn->insert_id);
                    } else {
                        error_log("✗ Sim kart aboneliği HATASI: " . $stmt_sub->error);
                    }
                    $stmt_sub->close();
                } else {
                    error_log("✗ PREPARE HATASI: " . $conn->error);
                }
            }
        }
        
        // Eşleştirmeleri kaydet
        if (!empty($mappings_data) && !empty($products_data)) {
            foreach ($mappings_data as $product_index => $simcard_index) {
                if ($simcard_index !== null && isset($products_data[$product_index]) && isset($simcards_data[$simcard_index])) {
                    $product = $products_data[$product_index];
                    $simcard = $simcards_data[$simcard_index];
                    
                    $stmt_mapping = $conn->prepare("INSERT INTO sale_mappings (sale_id, product_id, simcard_id, imei_number, phone_number) VALUES (?, ?, ?, ?, ?)");
                    $stmt_mapping->bind_param("iiiss", 
                        $sale_id, 
                        $product['id'], 
                        $simcard['id'], 
                        $product['imei'], 
                        $simcard['phone']
                    );
                    $stmt_mapping->execute();
                    $stmt_mapping->close();
                }
            }
        }
        
        $_SESSION['success'] = 'Satış başarıyla kaydedildi!';
        header('Location: sales-list.php');
        exit;
        
    } else {
        $_SESSION['error'] = 'Satış kaydedilirken hata oluştu.';
        header('Location: create-sale.php');
        exit;
    }
    
    $stmt->close();
}

header('Location: create-sale.php');
exit;
?>