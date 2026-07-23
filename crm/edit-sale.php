<?php
require_once 'config.php';
require_once 'partials/icons.php';

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
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Satış Düzenle - CRM</title>
</head>
<body class="center-page">
    <div class="center-container">
        <div class="center-header">
            <h1>Satış Düzenle #<?php echo $sale['id']; ?></h1>
            <a href="sales-list.php" class="btn btn-secondary"><?php echo icon('x'); ?> Kapat</a>
        </div>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="alert alert-warning">
            <strong>Uyarı:</strong> Satış düzenleme sınırlıdır. Sadece tarih, fiyat ve notlar değiştirilebilir. Ürün/SIM değişikliği için satışı silin ve yeniden oluşturun.
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
            <h2><?php echo icon('package'); ?> Satılan Ürünler (Salt Okunur)</h2>
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
            <h2><?php echo icon('sim'); ?> Satılan SIM Kartlar (Salt Okunur)</h2>
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
                <h2><?php echo icon('edit'); ?> Düzenlenebilir Bilgiler</h2>
                
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
                    <input type="number" step="0.01" name="total" value="<?php echo $sale['total']; ?>" readonly style="font-weight: 700;">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;"><?php echo icon('check'); ?> Değişiklikleri Kaydet</button>
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