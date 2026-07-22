<?php
require_once 'SimpleXLSXGen.php';

use Shuchkin\SimpleXLSXGen;

$data = [
    ['<b>Telefon Numarası</b>', '<b>Operatör</b>', '<b>Şirket</b>', '<b>Kategori</b>', '<b>Durum</b>', '<b>Maliyet</b>', '<b>KDV</b>', '<b>Toplam</b>', '<b>Açıklama</b>'],
    ['05321234567', 'Vodafone', 'Mycb Teknoloji', 'Sim Kart', 'Stokta', '50', '10', '60', 'Örnek açıklama'],
    ['05559876543', 'Turkcell', 'Waystech Bilişim', 'Yenileme', 'Stokta', '100', '20', '120', ''],
    ['', '', '', '', '', '', '', '', '']
];

$xlsx = SimpleXLSXGen::fromArray($data);
$xlsx->downloadAs('simkart_yukleme_sablonu.xlsx');
exit;
?>