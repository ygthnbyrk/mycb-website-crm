<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("Lütfen giriş yapın");
}

// Toplu satış yüklemesindeki bir hata yüzünden bazı sale_simcards.operator ve
// subscriptions.item_name (item_type='simcard') satırlarına "Şirket - Operatör"
// birleşik metni yazılmıştı. Bu, sadece operatör adını bekleyen yerlerde
// (ör. Raporlar sayfasındaki "Sim Kart Dağılımı" grafiği) tutarsız görünüme
// sebep oluyordu. Bu script mevcut kayıtları temizler; yeni yüklemeler için
// asıl hata bulk-sales-preview.php / bulk-sales-process.php içinde ayrıca
// düzeltildi.

$known_operators = ['Turkcell', 'Vodafone', 'Türk Telekom'];

$updated_sale_simcards = 0;
$res = $conn->query("SELECT id, operator FROM sale_simcards WHERE operator LIKE '%-%'");
while ($row = $res->fetch_assoc()) {
    $parts = explode(' - ', $row['operator']);
    $last = trim(end($parts));
    if (in_array($last, $known_operators, true)) {
        $stmt = $conn->prepare("UPDATE sale_simcards SET operator = ? WHERE id = ?");
        $stmt->bind_param("si", $last, $row['id']);
        $stmt->execute();
        $stmt->close();
        $updated_sale_simcards++;
        echo "sale_simcards #{$row['id']}: '{$row['operator']}' -> '$last'\n";
    }
}
echo "TOPLAM sale_simcards güncellendi: $updated_sale_simcards\n\n";

$updated_subs = 0;
$res2 = $conn->query("SELECT id, item_name FROM subscriptions WHERE item_type = 'simcard' AND item_name LIKE '%-%'");
while ($row = $res2->fetch_assoc()) {
    $parts = explode(' - ', $row['item_name']);
    $last = trim(end($parts));
    if (in_array($last, $known_operators, true)) {
        $stmt = $conn->prepare("UPDATE subscriptions SET item_name = ? WHERE id = ?");
        $stmt->bind_param("si", $last, $row['id']);
        $stmt->execute();
        $stmt->close();
        $updated_subs++;
        echo "subscriptions #{$row['id']}: '{$row['item_name']}' -> '$last'\n";
    }
}
echo "TOPLAM subscriptions güncellendi: $updated_subs\n";
?>
