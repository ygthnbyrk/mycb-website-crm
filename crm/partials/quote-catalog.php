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
        'specs' => [],
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
];
