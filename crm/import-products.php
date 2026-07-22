<?php
require_once 'config.php';
require_once 'SimpleXLSX.php';

use Shuchkin\SimpleXLSX; // ÖNEMLİ: SimpleXLSX namespaceli

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    $user_id = $_SESSION['user_id'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'Dosya yüklenirken hata oluştu.';
        header('Location: products.php');
        exit;
    }

    $allowedExtensions = ['xlsx', 'xls'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        $_SESSION['error'] = 'Sadece Excel dosyaları yüklenebilir (.xlsx, .xls)';
        header('Location: products.php');
        exit;
    }

    try {
        // XLSX oku
        if ($xlsx = SimpleXLSX::parse($file['tmp_name'])) {
            $rows = $xlsx->rows();
            if (!$rows || count($rows) < 2) {
                $_SESSION['error'] = 'Dosyada veri bulunamadı.';
                header('Location: products.php'); exit;
            }

            // İlk satır başlık
            $header = array_map('strtolower', array_map('trim', $rows[0]));
            $expected = ['model','product_name','serial_number','imei_number','cost_price','vat','total_cost','category','description'];
            if ($header !== $expected) {
                $_SESSION['error'] = 'Başlıklar hatalı. Sıra şu olmalı: '.implode(', ', $expected);
                header('Location: products.php'); exit;
            }
            array_shift($rows); // başlığı at

            $inserted = 0;
            $updated  = 0;
            $skipped  = 0;
            $errors   = 0;

            // Hazır SQL’ler
            $check = $conn->prepare("SELECT id FROM products WHERE imei_number = ?");
            $ins = $conn->prepare("
                INSERT INTO products
                (model, product_name, serial_number, imei_number, cost_price, vat, total_cost, category, description, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?)
            ");
            $upd = $conn->prepare("
                UPDATE products
                SET model=?, product_name=?, serial_number=?, cost_price=?, vat=?, total_cost=?, category=?, description=?
                WHERE imei_number=?
            ");

            foreach ($rows as $row) {
                // Eksik hücreleri doldur
                $row = array_pad($row, 9, '');

                // Hücreleri kırp
                $model         = trim((string)$row[0]);
                $product_name  = trim((string)$row[1]);
                $serial_number = trim((string)$row[2]);
                $imei          = trim((string)$row[3]);
                $cost_price    = $row[4];
                $vat           = $row[5];
                $total_cost    = $row[6];
                $category      = trim((string)$row[7]);
                $description   = trim((string)$row[8]);

                // Tamamen boş satırları geç
                if ($model === '' && $product_name === '' && $imei === '') { $skipped++; continue; }

                // Zorunlu alanlar
                if ($model === '' || $product_name === '' || $imei === '' || $category === '') { $skipped++; continue; }

                // Sayıları normalize et (virgüllü gelirse noktaya çevir)
                $norm = function($v) {
                    if (is_string($v)) { $v = str_replace(['.', ','], ['.', '.'], $v); } // virgül/nokta karmaşası varsa
                    return is_numeric($v) ? (float)$v : 0.0;
                };
                $cost_price = $norm($cost_price);
                $vat        = $norm($vat);
                $total_cost = is_numeric($total_cost) ? (float)$total_cost : 0.0;

                if ($cost_price <= 0) { $skipped++; continue; }
                if ($total_cost <= 0) {
                    $total_cost = $cost_price + ($cost_price * $vat / 100.0);
                }

                try {
                    // IMEI var mı?
                    $check->bind_param("s", $imei);
                    $check->execute();
                    $res = $check->get_result();

                    $serial_or_null = strlen($serial_number) ? $serial_number : null;
                    $desc_or_null   = strlen($description) ? $description : null;

                    if ($res && $res->num_rows > 0) {
                        // Güncelle
                        $upd->bind_param(
                            "sssdddsss",
                            $model, $product_name, $serial_or_null, $cost_price, $vat, $total_cost, $category, $desc_or_null, $imei
                        );
                        $upd->execute();
                        $updated++;
                    } else {
                        // Ekle
                        $ins->bind_param(
                            "ssssdddssi",
                            $model, $product_name, $serial_or_null, $imei, $cost_price, $vat, $total_cost, $category, $desc_or_null, $user_id
                        );
                        $ins->execute();
                        $inserted++;
                    }
                } catch (Throwable $e) {
                    $errors++;
                    // error_log('Products import error: '.$e->getMessage());
                    continue;
                }
            }

            $msg = "✅ Yeni: $inserted, 🔁 Güncellenen: $updated, ⏭ Atlanan: $skipped";
            if ($errors > 0) $msg .= " | ❌ Hata: $errors";
            $_SESSION['success'] = "Excel içe aktarma tamamlandı. $msg";

        } else {
            $_SESSION['error'] = 'Excel okunamadı: ' . SimpleXLSX::parseError();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Hata: ' . $e->getMessage();
    }
}

header('Location: products.php');
exit;
