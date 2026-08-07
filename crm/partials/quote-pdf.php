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
            $this->Image($this->logoPath, self::MARGIN, 10, 42);
        }

        $this->SetXY(-95, 10);
        $this->SetFont('DejaVu', 'B', 18);
        $this->SetTextColor(20, 20, 20);
        $this->Cell(80, 8, 'TEKLİF', 0, 2, 'R');

        $this->SetX(-95);
        $this->SetFont('DejaVu', 'B', 9.5);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(80, 5, $this->companyPhone, 0, 2, 'R');

        $this->SetX(-95);
        $this->SetFont('DejaVu', '', 8.5);
        $this->SetTextColor(140, 140, 140);
        $this->MultiCell(80, 4.2, $this->companyAddress, 0, 'R');

        $this->SetY(38);
        $this->SetDrawColor(220, 38, 38);
        $this->SetLineWidth(0.6);
        $this->Line(self::MARGIN, 38, self::PAGE_WIDTH - self::MARGIN, 38);
        $this->SetLineWidth(0.2);
        $this->SetY(44);
    }

    function Footer()
    {
        $this->SetY(-22);
        $this->SetDrawColor(229, 231, 235);
        $this->SetLineWidth(0.2);
        $this->Line(self::MARGIN, $this->GetY(), self::PAGE_WIDTH - self::MARGIN, $this->GetY());
        $this->Ln(2);
        $this->SetFont('DejaVu', '', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 5, $this->companyName . '  •  Sayfa ' . $this->PageNo() . '/{nb}', 0, 1, 'C');
        $this->SetFont('DejaVu', '', 7.5);
        $this->SetTextColor(180, 180, 180);
        $this->Cell(0, 4, 'Trio Mobil | İş Ortağı', 0, 0, 'C');
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

    /**
     * Birden fazla görseli tek satırda yan yana yerleştirir (sayfa genişliğini ve
     * sabit bir maksimum yüksekliği paylaşarak), ortalar. FPDF'in Image() çağrısı
     * kendiliğinden sayfa taşması kontrolü yapmadığı için (Cell/MultiCell'in aksine),
     * birden çok görseli alt alta dizmek sayfa sınırını aşabiliyordu — bunun yerine
     * hepsi tek, öngörülebilir yükseklikte bir satıra sığdırılıyor.
     */
    function ImageRow($paths, $maxHeight = 70)
    {
        $paths = array_values(array_filter($paths, 'file_exists'));
        $n = count($paths);
        if ($n === 0) return;

        $gap = 6;
        $slotW = (self::CONTENT_WIDTH - $gap * ($n - 1)) / $n;
        $sizes = [];
        $rowH = 0;
        foreach ($paths as $p) {
            $info = @getimagesize($p);
            if (!$info) continue;
            [$pxW, $pxH] = $info;
            $ratio = min($slotW / $pxW, $maxHeight / $pxH);
            $w = $pxW * $ratio;
            $h = $pxH * $ratio;
            $sizes[] = ['path' => $p, 'w' => $w, 'h' => $h];
            $rowH = max($rowH, $h);
        }
        if (empty($sizes)) return;

        // Image() Cell/MultiCell'in aksine otomatik sayfa taşması kontrolü yapmıyor —
        // satır kalan sayfa alanına sığmıyorsa (ör. uzun bir özellik listesinden sonra)
        // taşmayı önlemek için önce yeni sayfaya geçiyoruz.
        if ($this->GetY() + $rowH > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }

        $totalW = array_sum(array_column($sizes, 'w')) + $gap * (count($sizes) - 1);
        $x = self::MARGIN + (self::CONTENT_WIDTH - $totalW) / 2;
        $y = $this->GetY();
        foreach ($sizes as $s) {
            $this->Image($s['path'], $x, $y + ($rowH - $s['h']) / 2, $s['w'], $s['h']);
            $x += $s['w'] + $gap;
        }
        $this->SetY($y + $rowH);
    }
}
