<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$sale_id = intval($_GET['id'] ?? 0);

if ($sale_id === 0) {
    $_SESSION['error'] = 'Geçersiz satış ID.';
    header('Location: sales-list.php');
    exit();
}

// Satış bilgilerini çek
$stmt = $conn->prepare("SELECT s.*, c.name as customer_name, c.tax_number 
                        FROM sales s 
                        LEFT JOIN customers c ON s.customer_id = c.id 
                        WHERE s.id = ?");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sale) {
    $_SESSION['error'] = 'Satış bulunamadı.';
    header('Location: sales-list.php');
    exit();
}

// Satış ürünlerini çek
$stmt_products = $conn->prepare("SELECT * FROM sale_products WHERE sale_id = ?");
$stmt_products->bind_param("i", $sale_id);
$stmt_products->execute();
$products = $stmt_products->get_result();
$stmt_products->close();

// Satış sim kartlarını çek
$stmt_sims = $conn->prepare("SELECT * FROM sale_simcards WHERE sale_id = ?");
$stmt_sims->bind_param("i", $sale_id);
$stmt_sims->execute();
$simcards = $stmt_sims->get_result();
$stmt_sims->close();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Satış Düzenle - CRM</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 40px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .header h1 { font-size: 24px; color: #333; }
        .close-btn {
            padding: 8px 16px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .info-item label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .info-item p {
            font-size: 16px;
            color: #333;
        }
        .form-section {
            margin-bottom: 30px;
        }
        .form-section h2 {
            font-size: 18px;
            color: #667eea;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group input[readonly] {
            background: #f8f9fa;
            color: #666;
        }
        .product-list, .sim-list {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .product-item, .sim-item {
            padding: 10px;
            background: white;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        .product-item:last-child, .sim-item:last-child {
            margin-bottom: 0;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }
        .btn-success {
            background: #28a745;
            color: white;
            width: 100%;
        }
        .btn-success:hover { background: #218838; }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Satış Düzenle #<?php echo $sale['id']; ?></h1>
            <a href="sales-list.php" class="close-btn">✖ Kapat</a>
        </div>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="alert alert-warning">
            <strong>⚠️ Uyarı:</strong> Satış düzenleme sınırlıdır. Sadece tarih, fiyat ve notlar değiştirilebilir. Ürün/SIM değişikliği için satışı silin ve yeniden oluşturun.
        </div>

        <!-- Satış Bilgileri (Salt Okunur) -->
        <div class="info-grid">
            <div class="info-item">
                <label>Müşteri</label>
                <p><?php echo htmlspecialchars($sale['customer_name']); ?></p>
            </div>
            <div class="info-item">
                <label>Vergi No</label>
                <p><?php echo htmlspecialchars($sale['tax_number']); ?></p>
            </div>
        </div>

        <!-- Ürünler -->
        <?php if($products->num_rows > 0): ?>
        <div class="form-section">
            <h2>📦 Satılan Ürünler (Salt Okunur)</h2>
            <div class="product-list">
                <?php while($product = $products->fetch_assoc()): ?>
                <div class="product-item">
                    <strong><?php echo htmlspecialchars($product['model']); ?></strong><br>
                    <small>IMEI: <?php echo htmlspecialchars($product['imei_number']); ?></small><br>
                    <small>Plaka: <?php echo htmlspecialchars($product['plate']) ?: '-'; ?></small>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- SIM Kartlar -->
        <?php if($simcards->num_rows > 0): ?>
        <div class="form-section">
            <h2>📱 Satılan SIM Kartlar (Salt Okunur)</h2>
            <div class="sim-list">
                <?php while($sim = $simcards->fetch_assoc()): ?>
                <div class="sim-item">
                    <strong><?php echo htmlspecialchars($sim['phone_number']); ?></strong><br>
                    <small><?php echo htmlspecialchars($sim['operator']); ?> - <?php echo htmlspecialchars($sim['company']); ?></small>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Düzenlenebilir Form -->
        <form method="POST" action="update-sale.php">
            <input type="hidden" name="sale_id" value="<?php echo $sale['id']; ?>">

            <div class="form-section">
                <h2>✏️ Düzenlenebilir Bilgiler</h2>
                
                <div class="form-group">
                    <label>Satış Tarihi</label>
                    <input type="date" name="sale_date" value="<?php echo $sale['sale_date']; ?>" required>
                </div>

                <div class="form-group">
                    <label>Ara Toplam (₺)</label>
                    <input type="number" step="0.01" name="subtotal" value="<?php echo $sale['subtotal']; ?>" required>
                </div>

                <div class="form-group">
                    <label>KDV (₺)</label>
                    <input type="number" step="0.01" name="vat" value="<?php echo $sale['vat']; ?>" required>
                </div>

                <div class="form-group">
                    <label>Genel Toplam (₺)</label>
                    <input type="number" step="0.01" name="total" value="<?php echo $sale['total']; ?>" readonly style="background: #f0f0f0; font-weight: bold;">
                </div>
            </div>

            <button type="submit" class="btn btn-success">💾 Değişiklikleri Kaydet</button>
        </form>
    </div>

    <script>
        // Toplam hesaplama
        const subtotalInput = document.querySelector('input[name="subtotal"]');
        const vatInput = document.querySelector('input[name="vat"]');
        const totalInput = document.querySelector('input[name="total"]');

        function calculateTotal() {
            const subtotal = parseFloat(subtotalInput.value) || 0;
            const vat = parseFloat(vatInput.value) || 0;
            totalInput.value = (subtotal + vat).toFixed(2);
        }

        subtotalInput.addEventListener('input', calculateTotal);
        vatInput.addEventListener('input', calculateTotal);
    </script>
</body>
</html>