<?php
require_once 'config.php';
require_once 'partials/icons.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: hub.php');
    exit();
}

$user_name = $_SESSION['user_name'];
$palette = ['var(--accent)', 'var(--success)', 'var(--warning)', 'var(--purple)', 'var(--info)', 'var(--danger)'];

function svg_month_bars($months, $height = 220) {
    $n = count($months);
    $unit = 56;
    $w = $n * $unit;
    $chart_h = $height - 30;
    $max = 0;
    foreach ($months as $m) $max = max($max, $m['value']);
    if ($max <= 0) $max = 1;
    $bar_w = $unit * 0.5;
    $svg = '<svg viewBox="0 0 ' . $w . ' ' . $height . '" width="100%" height="' . $height . '" preserveAspectRatio="xMidYMid meet">';
    foreach ($months as $i => $m) {
        $x = $i * $unit + ($unit - $bar_w) / 2;
        $h = round(($m['value'] / $max) * $chart_h);
        if ($m['value'] > 0) $h = max($h, 3);
        $y = $chart_h - $h;
        $title = htmlspecialchars($m['label']) . ': ' . number_format($m['value'], 0, ',', '.') . ' ₺ (' . $m['count'] . ' satış)';
        $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $bar_w . '" height="' . $h . '" rx="4" fill="var(--accent)"><title>' . $title . '</title></rect>';
        $svg .= '<text x="' . ($x + $bar_w / 2) . '" y="' . ($height - 8) . '" text-anchor="middle" font-size="11" fill="var(--text-muted)">' . htmlspecialchars($m['label']) . '</text>';
    }
    $svg .= '</svg>';
    return $svg;
}

function svg_donut($segments, $size = 160, $stroke = 24) {
    $r = ($size - $stroke) / 2;
    $cx = $size / 2;
    $cy = $size / 2;
    $circumference = 2 * M_PI * $r;
    $total = 0;
    foreach ($segments as $s) $total += $s['value'];
    $svg = '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">';
    if ($total <= 0) {
        $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="var(--bg-hover)" stroke-width="' . $stroke . '"/>';
    } else {
        $svg .= '<g transform="rotate(-90 ' . $cx . ' ' . $cy . ')">';
        $offset = 0;
        foreach ($segments as $seg) {
            $frac = $seg['value'] / $total;
            $dash = $frac * $circumference;
            $gap = $circumference - $dash;
            $title = htmlspecialchars($seg['label']) . ': ' . $seg['value'] . ' (%' . round($frac * 100) . ')';
            $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="' . $seg['color'] . '" stroke-width="' . $stroke . '" stroke-dasharray="' . round($dash, 2) . ' ' . round($gap, 2) . '" stroke-dashoffset="-' . round($offset, 2) . '"><title>' . $title . '</title></circle>';
            $offset += $dash;
        }
        $svg .= '</g>';
    }
    $svg .= '<text x="' . $cx . '" y="' . ($cy - 3) . '" text-anchor="middle" font-size="19" font-weight="700" fill="var(--text-primary)">' . number_format($total, 0, ',', '.') . '</text>';
    $svg .= '<text x="' . $cx . '" y="' . ($cy + 15) . '" text-anchor="middle" font-size="10.5" fill="var(--text-muted)">toplam</text>';
    $svg .= '</svg>';
    return $svg;
}

// Yillar
$years = [];
$yres = $conn->query("SELECT DISTINCT YEAR(sale_date) as y FROM sales ORDER BY y ASC");
while ($r = $yres->fetch_assoc()) $years[] = (int)$r['y'];
if (empty($years)) $years[] = (int)date('Y');

$current_year = (int)date('Y');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (in_array($current_year, $years) ? $current_year : end($years));
if (!in_array($selected_year, $years)) $selected_year = end($years);

// KPI (secili yil)
$kpi_stmt = $conn->prepare("SELECT COUNT(*) as sale_count, SUM(total) as revenue FROM sales WHERE YEAR(sale_date) = ?");
$kpi_stmt->bind_param("i", $selected_year);
$kpi_stmt->execute();
$kpi = $kpi_stmt->get_result()->fetch_assoc();

$prod_stmt = $conn->prepare("SELECT COUNT(*) as c FROM sale_products sp INNER JOIN sales s ON sp.sale_id = s.id WHERE YEAR(s.sale_date) = ?");
$prod_stmt->bind_param("i", $selected_year);
$prod_stmt->execute();
$total_products_sold = $prod_stmt->get_result()->fetch_assoc()['c'];

$sim_stmt = $conn->prepare("SELECT COUNT(*) as c FROM sale_simcards ss INNER JOIN sales s ON ss.sale_id = s.id WHERE YEAR(s.sale_date) = ?");
$sim_stmt->bind_param("i", $selected_year);
$sim_stmt->execute();
$total_simcards_sold = $sim_stmt->get_result()->fetch_assoc()['c'];

