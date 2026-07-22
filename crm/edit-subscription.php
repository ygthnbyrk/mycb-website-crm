<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$subscription_id = intval($_GET['id'] ?? 0);

if ($subscription_id === 0) {
    header('Location: subscriptions.php');
    exit();
}

// Abonelik bilgilerini çek
$stmt = $conn->prepare("SELECT s.*, c.name as customer_name, c.tax_number 
                        FROM subscriptions s 
                        LEFT JOIN customers c ON s.customer_id = c.id 
                        WHERE s.id = ?");
$stmt->bind_param("i", $subscription_id);
$stmt->execute();
$subscription = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$subscription) {
    $_SESSION['error'] = 'Abonelik bulunamadı.';
    header('Location: subscriptions.php');
    exit();
}

// Yenileme türünü belirle
$renewal_type = 'Cihaz';
if ($subscription['item_type'] === 'product') {
    $renewal_type = 'Cihaz';
} else {
    $renewal_type = 'Sim Kart';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Abonelik Düzenle - CRM</title>
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
            padding: 30px;
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
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
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
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group input[readonly] {
            background: #f8f9fa;
            color: #666;
        }
        .required { color: red; }
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Abonelik Düzenle</h1>
            <a href="subscriptions.php" class="close-btn">✖ Kapat</a>
        </div>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Abonelik Bilgileri -->
        <div class="info-grid">
            <div class="info-item">
                <label>Müşteri Adı</label>
                <p><?php echo htmlspecialchars($subscription['customer_name']); ?></p>
            </div>
            <div class="info-item">
                <label>Ürün/Hizmet</label>
                <p><?php echo htmlspecialchars($subscription['item_name']); ?></p>
            </div>
            <div class="info-item">
                <label>IMEI / Telefon</label>
                <p><?php echo htmlspecialchars($subscription['item_detail']); ?></p>
            </div>
            <div class="info-item">
                <label>Yenileme Tipi</label>
                <p><?php echo $renewal_type; ?></p>
            </div>
            <div class="info-item">
                <label>Mevcut Durum</label>
                <p><strong><?php echo $subscription['status']; ?></strong></p>
            </div>
            <div class="info-item">
                <label>Abonelik Döngüsü</label>
                <p><strong><?php echo $subscription['cycle']; ?>. Döngü</strong></p>
            </div>
            <div class="info-item">
                <label>Sonraki Yenileme Tarihi</label>
                <p><?php echo date('d.m.Y', strtotime($subscription['renewal_date'])); ?></p>
            </div>
        </div>

        <!-- Güncelleme Formu -->
        <form method="POST" action="update-subscription.php" onsubmit="return validateForm()">
            <input type="hidden" name="subscription_id" value="<?php echo $subscription['id']; ?>">
            <input type="hidden" name="current_cycle" value="<?php echo $subscription['cycle']; ?>">
            <input type="hidden" name="item_type" value="<?php echo $subscription['item_type']; ?>">

            <div class="form-section">
                <h2>🔄 Durum Güncelle</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Durum Güncelle <span class="required">*</span></label>
                        <select name="status" id="status" required onchange="toggleFields()">
                            <option value="">Seçiniz</option>
                            <option value="Yenilendi">Yenilendi</option>
                            <option value="İptal">İptal Edildi</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Abonelik Döngüsü</label>
                        <input type="text" value="<?php echo $subscription['cycle']; ?>. Döngü" readonly>
                    </div>
                </div>
            </div>

            <div id="renewal-fields" style="display: none;">
                <!-- ÜRÜN YENİLEME -->
                <div id="product-renewal" class="form-section" style="display: none;">
                    <h2>💵 Cihaz Yenileme</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Yenileme Miktarı (₺) <span class="required">*</span></label>
                            <input type="number" step="0.01" name="renewal_amount" id="renewal_amount_product" placeholder="0.00" onkeyup="calculateProductTotals()" onchange="calculateProductTotals()">
                        </div>
                        <div class="form-group">
                            <label>KDV (%20)</label>
                            <input type="number" step="0.01" name="vat" id="vat_product" readonly>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Toplam</label>
                            <input type="number" step="0.01" name="total_amount" id="total_amount_product" readonly>
                        </div>
                        <div class="form-group">
                            <label>Abonelik Geliri (%20)</label>
                            <input type="number" step="0.01" name="subscription_revenue" id="subscription_revenue_product" readonly>
                        </div>
                    </div>
                </div>

                <!-- SIM KART YENİLEME -->
                <div id="simcard-renewal" class="form-section" style="display: none;">
                    <h2 style="color: #28a745;">💵 SIM Kart Yenileme</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Yenileme Miktarı (₺) <span class="required">*</span></label>
                            <input type="number" step="0.01" name="renewal_amount" id="renewal_amount_sim" placeholder="0.00" onkeyup="calculateSimTotals()" onchange="calculateSimTotals()">
                        </div>
                        <div class="form-group">
                            <label>KDV (%20)</label>
                            <input type="number" step="0.01" name="vat" id="vat_sim" readonly>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Toplam</label>
                            <input type="number" step="0.01" name="total_amount" id="total_amount_sim" readonly>
                        </div>
                    </div>

                    <h2 style="color: #dc8700; margin-top: 20px;">💰 SIM Kart Maliyet</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Yenileme Maliyeti (₺) <span class="required">*</span></label>
                            <input type="number" step="0.01" name="sim_cost" id="sim_cost" placeholder="0.00" onkeyup="calculateSimTotals()" onchange="calculateSimTotals()">
                        </div>
                        <div class="form-group">
                            <label>KDV (%20)</label>
                            <input type="number" step="0.01" id="sim_cost_vat" readonly>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Toplam Maliyet</label>
                            <input type="number" step="0.01" id="sim_total_cost" readonly>
                        </div>
                    </div>

                    <h2 style="color: #7952d3; margin-top: 20px;">📊 Kâr Hesaplama</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label style="font-size: 16px; color: #7952d3;">SIM Kart Kârı</label>
                            <input type="number" step="0.01" id="sim_profit_display" readonly style="font-size: 18px; font-weight: bold; background: #f0f0ff;">
                            <small style="color: #666;">Hesaplama: Yenileme Miktarı - Yenileme Maliyeti</small>
                        </div>
                        <div class="form-group">
                            <label style="font-size: 16px; color: #7952d3;">Abonelik Geliri</label>
                            <input type="number" step="0.01" name="subscription_revenue" id="subscription_revenue_sim" readonly style="font-size: 18px; font-weight: bold; background: #f0fff0;">
                            <small style="color: #666;">SIM Kart Kârı ile aynı</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2>📝 Notlar</h2>
                <div class="form-group">
                    <label>Yenileme veya iptal ile ilgili notlar</label>
                    <textarea name="notes" rows="4" placeholder="Yenileme veya iptal ile ilgili notlar..."><?php echo htmlspecialchars($subscription['notes'] ?? ''); ?></textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-success">💾 Güncelle</button>
        </form>
    </div>

    <script>
        const itemType = '<?php echo $subscription['item_type']; ?>';
        
        function toggleFields() {
            const status = document.getElementById('status').value;
            const renewalFields = document.getElementById('renewal-fields');
            const productRenewal = document.getElementById('product-renewal');
            const simcardRenewal = document.getElementById('simcard-renewal');
            
            if (status === 'Yenilendi') {
                renewalFields.style.display = 'block';
                
                if (itemType === 'product') {
                    productRenewal.style.display = 'block';
                    simcardRenewal.style.display = 'none';
                    document.getElementById('renewal_amount_product').required = true;
                    document.getElementById('renewal_amount_sim').required = false;
                    document.getElementById('renewal_amount_sim').disabled = true;
                    document.getElementById('vat_sim').disabled = true;
                    document.getElementById('total_amount_sim').disabled = true;
                    document.getElementById('subscription_revenue_sim').disabled = true;
                    document.getElementById('sim_cost').disabled = true;
                } else {
                    productRenewal.style.display = 'none';
                    simcardRenewal.style.display = 'block';
                    document.getElementById('renewal_amount_product').required = false;
                    document.getElementById('renewal_amount_product').disabled = true;
                    document.getElementById('vat_product').disabled = true;
                    document.getElementById('total_amount_product').disabled = true;
                    document.getElementById('subscription_revenue_product').disabled = true;
                    document.getElementById('renewal_amount_sim').required = true;
                    document.getElementById('sim_cost').required = true;
                    document.getElementById('renewal_amount_sim').disabled = false;
                    document.getElementById('vat_sim').disabled = false;
                    document.getElementById('total_amount_sim').disabled = false;
                    document.getElementById('subscription_revenue_sim').disabled = false;
                    document.getElementById('sim_cost').disabled = false;
                }
            } else {
                renewalFields.style.display = 'none';
                document.getElementById('renewal_amount_product').required = false;
                document.getElementById('renewal_amount_sim').required = false;
                document.getElementById('sim_cost').required = false;
            }
        }

        // ÜRÜN hesaplama
        function calculateProductTotals() {
            const renewalAmount = parseFloat(document.getElementById('renewal_amount_product').value) || 0;
            
            // KDV hesapla (%20)
            const vat = renewalAmount * 0.20;
            document.getElementById('vat_product').value = vat.toFixed(2);
            
            // Toplam hesapla
            const total = renewalAmount + vat;
            document.getElementById('total_amount_product').value = total.toFixed(2);
            
            // Abonelik geliri hesapla (%20)
            const revenue = renewalAmount * 0.20;
            document.getElementById('subscription_revenue_product').value = revenue.toFixed(2);
        }

        // SIM KART hesaplama
        function calculateSimTotals() {
            const renewalAmount = parseFloat(document.getElementById('renewal_amount_sim').value) || 0;
            const simCost = parseFloat(document.getElementById('sim_cost').value) || 0;
            
            // Yenileme - KDV hesapla
            const vat = renewalAmount * 0.20;
            document.getElementById('vat_sim').value = vat.toFixed(2);
            
            // Yenileme - Toplam hesapla
            const total = renewalAmount + vat;
            document.getElementById('total_amount_sim').value = total.toFixed(2);
            
            // Maliyet - KDV hesapla
            const simCostVat = simCost * 0.20;
            document.getElementById('sim_cost_vat').value = simCostVat.toFixed(2);
            
            // Maliyet - Toplam hesapla
            const simTotalCost = simCost + simCostVat;
            document.getElementById('sim_total_cost').value = simTotalCost.toFixed(2);
            
            // KÂR hesapla (KDV HARİÇ)
            const profit = renewalAmount - simCost;
            document.getElementById('sim_profit_display').value = profit.toFixed(2);
            
            // Abonelik geliri = Kâr
            document.getElementById('subscription_revenue_sim').value = profit.toFixed(2);
        }

        function validateForm() {
            const status = document.getElementById('status').value;
            
            if (status === 'Yenilendi') {
                if (itemType === 'product') {
                    const renewalAmount = parseFloat(document.getElementById('renewal_amount_product').value) || 0;
                    if (renewalAmount <= 0) {
                        alert('Lütfen Yenileme Miktarını giriniz!');
                        document.getElementById('renewal_amount_product').focus();
                        return false;
                    }
                } else {
                    const renewalAmount = parseFloat(document.getElementById('renewal_amount_sim').value) || 0;
                    const simCost = parseFloat(document.getElementById('sim_cost').value) || 0;
                    if (renewalAmount <= 0) {
                        alert('Lütfen Yenileme Miktarını giriniz!');
                        document.getElementById('renewal_amount_sim').focus();
                        return false;
                    }
                    if (simCost <= 0) {
                        alert('Lütfen Yenileme Maliyetini giriniz!');
                        document.getElementById('sim_cost').focus();
                        return false;
                    }
                }
            }
            
            return true;
        }
    </script>
</body>
</html>