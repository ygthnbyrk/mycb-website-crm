<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require_once 'config.php';

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Oturum bulunamadı. Lütfen giriş yapın.');
    }

    if (!isset($_POST['excel_data']) || empty($_POST['excel_data'])) {
        throw new Exception('Excel verisi bulunamadı. Lütfen dosyayı tekrar yükleyin.');
    }

    $errors_found = array();
    $warnings_found = array();
    $sales_data = array();
    $row_number = 0;

    // JSON verisini çöz
    $excel_data = json_decode($_POST['excel_data'], true);
    
    if ($excel_data === null) {
        throw new Exception('Excel verisi okunamadı. JSON hatası: ' . json_last_error_msg());
    }

    if (empty($excel_data)) {
        throw new Exception('Excel dosyası boş veya başlık satırı bulunamadı.');
    }

    // İlk satır başlıklar
    $headers = $excel_data[0];
    
    // Başlıkları kontrol et - en az bir başlık olmalı ve dizi olmalı
    if (!is_array($headers) || count($headers) === 0) {
        throw new Exception('Excel dosyası geçersiz. Başlık satırı eksik veya hatalı.');
    }
    
    // Başlık sayısı 7'den az ise, eksik kolonları ekle (boş string olarak)
    while (count($headers) < 7) {
        $headers[] = '';
    }
    
    // Beklenen başlıklar (kontrol için)
    $expected_headers = ['musteri_adi', 'imei', 'sim_telefon', 'plaka', 'satis_tarihi', 'urun_fiyati', 'sim_fiyati'];
    
    // Başlıkların doğru olup olmadığını kontrol et (küçük/büyük harf duyarsız)
    $header_check_passed = true;
    foreach ($expected_headers as $index => $expected) {
        if (!isset($headers[$index]) || strtolower(trim($headers[$index])) !== $expected) {
            $header_check_passed = false;
            break;
        }
    }
    
    if (!$header_check_passed) {
        $warning_msg = "⚠️ Başlık satırı tam eşleşmedi, ancak devam ediliyor. Beklenen sıra: " . implode(', ', $expected_headers);
        $warnings_found[] = $warning_msg;
    }
    
    // Verileri oku (ikinci satırdan başla)
    for ($i = 1; $i < count($excel_data); $i++) {
        $data = $excel_data[$i];
        $row_number = $i; // Excel'deki satır numarası (başlıkla birlikte)
        $row_errors = array();
        $row_warnings = array();
        
        // Eksik kolonları tamamla (eğer satırda 7'den az kolon varsa)
        while (count($data) < 7) {
            $data[] = '';
        }
        
        // Boş satırı atla
        $is_empty = true;
        foreach ($data as $cell) {
            if (!empty(trim($cell))) {
                $is_empty = false;
                break;
            }
        }
        if ($is_empty) {
            continue;
        }
        
        // Veriyi parse et
        $musteri_adi = isset($data[0]) ? trim($data[0]) : '';
        $imei = isset($data[1]) ? trim($data[1]) : '';
        $sim_telefon = isset($data[2]) ? trim($data[2]) : '';
        $plaka = isset($data[3]) ? trim($data[3]) : '';
        $satis_tarihi = isset($data[4]) ? trim($data[4]) : '';
        $urun_fiyati = isset($data[5]) ? trim($data[5]) : '';
        $sim_fiyati = isset($data[6]) ? trim($data[6]) : '';
        
        // 1. Müşteri kontrolü
        $customer_id = null;
        if (empty($musteri_adi)) {
            $row_errors[] = "Müşteri adı boş olamaz";
        } else {
            $stmt = $conn->prepare("SELECT id FROM customers WHERE name LIKE ?");
            if ($stmt === false) {
                throw new Exception("Veritabanı hatası: " . $conn->error);
            }
            $search = "%$musteri_adi%";
            $stmt->bind_param("s", $search);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $row_errors[] = "Müşteri bulunamadı: '$musteri_adi'";
            } elseif ($result->num_rows > 1) {
                $row_warnings[] = "Birden fazla müşteri bulundu, ilki seçilecek";
                $customer = $result->fetch_assoc();
                $customer_id = $customer['id'];
            } else {
                $customer = $result->fetch_assoc();
                $customer_id = $customer['id'];
            }
            $stmt->close();
        }
        
        // 2. IMEI kontrolü
        $product_id = null;
        $product_name = '';
        if (!empty($imei)) {
            $stmt = $conn->prepare("SELECT id, model, status FROM products WHERE imei_number = ?");
            if ($stmt === false) {
                throw new Exception("Veritabanı hatası: " . $conn->error);
            }
            $stmt->bind_param("s", $imei);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $row_errors[] = "IMEI bulunamadı: '$imei'";
            } else {
                $product = $result->fetch_assoc();
                if ($product['status'] !== 'Stokta') {
                    $row_errors[] = "Ürün stokta değil (Durum: {$product['status']})";
                } else {
                    $product_id = $product['id'];
                    $product_name = $product['model'];
                }
            }
            $stmt->close();
        }
        
        // 3. SIM kontrolü
        $simcard_id = null;
        $sim_name = '';
        $sim_phone_display = '';
        if (!empty($sim_telefon)) {
            // Telefon numarasını normalize et (sadece rakamlar)
            $sim_normalized = preg_replace('/[^0-9]/', '', $sim_telefon);
            
            // Başındaki 0'ı kaldır
            if (substr($sim_normalized, 0, 1) === '0') {
                $sim_normalized = substr($sim_normalized, 1);
            }
            
            // Veritabanında arama yap (normalize edilmiş hali ile)
            $stmt = $conn->prepare("SELECT id, company, operator, status, phone_number 
                                    FROM simcards 
                                    WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone_number, ' ', ''), '-', ''), '(', ''), ')', '') LIKE ?");
            if ($stmt === false) {
                throw new Exception("Veritabanı hatası: " . $conn->error);
            }
            
            // Hem 0'lı hem 0'sız aramak için
            $search_pattern = "%$sim_normalized%";
            $stmt->bind_param("s", $search_pattern);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $row_errors[] = "SIM bulunamadı: '$sim_telefon'";
            } elseif ($result->num_rows > 1) {
                $row_warnings[] = "Birden fazla SIM bulundu, ilki seçilecek";
                $simcard = $result->fetch_assoc();
                if ($simcard['status'] !== 'Stokta') {
                    $row_errors[] = "SIM stokta değil (Durum: {$simcard['status']})";
                } else {
                    $simcard_id = $simcard['id'];
                    $sim_name = $simcard['company'] . ' - ' . $simcard['operator'];
                    $sim_phone_display = $simcard['phone_number'];
                }
            } else {
                $simcard = $result->fetch_assoc();
                if ($simcard['status'] !== 'Stokta') {
                    $row_errors[] = "SIM stokta değil (Durum: {$simcard['status']})";
                } else {
                    $simcard_id = $simcard['id'];
                    $sim_name = $simcard['company'] . ' - ' . $simcard['operator'];
                    $sim_phone_display = $simcard['phone_number'];
                }
            }
            $stmt->close();
        }
        
        // 4. En az biri dolu olmalı
        if (empty($imei) && empty($sim_telefon)) {
            $row_errors[] = "En az IMEI veya SIM Telefon dolu olmalı";
        }
        
