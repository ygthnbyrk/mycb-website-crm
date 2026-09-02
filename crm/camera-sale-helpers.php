<?php
// Kamera satışı kalemlerini kaydetme/geri alma mantığı — save-camera-sale.php ve
// update-camera-sale.php arasında paylaşılır ki adet>1 durumunda stoktan doğru sayıda
// birim düşme ve düzenleme/silmede bunu doğru geri alma mantığı tek yerde kalsın.

// Bir satış kalemini (adet>1 ise aynı modelden gerektiği kadar ek stok birimiyle birlikte)
// camera_sale_items'a satır satır yazar; her fiziksel birim kendi satırında (adet=1) tutulur
// ki düzenleme/silmede hangi ürünün bu satıştan satıldığı belirsizlik olmadan bilinsin.
function saveCameraSaleItems($conn, $camera_sale_id, $customer_id, $sale_date, $items) {
    $renewal_date = date('Y-m-d', strtotime($sale_date . ' + 24 months'));
    $shortage_notes = [];

    foreach ($items as $item) {
        $product_id = !empty($item['product_id']) ? intval($item['product_id']) : null;
        $item_name = trim($item['item_name'] ?? '');
        $category = trim($item['category'] ?? '') ?: null;
        $cost_price = isset($item['cost_price']) && $item['cost_price'] !== '' ? floatval($item['cost_price']) : null;
        $sale_price = isset($item['sale_price']) && $item['sale_price'] !== '' ? floatval($item['sale_price']) : null;
        $quantity = !empty($item['quantity']) ? intval($item['quantity']) : 1;

        if ($item_name === '') {
            continue;
        }

        if ($product_id && $quantity > 1) {
            $unit_product_ids = [$product_id];

            $model_stmt = $conn->prepare("SELECT model FROM products WHERE id = ?");
            $model_stmt->bind_param('i', $product_id);
            $model_stmt->execute();
            $model_row = $model_stmt->get_result()->fetch_assoc();
            $model_stmt->close();

            if ($model_row) {
                $need = $quantity - 1;
                $extra_stmt = $conn->prepare("SELECT id FROM products WHERE model = ? AND status = 'Stokta' AND id != ? ORDER BY id ASC LIMIT ?");
                $extra_stmt->bind_param('sii', $model_row['model'], $product_id, $need);
                $extra_stmt->execute();
                $extra_result = $extra_stmt->get_result();
                while ($row = $extra_result->fetch_assoc()) {
                    $unit_product_ids[] = (int)$row['id'];
                }
                $extra_stmt->close();
            }

            foreach ($unit_product_ids as $upid) {
                insertCameraSaleItemRow($conn, $camera_sale_id, $customer_id, $upid, $category, $item_name, $cost_price, $sale_price, 1, $sale_date, $renewal_date);
            }

            $missing = $quantity - count($unit_product_ids);
            if ($missing > 0) {
                insertCameraSaleItemRow($conn, $camera_sale_id, $customer_id, null, $category, $item_name, $cost_price, $sale_price, $missing, $sale_date, $renewal_date);
                $shortage_notes[] = "$item_name: $quantity adet istendi, stokta sadece " . count($unit_product_ids) . " adet bulunduğu için kalan $missing adet stok bağlantısı olmadan kaydedildi.";
            }
        } else {
            insertCameraSaleItemRow($conn, $camera_sale_id, $customer_id, $product_id, $category, $item_name, $cost_price, $sale_price, $quantity, $sale_date, $renewal_date);
        }
    }

    return $shortage_notes;
}

function insertCameraSaleItemRow($conn, $camera_sale_id, $customer_id, $product_id, $category, $item_name, $cost_price, $sale_price, $quantity, $sale_date, $renewal_date) {
    $stmt_item = $conn->prepare("INSERT INTO camera_sale_items (camera_sale_id, product_id, category, item_name, cost_price, sale_price, quantity) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_item->bind_param('iissddi', $camera_sale_id, $product_id, $category, $item_name, $cost_price, $sale_price, $quantity);
    $stmt_item->execute();
    $stmt_item->close();

    if ($product_id) {
        $stmt_status = $conn->prepare("UPDATE products SET status = 'Satıldı' WHERE id = ?");
        $stmt_status->bind_param('i', $product_id);
        $stmt_status->execute();
        $stmt_status->close();
    }

    $item_detail = $category ?? '';
    $stmt_sub = $conn->prepare("INSERT INTO subscriptions (sale_id, customer_id, product_id, item_type, item_name, item_detail, initial_sale_date, renewal_date) VALUES (?, ?, ?, 'product', ?, ?, ?, ?)");
    $stmt_sub->bind_param('iiissss', $camera_sale_id, $customer_id, $product_id, $item_name, $item_detail, $sale_date, $renewal_date);
    $stmt_sub->execute();
    $stmt_sub->close();
}

// Bir kamera satışına bağlı ürünleri tekrar "Stokta" yapar (düzenleme/silmeden önce çağrılır).
function revertCameraSaleStock($conn, $camera_sale_id) {
    $stmt = $conn->prepare("SELECT DISTINCT product_id FROM camera_sale_items WHERE camera_sale_id = ? AND product_id IS NOT NULL");
    $stmt->bind_param('i', $camera_sale_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $upd = $conn->prepare("UPDATE products SET status = 'Stokta' WHERE id = ?");
        $upd->bind_param('i', $row['product_id']);
        $upd->execute();
        $upd->close();
    }
    $stmt->close();
}

// Bir kamera satışının kalemlerini ve bunlara bağlı abonelik kayıtlarını siler
// (yeniden kaydetmeden önce ya da satışı tamamen silerken kullanılır).
function deleteCameraSaleItemsAndSubs($conn, $camera_sale_id) {
    $stmt = $conn->prepare("DELETE FROM camera_sale_items WHERE camera_sale_id = ?");
    $stmt->bind_param('i', $camera_sale_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM subscriptions WHERE sale_id = ? AND item_type = 'product'");
    $stmt->bind_param('i', $camera_sale_id);
    $stmt->execute();
    $stmt->close();
}
?>
