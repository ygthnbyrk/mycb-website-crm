<?php
require_once 'SimpleXLSXGen.php';

use Shuchkin\SimpleXLSXGen;

$data = [
    ['<b>Müşteri Adı</b>', '<b>Vergi Numarası</b>', '<b>Email</b>', '<b>Telefon</b>', '<b>Adres</b>'],
    ['Örnek Şirket A.Ş.', '1234567890', 'ornek@sirket.com', '0532 123 4567', 'İstanbul, Türkiye'],
    ['Test Firma Ltd.', '9876543210', 'test@firma.com', '0555 999 8888', 'Ankara, Türkiye'],
    ['', '', '', '', '']
];

$xlsx = SimpleXLSXGen::fromArray($data);
$xlsx->downloadAs('musteri_yukleme_sablonu.xlsx');
exit;
?>