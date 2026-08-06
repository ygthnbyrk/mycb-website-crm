<?php
require_once __DIR__ . '/../tfpdf.php';
// Bu tFPDF derlemesinde unifont/ttfonts.php'nin require'ı tfpdf.php içinde
// yorum satırı olarak bırakılmış (TTFontFile sınıfı otomatik yüklenmiyor) —
// AddFont(..., true) çağrılmadan önce elle yüklemek gerekiyor.
require_once __DIR__ . '/../font/unifont/ttfonts.php';

class QuotePdf extends tFPDF
{
    public $companyName = 'Mycb Teknoloji Sanayi Ticaret Ltd.Şti';
    public $companyAddress = 'Mithatpaşa Mah. Gazi Paşa Cad. No:74 K:4 Kozlu/Zonguldak';
    public $companyPhone = '0532 629 73 19';
    private $logoPath;

    const MARGIN = 15;
    const PAGE_WIDTH = 210;
    const CONTENT_WIDTH = 180; // PAGE_WIDTH - 2*MARGIN

    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        parent::__construct($orientation, $unit, $size);
        // Alfa kanalsız, beyaz zemine düzleştirilmiş logo kullanılıyor —
        // FPDF'in şeffaf PNG'ler için GD uzantısına ihtiyaç duymasını önler.
        $this->logoPath = __DIR__ . '/../assets/images/logo-pdf.png';
        $this->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
        $this->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.ttf', true);
        $this->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $this->SetAutoPageBreak(true, 25);
        $this->AliasNbPages();
    }

    function Header()
    {
        if (file_exists($this->logoPath)) {
            $this->Image($this->logoPath, self::MARGIN, 12, 42);
        }

        $this->SetXY(-95, 13);
        $this->SetFont('DejaVu', 'B', 18);
        $this->SetTextColor(20, 20, 20);
        $this->Cell(80, 8, 'TEKLİF', 0, 2, 'R');

        $this->SetX(-95);
        $this->SetFont('DejaVu', '', 8.5);
        $this->SetTextColor(120, 120, 120);
        $this->MultiCell(80, 4.2, $this->companyAddress . "\n" . $this->companyPhone, 0, 'R');

        $this->SetY(30);
        $this->SetDrawColor(220, 38, 38);
        $this->SetLineWidth(0.6);
        $this->Line(self::MARGIN, 30, self::PAGE_WIDTH - self::MARGIN, 30);
        $this->SetLineWidth(0.2);
        $this->SetY(36);
    }

    function Footer()
    {
        $this->SetY(-18);
        $this->SetDrawColor(229, 231, 235);
        $this->SetLineWidth(0.2);
        $this->Line(self::MARGIN, $this->GetY(), self::PAGE_WIDTH - self::MARGIN, $this->GetY());
        $this->Ln(2);
        $this->SetFont('DejaVu', '', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 5, $this->companyName . '  •  Sayfa ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    /** İnce alt çizgili bir bölüm başlığı (örn. "MÜŞTERİ", "KALEMLER") */
    function SectionTitle($text)
    {
        $this->SetFont('DejaVu', 'B', 10);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 6, mb_strtoupper($text, 'UTF-8'), 0, 1, 'L');
        $this->SetDrawColor(229, 231, 235);
        $this->Line($this->GetX(), $this->GetY(), self::PAGE_WIDTH - self::MARGIN, $this->GetY());
        $this->Ln(3);
    }

    /** Görseli en-boy oranını koruyarak maxW x maxH kutusuna sığdırır, sayfada ortalar */
    function FitImage($path, $maxW, $maxH)
    {
        $size = @getimagesize($path);
        if (!$size) return;
        [$pxW, $pxH] = $size;
        $ratio = min($maxW / $pxW, $maxH / $pxH);
        $w = $pxW * $ratio;
        $h = $pxH * $ratio;
        $x = self::MARGIN + (self::CONTENT_WIDTH - $w) / 2;
        $this->Image($path, $x, $this->GetY(), $w, $h);
        $this->SetY($this->GetY() + $h);
    }
}
