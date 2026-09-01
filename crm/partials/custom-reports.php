<?php
/**
 * Özel Rapor Merkezi — kayıt defteri.
 * Yeni bir rapor eklemek için buraya yeni bir slug => tanım girmek yeterli;
 * raporlar.php (kart+form listesi) ve export-ozel-rapor.php (Excel üretimi)
 * ikisi de bu dosyayı ortak kaynak olarak kullanır.
 *
 * 'run' fonksiyonu her satır için 'columns' ile aynı sırada bir dizi döndürür.
 */
function get_custom_reports() {
    return [
        'arac-takip-abonelik' => [
            'title'       => 'En Az N Araç Takip Aboneliği Olan Firmalar',
            'description' => 'Aktif/yenilenmiş (iptal edilmemiş) araç takip cihazı aboneliği bulunan firmaları filo büyüklüğüne göre sıralar.',
            'icon'        => 'truck',
            'filename'    => 'arac_takip_abonelik',
            'params'      => [
                'min' => ['label' => 'Min. araç sayısı', 'type' => 'number', 'default' => 5, 'min' => 1],
            ],
            'columns'     => ['Firma', 'Vergi No', 'Telefon', 'Adres', 'Araç Sayısı'],
            'run'         => function ($conn, $params) {
                $min = max(1, (int)($params['min'] ?? 5));
                $stmt = $conn->prepare(
                    "SELECT c.name, c.tax_number, c.phone, c.address, COUNT(*) as arac_sayisi
                     FROM subscriptions s
                     JOIN customers c ON s.customer_id = c.id
                     WHERE s.item_type = 'product' AND s.status != 'İptal'
                     GROUP BY c.id, c.name, c.tax_number, c.phone, c.address
                     HAVING COUNT(*) >= ?
                     ORDER BY arac_sayisi DESC, c.name ASC"
                );
                $stmt->bind_param('i', $min);
                $stmt->execute();
                $res = $stmt->get_result();
                $rows = [];
                while ($r = $res->fetch_assoc()) {
                    $rows[] = [$r['name'], $r['tax_number'], $r['phone'] ?? '', $r['address'] ?? '', (int)$r['arac_sayisi']];
                }
                $stmt->close();
                return $rows;
            },
        ],

        'yenileme-yaklasan' => [
            'title'       => 'Yenilemesi Yaklaşan Abonelikler',
            'description' => 'Belirtilen gün içinde yenileme tarihi gelecek aktif cihaz/SIM aboneliklerini listeler.',
            'icon'        => 'clock',
            'filename'    => 'yenileme_yaklasan',
            'params'      => [
                'gun' => ['label' => 'Kaç gün içinde', 'type' => 'number', 'default' => 30, 'min' => 1],
            ],
            'columns'     => ['Firma', 'Vergi No', 'Tür', 'Kalem', 'Detay', 'Yenileme Tarihi'],
            'run'         => function ($conn, $params) {
                $gun = max(1, (int)($params['gun'] ?? 30));
                $stmt = $conn->prepare(
                    "SELECT c.name, c.tax_number, s.item_type, s.item_name, s.item_detail, s.renewal_date
                     FROM subscriptions s
                     JOIN customers c ON s.customer_id = c.id
                     WHERE s.status = 'Aktif' AND s.renewal_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                     ORDER BY s.renewal_date ASC, c.name ASC"
                );
                $stmt->bind_param('i', $gun);
                $stmt->execute();
                $res = $stmt->get_result();
                $rows = [];
                while ($r = $res->fetch_assoc()) {
                    $rows[] = [
                        $r['name'],
                        $r['tax_number'],
                        $r['item_type'] === 'product' ? 'Cihaz' : 'SIM',
                        $r['item_name'],
                        $r['item_detail'],
                        $r['renewal_date'],
                    ];
                }
                $stmt->close();
                return $rows;
            },
        ],

        'yenileme-gecikmis' => [
            'title'       => 'Yenilemesi Geçmiş (Gecikmiş) Abonelikler',
            'description' => 'Yenileme tarihi geçtiği halde hâlâ "Aktif" görünen cihaz/SIM aboneliklerini, gecikme süresine göre listeler.',
            'icon'        => 'bell',
            'filename'    => 'yenileme_gecikmis',
            'params'      => [],
            'columns'     => ['Firma', 'Vergi No', 'Tür', 'Kalem', 'Detay', 'Yenileme Tarihi', 'Gecikme (gün)'],
            'run'         => function ($conn, $params) {
                $sql = "SELECT c.name, c.tax_number, s.item_type, s.item_name, s.item_detail, s.renewal_date,
                               DATEDIFF(CURDATE(), s.renewal_date) as gecikme
                        FROM subscriptions s
                        JOIN customers c ON s.customer_id = c.id
                        WHERE s.status = 'Aktif' AND s.renewal_date < CURDATE()
                        ORDER BY s.renewal_date ASC, c.name ASC";
                $res = $conn->query($sql);
                $rows = [];
                while ($r = $res->fetch_assoc()) {
                    $rows[] = [
                        $r['name'],
                        $r['tax_number'],
                        $r['item_type'] === 'product' ? 'Cihaz' : 'SIM',
                        $r['item_name'],
                        $r['item_detail'],
                        $r['renewal_date'],
                        (int)$r['gecikme'],
                    ];
                }
                return $rows;
            },
        ],

        'sim-operator-firma' => [
            'title'       => 'Operatör Bazlı Firma / SIM Dağılımı',
            'description' => 'Her firmanın operatör bazında kaç aktif SIM kart aboneliği olduğunu listeler.',
            'icon'        => 'sim',
            'filename'    => 'sim_operator_firma',
            'params'      => [
                'min' => ['label' => 'Min. SIM sayısı', 'type' => 'number', 'default' => 1, 'min' => 1],
            ],
            'columns'     => ['Firma', 'Vergi No', 'Operatör', 'SIM Sayısı'],
            'run'         => function ($conn, $params) {
                $min = max(1, (int)($params['min'] ?? 1));
                $stmt = $conn->prepare(
                    "SELECT c.name, c.tax_number, s.item_name as operator, COUNT(*) as sim_sayisi
                     FROM subscriptions s
                     JOIN customers c ON s.customer_id = c.id
                     WHERE s.item_type = 'simcard' AND s.status != 'İptal'
                     GROUP BY c.id, c.name, c.tax_number, s.item_name
                     HAVING COUNT(*) >= ?
                     ORDER BY c.name ASC, sim_sayisi DESC"
                );
                $stmt->bind_param('i', $min);
                $stmt->execute();
                $res = $stmt->get_result();
                $rows = [];
                while ($r = $res->fetch_assoc()) {
                    $rows[] = [$r['name'], $r['tax_number'], $r['operator'], (int)$r['sim_sayisi']];
                }
                $stmt->close();
                return $rows;
            },
        ],
    ];
}
