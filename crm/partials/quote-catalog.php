<?php
/**
 * Teklif PDF'ine eklenebilecek ürün kataloğu.
 * Her ürün: başlık, teknik özellik maddeleri, görsel dosya adları
 * (crm/assets/images/products/ altında). Yeni ürün eklemek için
 * buraya yeni bir anahtar eklemek yeterli — create-quote.php'deki
 * seçim listesi ve generate-quote-pdf.php'deki katalog sayfaları
 * otomatik güncellenir.
 */
return [
    '95c' => [
        'label' => '95C Online Dashcam Kamera',
        'specs' => [
            'Dahili yüksek performanslı görüntü işleme çipi',
            'H.264/H.265 kodlama ile yüksek sıkıştırma oranı, net görüntü',
            '1CH Öne bakan 1080p kamera',
            '3 Adet extra kamera takılabilme',
            "GPS/BDS/GLONASS'ı, yüksek hassasiyeti ve hızlı konumlandırmayı destekler.",
            '1 Adet dahili panik butonu',
            '2x512 GB Hafıza kartı desteği',
            'Ses kayıt özelliği',
            'Kolay kurulum',
            'WEB & Mobil arayüz desteği',
        ],
        'images' => ['95c-1.png', '95c-2.png', '95c-3.png'],
    ],
    '95a' => [
        'label' => '95A Online Dashcam Kamera',
        'specs' => [
            'Dahili yüksek performanslı görüntü işleme çipi',
            'H.264/H.265 kodlama ile yüksek sıkıştırma oranı, net görüntü',
            '1CH Öne bakan 1080p kamera',
            '1CH Arka görüş 1080p kamera',
            "GPS/BDS/GLONASS'ı, yüksek hassasiyeti ve hızlı konumlandırmayı destekler.",
            '512 GB Hafıza kartı desteği',
            'Ses kayıt özelliği',
            'Kolay kurulum',
            'WEB & Mobil arayüz desteği',
        ],
        'images' => ['95a-1.png', '95a-2.jpeg'],
    ],
    'tek-tarafli-182' => [
        'label' => 'Tek Taraflı Online Dashcam Kamera (182)',
        'specs' => [
            '2K Görüntü Kalitesi',
            'OBD Bağlantı Girişi',
            'Gerçek Zamanlı LTE Bağlantısı',
            "256 GB'a Kadar Hafıza Desteği",
            '4G LTE / Nano-SIM',
            'Park Modülü & G Sensör',
            'Nano Sim ve WiFi Özelliği',
            'Çalışma Sıcaklığı -20°C ile +65°C',
        ],
        'images' => ['tek-tarafli-182-1.png', 'tek-tarafli-182-2.jpg', 'tek-tarafli-182-3.png'],
    ],
    'wifi-offline' => [
        'label' => 'Wifi Offline Dashcam Kamera - Oscar',
        'specs' => [
            "3'inç Ekran",
            '2K Quad HD Çözünürlük',
            'Gece Görüş Özelliği',
            'Wi-Fi mobil bağlantı, video mobil indirme',
            'Arka kamera isteğe bağlı olarak içeriden veya dışarıdan destekler (opsiyonel) – 2 Kanal desteği',
            'Park modülü & G Sensör',
            'Dahili Mikrofon',
            'Güç açıldığında otomatik olarak açılır ve kayda başlar',
            "Döngüsel kayıt (Circular recording - kayıt dosyalarının tam otomatik üzerine yazılmasını sağlar)",
            "256 GB'a kadar SD hafıza kartını destekler",
        ],
        'images' => ['wifi-offline-1.jpg'],
    ],
    'harvox' => [
        'label' => 'Harvox Online Kamera',
        'specs' => [
            '2K Görüntü Kalitesi',
            "512 GB'a Kadar Hafıza Desteği",
            "3'inç Ekran",
            'G Sensör',
            'Dahili Mikrofon',
            'Güç açıldığında otomatik olarak açılır ve kayda başlar',
        ],
        'images' => ['harvox-online-1.jpg'],
    ],
    't0' => [
        'label' => 'T0 Standart Araç Takip Cihazı',
        'specs' => [
            'Ürün Boyutları: 92 x 62 x 21 mm',
            'Ağırlık: 77 gr',
            'Voltaj Aralığı: 9 - 30 V DC',
            'Sıcaklık Aralığı: -40/+85°C',
            'Göstergeler: PWR/GSM/GPRS',
            'Giriş/Çıkış: 1x Kontak Girişi, 1x Dijital Çıkış',
            'Uyumlu Aksesuarlar: Motorblokaj',
        ],
        'images' => ['t0-1.png'],
    ],
    'p56' => [
        'label' => 'P56 Takip Cihazı',
        'specs' => [
            'Ürün Boyutları: 80 x 80 x 24.5 mm',
            'Ağırlık: 85 gr',
            'Voltaj Aralığı: 5 V DC',
            'Pil Tipi: Şarj Edilebilir Li-Ion',
            'Pil Kapasitesi: 3400 / 6800 mAh',
            'Sıcaklık Aralığı: -20/+60°C',
            'GSM/GPRS Anten: Dahili PCB Anten',
        ],
        'images' => ['p56-1.png'],
    ],
    't15-premium-canbus' => [
        'label' => 'T15 Premium Canbus',
        'specs' => [
            'Anlık Konum Takibi: Araçlarınızın konumunu gerçek zamanlı izleyin.',
            'G-Sensör Özelliği: Hassas ivmeölçer (G-Sensör) ile sürücü davranışlarını doğru tespit edin.',
            'CAN Bus Özelliği: Opsiyonel CAN bus modülü ile anlık yakıt tüketimi, km, emniyet kemeri vb. verileri takip edin. Aracınızın gerçek gösterge ve sensör verilerine ulaşın.',
            'Opsiyonel 4G bağlantısıyla en geniş kapsama alanı içinde araçlarınızı takip edin.',
            'Batarya Özelliği: Opsiyonel batarya seçeneği ile veri aktarımını kesintisiz sürdürün.',
            'Gateway Özelliği: Opsiyonel gateway özelliği ile araç takip cihazı üzerinden kablosuz sensör verilerine ulaşın.',
            'Motor Blokaj Özelliği: Opsiyonel kart okuyucu ile motoru uzaktan durdurun ve çalıştırın.',
            'Sürücü Takip Özelliği: Opsiyonel sürücü takip kiti ile aracı kimin sürdüğünü ayırt edin.',
            'Canbus Özelliği: Araçların marka ve modeline göre anlık yakıt tüketimi, yakıt menzili, motor suyu sıcaklığı, gerçek km takibi (odometre), ortalama yakıt tüketimi, emniyet kemeri kullanımı, el freni, motor devri benzeri verileri takip edin.',
            'Insight Yakıt Tüketim Analizi: Ayrıntılı yakıt tüketim analizi ekranlarına opsiyonel olarak ulaşın.',
        ],
        'images' => ['t15-premium-canbus-1.png', 't15-premium-canbus-2.png'],
    ],
    'moto22' => [
        'label' => 'Moto22 Motosiklet Takip Cihazı',
        'specs' => [
            'Anlık Konum Takibi: Motorların konumunu gerçek zamanlı izleyin.',
            'Batarya Özelliği: Opsiyonel batarya seçeneği ile kesintisiz veri almaya devam edin.',
            'Suya - Toza Karşı Dayanıklılık: Özel tasarım IP65 kutular ile sudan ve tozdan koruma sağlayın.',
            'Ürün Boyutları: 87 x 62 x 22 mm',
            'Ağırlık: 52 gr',
            'Voltaj Aralığı: 9 - 30 V DC',
            'Sıcaklık Aralığı: -40 / +85°C',
            'Göstergeler: PWR / GSM / GPRS',
        ],
        'images' => ['moto22-1.png'],
    ],
    'dba' => [
        'label' => 'Dijital Sürüş Koçu',
        'specs' => [
            'Dijital Sürüş Koçu, G-Sensör ile sürücü davranışlarını izler, sesli uyarılar ve sürücü kimlik tespiti sağlar. Ayrıca, gelişmiş raporlama ve opsiyonel sürücü risk analizi ile güvenliği artırır.',
            'G-Sensör Özelliği: Hassas ivmeölçer (G-Sensör) ile sürücü davranışlarını doğru tespit edin.',
            'Sesli Uyarı Özelliği: Sürücülerin seyahat anında sesli uyarılmasını sağlayın.',
            'Sürücü Takip Özelliği: Dahili kart okuyucu özelliği ile aracı kimin sürdüğünü ayırt edin.',
            'Gelişmiş Raporlama ve Analizler: Varlık takibi için anlık ve geçmişe dönük raporları alın.',
            'Insight Sürücü Risk Analizi: Ayrıntılı sürücü risk analizi ekranlarına opsiyonel olarak ulaşın.',
            'Ürün Boyutları: 85 x 85 x 23 mm',
            'Ağırlık: 81 gr',
            'Teknoloji: WiFi / BLE',
            'Dil Seçenekleri: Türkçe / İngilizce',
            'Özellikler: Sesli Uyarı, Görsel Uyarı, NFC Sürücü Tanıma, G Sensör, Sürücü Takip, T15 2G ve T15 4G ile birlikte çalışır.',
        ],
        'images' => ['dba-1.png'],
    ],
];
