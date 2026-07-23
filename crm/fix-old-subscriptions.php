<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
require_once 'partials/icons.php';

if (!isset($_SESSION['user_id'])) {
    die("Lütfen giriş yapın");
}

// POST ile güncellemeler gelirse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_subscriptions'])) {
    $updated_count = 0;
    $errors = [];
    
    foreach ($_POST['subscriptions'] as $sub_id => $data) {
        $renewal_amount = floatval($data['renewal_amount'] ?? 0);
        
        if ($renewal_amount > 0) {
            // Hesaplamalar
            $vat = $renewal_amount * 0.20;
            $total_amount = $renewal_amount + $vat;
            $subscription_revenue = $renewal_amount * 0.20;
            
            // Güncelle
            $stmt = $conn->prepare("UPDATE subscriptions SET 
                renewal_amount = ?,
                vat = ?,
                total_amount = ?,
                subscription_revenue = ?
                WHERE id = ?");
            
            $stmt->bind_param("ddddi", 
                $renewal_amount, 
                $vat, 
                $total_amount, 
                $subscription_revenue, 
                $sub_id
            );
            
            if ($stmt->execute()) {
                $updated_count++;
            } else {
                $errors[] = "ID $sub_id: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    
    if ($updated_count > 0) {
        $_SESSION['success'] = "$updated_count adet abonelik başarıyla güncellendi!";
    }
    if (!empty($errors)) {
        $_SESSION['error'] = implode(", ", $errors);
    }
    
    header('Location: subscriptions.php');
    exit();
}

// Yenilendi durumundaki ve geliri sıfır olan kayıtları çek
$query = "SELECT s.id, s.item_name, s.item_type, s.item_detail, s.cycle, 
          s.renewal_date, s.renewal_amount, s.total_amount, s.subscription_revenue,
          c.name as customer_name
          FROM subscriptions s
          LEFT JOIN customers c ON s.customer_id = c.id
          WHERE s.status = 'Yenilendi' 
          AND (s.subscription_revenue = 0 OR s.subscription_revenue IS NULL)
          ORDER BY s.id";

$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Eski Abonelikleri Düzelt - CRM</title>
</head>
<body class="center-page">
    <div class="center-container" style="max-width: 1200px;">
        <h1 style="font-size: 20px; margin-bottom: 6px;">Eski Abonelikleri Düzelt</h1>
        <p class="subtitle">Yenileme geliri sıfır olan kayıtlar için tutarları girin</p>

        <?php if ($result->num_rows > 0): ?>
            <div class="alert alert-warning">
                <strong>Dikkat:</strong>
                <?php echo $result->num_rows; ?> adet kayıt bulundu.
                Her abonelik için <strong>Yenileme Miktarı</strong> girin.
                KDV, Toplam ve Gelir otomatik hesaplanacak.
            </div>

            <form method="POST" onsubmit="return confirm('<?php echo $result->num_rows; ?> adet kayıt güncellenecek. Devam etmek istiyor musunuz?');">
                <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th style="width: 150px;">Müşteri</th>
                            <th style="width: 150px;">Ürün/Hizmet</th>
                            <th style="width: 60px;">Döngü</th>
                            <th style="width: 120px;">Yenileme Miktarı (₺)</th>
                            <th style="width: 100px;">KDV (%20)</th>
                            <th style="width: 100px;">Toplam</th>
                            <th style="width: 120px;">Abonelik Geliri</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo $row['id']; ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($row['customer_name']); ?><br>
                                <small style="color: var(--text-secondary);"><?php echo $row['item_type'] === 'product' ? 'Ürün' : 'SIM'; ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['item_name']); ?></strong><br>
                                <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($row['item_detail']); ?></small>
                            </td>
                            <td><strong><?php echo $row['cycle']; ?>.</strong></td>
                            <td>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    name="subscriptions[<?php echo $row['id']; ?>][renewal_amount]" 
                                    id="renewal_<?php echo $row['id']; ?>"
                                    placeholder="0.00"
                                    onkeyup="calculate(<?php echo $row['id']; ?>)"
                                    onchange="calculate(<?php echo $row['id']; ?>)"
                                    required
                                >
                                <div class="calc-info">Örn: 5000</div>
                            </td>
                            <td>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    id="vat_<?php echo $row['id']; ?>" 
                                    readonly
                                    placeholder="0.00"
                                >
                                <div class="calc-info">Otomatik</div>
                            </td>
                            <td>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    id="total_<?php echo $row['id']; ?>" 
                                    readonly
                                    placeholder="0.00"
                                >
                                <div class="calc-info">Otomatik</div>
                            </td>
                            <td>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    id="revenue_<?php echo $row['id']; ?>"
                                    readonly
                                    placeholder="0.00"
                                    style="font-weight: 700;"
                                >
                                <div class="calc-info" style="color: var(--success);">%20 gelir</div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                </div>

                <button type="submit" name="update_subscriptions" class="btn btn-primary" style="width: 100%; margin-top: 16px;">
                    <?php echo icon('check'); ?> Tüm Kayıtları Güncelle
                </button>

                <div class="actions">
                    <a href="subscriptions.php" class="btn btn-secondary">Aboneliklere Dön</a>
                </div>
            </form>

        <?php else: ?>
            <div class="alert alert-success">
                <strong>Harika!</strong> Güncellenecek kayıt bulunamadı. Tüm kayıtlar güncel!
            </div>
            <div class="actions">
                <a href="subscriptions.php" class="btn btn-primary">Aboneliklere Dön</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function calculate(id) {
            const renewalAmount = parseFloat(document.getElementById('renewal_' + id).value) || 0;
            
            // KDV hesapla (%20)
            const vat = renewalAmount * 0.20;
            document.getElementById('vat_' + id).value = vat.toFixed(2);
            
            // Toplam hesapla
            const total = renewalAmount + vat;
            document.getElementById('total_' + id).value = total.toFixed(2);
            
            // Gelir hesapla (%20)
            const revenue = renewalAmount * 0.20;
            document.getElementById('revenue_' + id).value = revenue.toFixed(2);
        }
    </script>
</body>
</html>