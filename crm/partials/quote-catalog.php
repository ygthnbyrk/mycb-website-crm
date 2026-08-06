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
];