// Aylik trend (secili yil, Ocak-Aralik)
$ay_kisa = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
$monthly = [];
for ($m = 1; $m <= 12; $m++) {
    $monthly[$m] = ['label' => $ay_kisa[$m - 1], 'value' => 0.0, 'count' => 0];
}
$tstmt = $conn->prepare("SELECT MONTH(sale_date) as m, SUM(total) as revenue, COUNT(*) as cnt FROM sales WHERE YEAR(sale_date) = ? GROUP BY m");
$tstmt->bind_param("i", $selected_year);
$tstmt->execute();
$tres = $tstmt->get_result();
while ($row = $tres->fetch_assoc()) {
    $monthly[(int)$row['m']]['value'] = (float)$row['revenue'];
    $monthly[(int)$row['m']]['count'] = (int)$row['cnt'];
}

// En cok satan modeller (secili yil, top 6 + diger)
$mstmt = $conn->prepare("SELECT sp.model, COUNT(*) as qty FROM sale_products sp INNER JOIN sales s ON sp.sale_id = s.id WHERE YEAR(s.sale_date) = ? GROUP BY sp.model ORDER BY qty DESC");
$mstmt->bind_param("i", $selected_year);
$mstmt->execute();
$all_models = $mstmt->get_result()->fetch_all(MYSQLI_ASSOC);
$model_segments = [];
$other_qty = 0;
foreach ($all_models as $i => $row) {
    if ($i < 6) {
        $model_segments[] = ['label' => $row['model'], 'value' => (int)$row['qty'], 'color' => $palette[$i % count($palette)]];
    } else {
        $other_qty += (int)$row['qty'];
    }
}
if ($other_qty > 0) {
    $model_segments[] = ['label' => 'Diğer', 'value' => $other_qty, 'color' => 'var(--text-muted)'];
}

// Sim kart operator dagilimi (secili yil)
$sstmt = $conn->prepare("SELECT ss.operator, COUNT(*) as qty FROM sale_simcards ss INNER JOIN sales s ON ss.sale_id = s.id WHERE YEAR(s.sale_date) = ? GROUP BY ss.operator ORDER BY qty DESC");
$sstmt->bind_param("i", $selected_year);
$sstmt->execute();
$all_ops = $sstmt->get_result()->fetch_all(MYSQLI_ASSOC);
$sim_segments = [];
foreach ($all_ops as $i => $row) {
    $sim_segments[] = ['label' => $row['operator'], 'value' => (int)$row['qty'], 'color' => $palette[$i % count($palette)]];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Raporlar - CRM</title>
</head>
<body>
    <?php $active_page = 'raporlar'; include 'partials/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1>Raporlar</h1>
                <p class="welcome">Hoş geldiniz, <?php echo htmlspecialchars($user_name); ?>!</p>
            </div>
        </div>

        <div class="year-tabs">
            <?php foreach ($years as $y): ?>
                <a href="raporlar.php?year=<?php echo $y; ?>" class="year-tab<?php echo $y === $selected_year ? ' active' : ''; ?>"><?php echo $y; ?></a>
            <?php endforeach; ?>
        </div>

        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="icon"><?php echo icon('dollar'); ?></div>
                <h3>Toplam Ciro</h3>
                <div class="number"><?php echo number_format((float)$kpi['revenue'], 0, ',', '.'); ?> ₺</div>
                <small><?php echo (int)$kpi['sale_count']; ?> satış işlemi</small>
            </div>
            <div class="stat-card green">
                <div class="icon"><?php echo icon('package'); ?></div>
                <h3>Satılan Cihaz</h3>
                <div class="number"><?php echo (int)$total_products_sold; ?></div>
                <small><?php echo $selected_year; ?> toplamı</small>
            </div>
            <div class="stat-card orange">
                <div class="icon"><?php echo icon('sim'); ?></div>
                <h3>Satılan Sim Kart</h3>
                <div class="number"><?php echo (int)$total_simcards_sold; ?></div>
                <small><?php echo $selected_year; ?> toplamı</small>
            </div>
        </div>

        <div class="detail-card" style="margin-bottom:16px;">
            <h3><?php echo icon('chart'); ?> Aylık Satış Trendi — <?php echo $selected_year; ?></h3>
            <?php echo svg_month_bars($monthly); ?>
        </div>

        <div class="details-grid">
            <div class="detail-card">
                <h3><?php echo icon('package'); ?> En Çok Satan Modeller</h3>
                <?php if (!empty($model_segments)): ?>
                    <div class="donut-row">
                        <?php echo svg_donut($model_segments); ?>
                        <div class="donut-legend">
                            <?php foreach ($model_segments as $seg): ?>
                                <div class="legend-item">
                                    <span class="legend-dot" style="background:<?php echo $seg['color']; ?>"></span>
                                    <span class="legend-label"><?php echo htmlspecialchars($seg['label']); ?></span>
                                    <span class="legend-value"><?php echo $seg['value']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-data">Bu yıl için veri yok</div>
                <?php endif; ?>
            </div>

            <div class="detail-card">
                <h3><?php echo icon('sim'); ?> Sim Kart Dağılımı (Operatör)</h3>
                <?php if (!empty($sim_segments)): ?>
                    <div class="donut-row">
                        <?php echo svg_donut($sim_segments); ?>
                        <div class="donut-legend">
                            <?php foreach ($sim_segments as $seg): ?>
                                <div class="legend-item">
                                    <span class="legend-dot" style="background:<?php echo $seg['color']; ?>"></span>
                                    <span class="legend-label"><?php echo htmlspecialchars($seg['label']); ?></span>
                                    <span class="legend-value"><?php echo $seg['value']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-data">Bu yıl için veri yok</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
