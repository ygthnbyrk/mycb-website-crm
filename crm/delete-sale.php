<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$sale_id = intval($_GET['id'] ?? 0);

if ($sale_id > 0) {
    // Satıştaki ürünleri "Stokta" yap
    $products_sql = "SELECT product_id FROM sale_products WHERE sale_id = ?";
    $stmt_p = $conn->prepare($products_sql);
    $stmt_p->bind_param("i", $sale_id);
    $stmt_p->execute();
    $products_result = $stmt_p->get_result();
    
    while ($product = $products_result->fetch_assoc()) {
        $update = $conn->prepare("UPDATE products SET status = 'Stokta' WHERE id = ?");
        $update->bind_param("i", $product['product_id']);
        $update->execute();
        $update->close();
    }
    $stmt_p->close();
    
    // Satıştaki sim kartları "Stokta" yap
    $simcards_sql = "SELECT simcard_id FROM sale_simcards WHERE sale_id = ?";
    $stmt_s = $conn->prepare($simcards_sql);
    $stmt_s->bind_param("i", $sale_id);
    $stmt_s->execute();
    $simcards_result = $stmt_s->get_result();
    
    while ($simcard = $simcards_result->fetch_assoc()) {
        $update = $conn->prepare("UPDATE simcards SET status = 'Stokta' WHERE id = ?");
        $update->bind_param("i", $simcard['simcard_id']);
        $update->execute();
        $update->close();
    }
    $stmt_s->close();
    
    // Satışa ait TÜM abonelikleri sil (tüm döngüler)
    $delete_subs = $conn->prepare("DELETE FROM subscriptions WHERE sale_id = ?");
    $delete_subs->bind_param("i", $sale_id);
    $delete_subs->execute();
    $deleted_count = $delete_subs->affected_rows;
    $delete_subs->close();
    
    // Satışı sil (CASCADE ile detaylar da silinir)
    $stmt = $conn->prepare("DELETE FROM sales WHERE id = ?");
    $stmt->bind_param("i", $sale_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Satış başarıyla silindi. ' . $deleted_count . ' abonelik kaydı da silindi. Ürünler stoğa geri eklendi.';
    } else {
        $_SESSION['error'] = 'Satış silinirken hata oluştu.';
    }
    $stmt->close();
} else {
    $_SESSION['error'] = 'Geçersiz satış ID.';
}

header('Location: sales-list.php');
exit;
?>