// 4. Tarih kontrolü - Türk formatı öncelikli
$parsed_date = null;
if (empty($satis_tarihi)) {
    $row_errors[] = "Satış tarihi boş olamaz";
} else {
    $satis_tarihi = trim($satis_tarihi);
    $date_parsed = false;
    
    // Önce manuel parse dene (Türk formatı: GG.AA.YYYY veya G.A.YYYY)
    if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})$/', $satis_tarihi, $matches)) {
        $gun = intval($matches[1]);
        $ay = intval($matches[2]);
        $yil = intval($matches[3]);
        
        // 2 haneli yılı 4 haneliye çevir
        if ($yil < 100) {
            $yil = ($yil > 50) ? 1900 + $yil : 2000 + $yil;
        }
        
        // Geçerli tarih mi kontrol et
        if ($gun >= 1 && $gun <= 31 && $ay >= 1 && $ay <= 12 && $yil >= 2000 && $yil <= 2100) {
            if (checkdate($ay, $gun, $yil)) {
                $parsed_date = sprintf('%04d-%02d-%02d', $yil, $ay, $gun);
                $date_parsed = true;
            }
        }
    }
    
    // YYYY-MM-DD formatı (ISO)
    if (!$date_parsed && preg_match('/^(\d{4})[.\/-](\d{1,2})[.\/-](\d{1,2})$/', $satis_tarihi, $matches)) {
        $yil = intval($matches[1]);
        $ay = intval($matches[2]);
        $gun = intval($matches[3]);
        
        if (checkdate($ay, $gun, $yil)) {
            $parsed_date = sprintf('%04d-%02d-%02d', $yil, $ay, $gun);
            $date_parsed = true;
        }
    }
    
    // Excel sayısal tarih formatı (örn: 45352)
    if (!$date_parsed && is_numeric($satis_tarihi)) {
        $excel_date = intval($satis_tarihi);
        if ($excel_date > 0 && $excel_date < 100000) {
            $unix_date = ($excel_date - 25569) * 86400;
            $parsed_date = date('Y-m-d', $unix_date);
            $date_parsed = true;
        }
    }
    
    if (!$date_parsed) {
        $row_errors[] = "Tarih formatı hatalı: '$satis_tarihi' (Desteklenen: GG.AA.YYYY)";
    }
}
        
        // 6. Fiyat kontrolü
        $urun_fiyati_float = 0;
        $sim_fiyati_float = 0;
        
        if (!empty($urun_fiyati)) {
            $urun_fiyati = str_replace(',', '.', $urun_fiyati);
            if (!is_numeric($urun_fiyati)) {
                $row_errors[] = "Ürün fiyatı sayısal değil: '$urun_fiyati'";
            } else {
                $urun_fiyati_float = floatval($urun_fiyati);
                if ($urun_fiyati_float < 0) {
                    $row_errors[] = "Ürün fiyatı negatif olamaz";
                }
            }
        }
        
        if (!empty($sim_fiyati)) {
            $sim_fiyati = str_replace(',', '.', $sim_fiyati);
            if (!is_numeric($sim_fiyati)) {
                $row_errors[] = "SIM fiyatı sayısal değil: '$sim_fiyati'";
            } else {
                $sim_fiyati_float = floatval($sim_fiyati);
                if ($sim_fiyati_float < 0) {
                    $row_errors[] = "SIM fiyatı negatif olamaz";
                }
            }
        }
        
        // Mantıksal kontroller
        if (!empty($imei) && empty($urun_fiyati)) {
            $row_warnings[] = "IMEI var ama ürün fiyatı yok - fiyat 0 olarak kaydedilecek";
        }
        
        if (!empty($sim_telefon) && empty($sim_fiyati)) {
            $row_warnings[] = "SIM var ama SIM fiyatı yok - fiyat 0 olarak kaydedilecek";
        }
        
        // Toplam fiyat hesapla
        $total_price = $urun_fiyati_float + $sim_fiyati_float;
        
        // Satış verisini kaydet
        $sales_data[] = array(
            'row_number' => $row_number + 1,
            'musteri_adi' => $musteri_adi,
            'customer_id' => $customer_id,
            'imei' => $imei,
            'product_id' => $product_id,
            'product_name' => $product_name,
            'sim_telefon' => $sim_telefon,
            'sim_telefon_display' => $sim_phone_display ? $sim_phone_display : $sim_telefon,
            'simcard_id' => $simcard_id,
            'sim_name' => $sim_name,
            'plaka' => $plaka,
            'satis_tarihi' => $satis_tarihi,
            'parsed_date' => $parsed_date,
            'urun_fiyati' => $urun_fiyati_float,
            'sim_fiyati' => $sim_fiyati_float,
            'total_price' => $total_price,
            'errors' => $row_errors,
            'warnings' => $row_warnings,
            'has_error' => count($row_errors) > 0
        );
        
        foreach ($row_errors as $error) {
            $errors_found[] = "Satır " . ($row_number + 1) . ": " . $error;
        }
        foreach ($row_warnings as $warning) {
            $warnings_found[] = "Satır " . ($row_number + 1) . ": " . $warning;
        }
    }
    
    $total_rows = count($sales_data);
    $valid_rows = 0;
    $error_rows = 0;
    
    foreach ($sales_data as $sale) {
        if ($sale['has_error']) {
            $error_rows++;
        } else {
            $valid_rows++;
        }
    }
    
    $_SESSION['bulk_sales_data'] = array_filter($sales_data, function($sale) {
        return !$sale['has_error'];
    });

} catch (Exception $e) {
    $error_message = $e->getMessage();
    $error_file = $e->getFile();
    $error_line = $e->getLine();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Toplu Satış Önizleme</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f7fa; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; background: white; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 40px; }
        h1 { color: #667eea; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #f0f0f0; }
        .error-box { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; border-left: 4px solid #dc3545; margin: 20px 0; }
        .alert { padding: 15px 20px; border-radius: 8px; margin: 20px 0; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .alert-warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        .stats-bar { display: flex; gap: 20px; margin: 30px 0; }
        .stat-box { flex: 1; background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; border: 2px solid #dee2e6; }
        .stat-box h3 { font-size: 32px; margin-bottom: 5px; }
        .stat-box.success { border-color: #28a745; }
        .stat-box.danger { border-color: #dc3545; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 13px; }
        th, td { padding: 10px 8px; border: 1px solid #ddd; text-align: left; }
        th { background: #667eea; color: white; font-weight: 600; position: sticky; top: 0; }
        tr.success { background: #d4edda; }
        tr.error { background: #f8d7da; }
        tr.warning { background: #fff3cd; }
        .error-list, .warning-list { margin: 0; padding-left: 20px; font-size: 12px; }
        .error-list li { color: #721c24; }
        .warning-list li { color: #856404; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 15px; text-decoration: none; display: inline-block; margin: 5px; transition: all 0.3s; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover:not(:disabled) { background: #218838; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .actions { text-align: center; margin-top: 30px; padding-top: 30px; border-top: 2px solid #f0f0f0; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php if (isset($error_message)): ?>
        <div class="container">
            <h1>❌ Hata</h1>
            <div class="error-box">
                <h3>Bir hata oluştu:</h3>
                <p><strong><?php echo htmlspecialchars($error_message); ?></strong></p>
                <p style="margin-top: 10px; font-size: 12px; color: #666;">
                    Dosya: <?php echo htmlspecialchars($error_file); ?><br>
                    Satır: <?php echo $error_line; ?>
                </p>
            </div>
            <div class="actions">
                <a href="bulk-sales-upload.php" class="btn btn-secondary">← Geri Dön</a>
            </div>
        </div>
    <?php else: ?>
    <div class="container">
        <h1>🔍 Toplu Satış Önizleme</h1>
        <p class="subtitle">Excel verileriniz kontrol edildi, aşağıda sonuçları görüntüleyin</p>
        <div class="stats-bar">
            <div class="stat-box">
                <h3><?php echo $total_rows; ?></h3>
                <p>Toplam Satır</p>
            </div>
            <div class="stat-box success">
                <h3 style="color: #28a745;"><?php echo $valid_rows; ?></h3>
                <p>Geçerli Satır</p>
            </div>
            <div class="stat-box danger">
                <h3 style="color: #dc3545;"><?php echo $error_rows; ?></h3>
                <p>Hatalı Satır</p>
            </div>
        </div>
        <?php if ($error_rows === 0): ?>
            <div class="alert alert-success">
                <strong>✅ Tebrikler!</strong> Tüm satırlar geçerli. Devam ederseniz <?php echo $valid_rows; ?> adet satış kaydedilecek.
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <strong>⚠️ Dikkat!</strong> <?php echo $error_rows; ?> satırda hata bulundu. Hatalı satırlar işlenmeyecek, sadece geçerli <?php echo $valid_rows; ?> satır kaydedilecek.
            </div>
        <?php endif; ?>
        <?php if (count($warnings_found) > 0): ?>
            <div class="alert alert-warning">
                <strong>⚠️ Uyarılar:</strong>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <?php foreach (array_unique($warnings_found) as $warning): ?>
                        <li><?php echo htmlspecialchars($warning); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <h3 style="margin: 30px 0 15px 0; color: #667eea;">📋 Satış Detayları</h3>
        <div style="overflow-x: auto; max-height: 600px; overflow-y: auto;">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th style="width: 80px;">Durum</th>
                        <th>Müşteri</th>
                        <th>Ürün (IMEI)</th>
                        <th>Ürün Fiyatı</th>
                        <th>SIM (Telefon)</th>
                        <th>SIM Fiyatı</th>
                        <th>Toplam</th>
                        <th>Plaka</th>
                        <th>Tarih</th>
                        <th>Hatalar/Uyarılar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales_data as $sale): ?>
                        <tr class="<?php echo $sale['has_error'] ? 'error' : (count($sale['warnings']) > 0 ? 'warning' : 'success'); ?>">
                            <td><strong><?php echo $sale['row_number']; ?></strong></td>
                            <td>
                                <?php if ($sale['has_error']): ?>
                                    <span class="badge badge-danger">❌ Hatalı</span>
                                <?php else: ?>
                                    <span class="badge badge-success">✅ Geçerli</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($sale['musteri_adi']); ?></td>
                            <td>
                                <?php if ($sale['product_name']): ?>
                                    <strong><?php echo htmlspecialchars($sale['product_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($sale['imei']); ?></small>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($sale['urun_fiyati'])): ?>
                                    <strong style="color: #667eea;">₺<?php echo number_format($sale['urun_fiyati'], 2); ?></strong>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($sale['sim_name']): ?>
                                    <strong><?php echo htmlspecialchars($sale['sim_name']); ?></strong><br>
                                    <small style="color: #28a745;">✓ <?php echo htmlspecialchars($sale['sim_telefon_display']); ?></small>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($sale['sim_fiyati'])): ?>
                                    <strong style="color: #28a745;">₺<?php echo number_format($sale['sim_fiyati'], 2); ?></strong>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><strong style="color: #333; font-size: 16px;">₺<?php echo number_format($sale['total_price'], 2); ?></strong></td>
                            <td><?php echo htmlspecialchars($sale['plaka'] ? $sale['plaka'] : '-'); ?></td>
                            <td><?php echo htmlspecialchars($sale['satis_tarihi']); ?></td>
                            <td>
                                <?php if (count($sale['errors']) > 0): ?>
                                    <ul class="error-list">
                                        <?php foreach ($sale['errors'] as $error): ?>
                                            <li>❌ <?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <?php if (count($sale['warnings']) > 0): ?>
                                    <ul class="warning-list">
                                        <?php foreach ($sale['warnings'] as $warning): ?>
                                            <li>⚠️ <?php echo htmlspecialchars($warning); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <?php if (count($sale['errors']) == 0 && count($sale['warnings']) == 0): ?>
                                    <span style="color: #28a745;">✓ Sorun yok</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="actions">
            <?php if ($valid_rows > 0): ?>
                <form method="POST" action="bulk-sales-process.php" style="display: inline;">
                    <button type="submit" class="btn btn-success" onclick="return confirm('<?php echo $valid_rows; ?> adet satış kaydedilecek. Devam etmek istiyor musunuz?');">
                        ✅ Geçerli Satışları Kaydet (<?php echo $valid_rows; ?> adet)
                    </button>
                </form>
            <?php else: ?>
                <button type="button" class="btn btn-success" disabled>
                    ❌ Geçerli Satış Bulunamadı
                </button>
            <?php endif; ?>
            <a href="bulk-sales-upload.php" class="btn btn-secondary">← Yeni Dosya Yükle</a>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>