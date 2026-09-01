<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("Lütfen giriş yapın");
}

header('Content-Type: text/plain; charset=utf-8');

// 1) products.imei_number: Telematik dışı kategoriler (Aksesuar/Hizmet/bazı Kamera
// kalemleri) için IMEI anlamsız, artık zorunlu değil.
$col = $conn->query("SHOW COLUMNS FROM products LIKE 'imei_number'")->fetch_assoc();
if ($col && stripos($col['Null'], 'NO') === 0) {
    if ($conn->query("ALTER TABLE products MODIFY imei_number VARCHAR(50) NULL")) {
        echo "OK: products.imei_number artik NULL olabilir.\n";
    } else {
        echo "HATA: " . $conn->error . "\n";
    }
} else {
    echo "OK: products.imei_number zaten NULL olabiliyor, atlandi.\n";
}

// 2) camera_sales / camera_sale_items - Kamera Satisi akisi icin ayri tablolar.
// sales/sale_products'a hic dokunulmuyor, create-sale.php akisindan tamamen bagimsiz.
$exists = $conn->query("SHOW TABLES LIKE 'camera_sales'")->num_rows > 0;
if (!$exists) {
    $sql = "CREATE TABLE camera_sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_date DATE NOT NULL,
        customer_id INT NOT NULL,
        notes TEXT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    echo $conn->query($sql) ? "OK: camera_sales olusturuldu.\n" : "HATA: " . $conn->error . "\n";
} else {
    echo "OK: camera_sales zaten var, atlandi.\n";
}

$exists = $conn->query("SHOW TABLES LIKE 'camera_sale_items'")->num_rows > 0;
if (!$exists) {
    $sql = "CREATE TABLE camera_sale_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        camera_sale_id INT NOT NULL,
        product_id INT NULL,
        category VARCHAR(50) NULL,
        item_name VARCHAR(255) NOT NULL,
        cost_price DECIMAL(10,2) NULL,
        quantity INT NOT NULL DEFAULT 1,
        notes VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    echo $conn->query($sql) ? "OK: camera_sale_items olusturuldu.\n" : "HATA: " . $conn->error . "\n";
} else {
    echo "OK: camera_sale_items zaten var, atlandi.\n";
}

// 3) Katalog: Dashcam / kablolar / Montaj - Urunler listesinde secime hazir gelsin.
// idempotent: ayni model+category zaten varsa tekrar eklemez.
$user_id = $_SESSION['user_id'];
$seed = [
    ['model' => 'İç-Dış 2K Smart Dashcam', 'name' => 'İç-Dış 2K Smart Dashcam', 'category' => 'Kamera',   'cost' => 0],
    ['model' => '3M Kablo',                'name' => '3M Kablo',                'category' => 'Aksesuar', 'cost' => 0],
    ['model' => '5M Kablo',                'name' => '5M Kablo',                'category' => 'Aksesuar', 'cost' => 0],
    ['model' => '7M Kablo',                'name' => '7M Kablo',                'category' => 'Aksesuar', 'cost' => 0],
    ['model' => '10M Kablo',               'name' => '10M Kablo',               'category' => 'Aksesuar', 'cost' => 0],
    ['model' => 'Montaj',                  'name' => 'Montaj',                  'category' => 'Hizmet',   'cost' => 0],
];
foreach ($seed as $item) {
    $check = $conn->prepare("SELECT id FROM products WHERE model = ? AND category = ?");
    $check->bind_param('ss', $item['model'], $item['category']);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo "OK: {$item['model']} ({$item['category']}) zaten kayitli, atlandi.\n";
        $check->close();
        continue;
    }
    $check->close();

    $stmt = $conn->prepare("INSERT INTO products (model, product_name, serial_number, imei_number, cost_price, vat, total_cost, category, description, status, created_by) VALUES (?, ?, NULL, NULL, ?, 0, ?, ?, NULL, 'Stokta', ?)");
    $stmt->bind_param('ssddsi', $item['model'], $item['name'], $item['cost'], $item['cost'], $item['category'], $user_id);
    if ($stmt->execute()) {
        echo "OK: {$item['model']} ({$item['category']}) katalog kaydi eklendi.\n";
    } else {
        echo "HATA ({$item['model']}): " . $stmt->error . "\n";
    }
    $stmt->close();
}

echo "Bitti.\n";
