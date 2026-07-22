<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

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
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Eski Abonelikleri Düzelt - CRM</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .info-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }
        .warning-box {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background: #667eea;
            color: white;
            padding: 15px 10px;
            text-align: left;
            font-weight: 600;
        }
        td {
            padding: 15px 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        tr:hover {
            background: #f8f9fa;
        }
        input[type="number"] {
            width: 100%;
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }
        input[type="number"]:focus {
            outline: none;
            border-color: #667eea;
        }
        input[readonly] {
            background: #f8f9fa;
            color: #666;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-success {
            background: #28a745;
            color: white;
            width: 100%;
            font-size: 18px;
            padding: 15px;
        }
        .btn-success:hover {
            background: #218838;
        }
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .calc-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Eski Abonelikleri Düzelt</h1>
        <p class="subtitle">Yenileme geliri sıfır olan kayıtlar için tutarları girin</p>

        <?php if ($result->num_rows > 0): ?>
            <div class="warning-box">
                <strong>⚠️ DİKKAT:</strong> 
                <?php echo $result->num_rows; ?> adet kayıt bulundu. 
                Her abonelik için <strong>Yenileme Miktarı</strong> girin. 
                KDV, Toplam ve Gelir otomatik hesaplanacak.
            </div>

            <form method="POST" onsubmit="return confirm('<?php echo $result->num_rows; ?> adet kayıt güncellenecek. Devam etmek istiyor musunuz?');">
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
                                <small style="color: #666;"><?php echo $row['item_type'] === 'product' ? '📦 Ürün' : '📱 SIM'; ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['item_name']); ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($row['item_detail']); ?></small>
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
                                    style="background: #f0fff0; font-weight: bold;"
                                >
                                <div class="calc-info" style="color: #28a745;">%20 gelir</div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <button type="submit" name="update_subscriptions" class="btn btn-success">
                    💾 Tüm Kayıtları Güncelle
                </button>

                <div class="actions">
                    <a href="subscriptions.php" class="btn btn-secondary">← Aboneliklere Dön</a>
                    <a href="test-subscription-debug.php" class="btn btn-primary">🔍 Debug Sayfası</a>
                </div>
            </form>

        <?php else: ?>
            <div class="info-box">
                ✅ <strong>Harika!</strong> Güncellenecek kayıt bulunamadı. Tüm kayıtlar güncel!
            </div>
            <div class="actions">
                <a href="subscriptions.php" class="btn btn-primary">← Aboneliklere Dön</a>
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