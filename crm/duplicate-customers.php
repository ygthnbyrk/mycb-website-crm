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

/**
 * Türkçe harflere duyarlı normalize: büyük/küçük harf ve Türkçe özel karakter
 * farklarını (İ/I/ı, Ş/ş, Ğ/ğ, Ü/ü, Ö/ö, Ç/ç) eritir, boşlukları sadeleştirir.
 * mb_strtolower tek başına İ/I harflerini Türkçe kurallarına göre çevirmediği
 * için (Unicode varsayılanı kullanır) burada elle eşleştiriyoruz.
 */
function normalize_tr_name($s) {
    static $map = ['İ' => 'i', 'I' => 'i', 'ı' => 'i', 'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g', 'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o', 'Ç' => 'c', 'ç' => 'c'];
    $s = strtr(trim($s), $map);
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^\p{L}\p{N} ]/u', ' ', $s); // noktalama vs. boşluğa çevrilir (Ltd. Şti. -> Ltd Sti)
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

/**
 * normalize_tr_name'in üzerine, sadece şirket unvanı eki olan genel kelimeleri
 * ("Ltd Şti", "A.Ş", "Ticaret", "Sanayi" vb.) atar — "ABC Ltd. Şti." ile
 * "ABC Ticaret Sanayi Ltd Şti" gibi ünvan yazım farklarını da yakalamak için.
 */
function normalize_tr_company($s) {
    static $suffixes = ['ltd', 'sti', 'as', 'tic', 'ticaret', 'san', 'sanayi', 'limited', 'anonim', 'sirketi', 'a s'];
    $s = normalize_tr_name($s);
    $words = array_filter(explode(' ', $s), fn($w) => $w !== '' && !in_array($w, $suffixes, true));
    return trim(implode(' ', $words));
}

$customers = $conn->query("
    SELECT c.*,
        (SELECT COUNT(*) FROM sales WHERE customer_id = c.id) as sales_count,
        (SELECT COUNT(*) FROM subscriptions WHERE customer_id = c.id) as subs_count,
        (SELECT COUNT(*) FROM quotes WHERE customer_id = c.id) as quotes_count
    FROM customers c
    ORDER BY c.name
")->fetch_all(MYSQLI_ASSOC);

// Union-Find ile kümeleme: isim ya da vergi no eşleşen müşteriler aynı kümeye girer
$parent = [];
foreach ($customers as $c) $parent[$c['id']] = $c['id'];

function uf_find(&$parent, $x) {
    while ($parent[$x] !== $x) {
        $parent[$x] = $parent[$parent[$x]];
        $x = $parent[$x];
    }
    return $x;
}
function uf_union(&$parent, $a, $b) {
    $ra = uf_find($parent, $a);
    $rb = uf_find($parent, $b);
    if ($ra !== $rb) $parent[$ra] = $rb;
}

$by_name = [];
$by_company = [];
$by_tax = [];
foreach ($customers as $c) {
    $by_name[normalize_tr_name($c['name'])][] = $c['id'];
    $company_key = normalize_tr_company($c['name']);
    if (mb_strlen($company_key, 'UTF-8') >= 3) {
        $by_company[$company_key][] = $c['id'];
    }
    if (!empty(trim($c['tax_number']))) {
        $by_tax[trim($c['tax_number'])][] = $c['id'];
    }
}
foreach ([$by_name, $by_company, $by_tax] as $group_map) {
    foreach ($group_map as $ids) {
        for ($i = 1; $i < count($ids); $i++) uf_union($parent, $ids[0], $ids[$i]);
    }
}

$clusters = [];
foreach ($customers as $c) {
    $root = uf_find($parent, $c['id']);
    $clusters[$root][] = $c;
}
$clusters = array_filter($clusters, fn($g) => count($g) > 1);

// Her kümede en çok bağlı kaydı olan müşteriyi varsayılan "tutulacak" seç
foreach ($clusters as &$group) {
    usort($group, fn($a, $b) =>
        ($b['sales_count'] + $b['subs_count'] + $b['quotes_count']) <=> ($a['sales_count'] + $a['subs_count'] + $a['quotes_count'])
    );
}
unset($group);

// Kesin (isim/vergi no birebir) eşleşmeyen kalan müşteriler arasında, yazım
// hatası / eksik-fazla kelime gibi farkları yakalamak için ikili benzerlik
// taraması. Bunlar otomatik kümelenmiyor, tek tek incelemen için listeleniyor -
// yanlış eşleştirme riski isim/vergi no eşleşmesinden daha yüksek.
$clustered_ids = [];
foreach ($clusters as $group) {
    foreach ($group as $c) $clustered_ids[$c['id']] = true;
}
$leftover = array_values(array_filter($customers, fn($c) => !isset($clustered_ids[$c['id']])));

$fuzzy_pairs = [];
$n = count($leftover);
for ($i = 0; $i < $n; $i++) {
    $ni = normalize_tr_name($leftover[$i]['name']);
    for ($j = $i + 1; $j < $n; $j++) {
        $nj = normalize_tr_name($leftover[$j]['name']);
        similar_text($ni, $nj, $percent);
        if ($percent >= 90) {
            $fuzzy_pairs[] = ['a' => $leftover[$i], 'b' => $leftover[$j], 'percent' => round($percent)];
        }
    }
}
usort($fuzzy_pairs, fn($x, $y) => $y['percent'] <=> $x['percent']);

$user_name = $_SESSION['user_name'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Mükerrer Müşteriler - CRM</title>
</head>
<body>
    <?php $active_page = ''; include 'partials/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1><?php echo icon('users'); ?> Mükerrer Müşteriler</h1>
                <p class="welcome">İsim ya da vergi numarası eşleşen müşteri kayıtlarını bulur, seçtiğini tek kayda birleştirir.</p>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="stats-bar" style="margin-bottom:20px;">
            <div class="stat-box">
                <h3><?php echo count($clusters); ?></h3>
                <p>Kesin Eşleşen Grup</p>
            </div>
            <div class="stat-box">
                <h3><?php echo count($fuzzy_pairs); ?></h3>
                <p>İncelemen Gereken Benzer İsim</p>
            </div>
        </div>

        <h2 style="font-size:16px; margin-bottom:10px;">Kesin Eşleşmeler (isim ya da vergi no birebir aynı)</h2>
        <?php if (empty($clusters)): ?>
            <div class="detail-card">
                <div class="no-data">Mükerrer görünen müşteri bulunamadı.</div>
            </div>
        <?php else: ?>
            <?php foreach ($clusters as $group): ?>
                <div class="detail-card" style="margin-bottom:16px;">
                    <form method="POST" action="merge-customers.php" onsubmit="return confirm('Seçili olmayan kayıtlardaki tüm satış/abonelik/teklif geçmişi seçili müşteriye taşınacak ve diğer kayıtlar silinecek. Emin misin?');">
                        <?php foreach ($group as $c): ?>
                            <input type="hidden" name="all_ids[]" value="<?php echo $c['id']; ?>">
                        <?php endforeach; ?>

                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width:40px;">Tut</th>
                                        <th>Müşteri Adı</th>
                                        <th>Vergi No</th>
                                        <th>Telefon</th>
                                        <th>E-posta</th>
                                        <th style="text-align:center;">Satış</th>
                                        <th style="text-align:center;">Abonelik</th>
                                        <th style="text-align:center;">Teklif</th>
                                        <th>Kayıt Tarihi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($group as $i => $c): ?>
                                        <tr>
                                            <td><input type="radio" name="keep_id" value="<?php echo $c['id']; ?>" <?php echo $i === 0 ? 'checked' : ''; ?> required></td>
                                            <td><strong><?php echo htmlspecialchars($c['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($c['tax_number'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars($c['phone'] ?: '-'); ?></td>
                                            <td><small><?php echo htmlspecialchars($c['email'] ?: '-'); ?></small></td>
                                            <td style="text-align:center;"><?php echo (int)$c['sales_count']; ?></td>
                                            <td style="text-align:center;"><?php echo (int)$c['subs_count']; ?></td>
                                            <td style="text-align:center;"><?php echo (int)$c['quotes_count']; ?></td>
                                            <td><small><?php echo date('d.m.Y', strtotime($c['created_at'])); ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button type="submit" class="btn btn-primary" style="margin-top:12px;"><?php echo icon('check'); ?> Seçili Kayda Birleştir</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h2 style="font-size:16px; margin:24px 0 10px;">İncelemen Gereken Benzer İsimler (yazım farkı olabilir, otomatik seçilmedi)</h2>
        <?php if (empty($fuzzy_pairs)): ?>
            <div class="detail-card">
                <div class="no-data">Benzer isimli başka müşteri bulunamadı.</div>
            </div>
        <?php else: ?>
            <?php foreach ($fuzzy_pairs as $pair): ?>
                <div class="detail-card" style="margin-bottom:16px;">
                    <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:10px;">Benzerlik: %<?php echo $pair['percent']; ?></p>
                    <form method="POST" action="merge-customers.php" onsubmit="return confirm('Seçili olmayan kayıttaki tüm satış/abonelik/teklif geçmişi seçili müşteriye taşınacak ve diğer kayıt silinecek. Bu ikisi gerçekten aynı müşteri mi, emin misin?');">
                        <input type="hidden" name="all_ids[]" value="<?php echo $pair['a']['id']; ?>">
                        <input type="hidden" name="all_ids[]" value="<?php echo $pair['b']['id']; ?>">

                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width:40px;">Tut</th>
                                        <th>Müşteri Adı</th>
                                        <th>Vergi No</th>
                                        <th>Telefon</th>
                                        <th>E-posta</th>
                                        <th style="text-align:center;">Satış</th>
                                        <th style="text-align:center;">Abonelik</th>
                                        <th style="text-align:center;">Teklif</th>
                                        <th>Kayıt Tarihi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ([$pair['a'], $pair['b']] as $i => $c): ?>
                                        <tr>
                                            <td><input type="radio" name="keep_id" value="<?php echo $c['id']; ?>" <?php echo $i === 0 ? 'checked' : ''; ?> required></td>
                                            <td><strong><?php echo htmlspecialchars($c['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($c['tax_number'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars($c['phone'] ?: '-'); ?></td>
                                            <td><small><?php echo htmlspecialchars($c['email'] ?: '-'); ?></small></td>
                                            <td style="text-align:center;"><?php echo (int)$c['sales_count']; ?></td>
                                            <td style="text-align:center;"><?php echo (int)$c['subs_count']; ?></td>
                                            <td style="text-align:center;"><?php echo (int)$c['quotes_count']; ?></td>
                                            <td><small><?php echo date('d.m.Y', strtotime($c['created_at'])); ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="action-btns" style="margin-top:12px; display:flex; gap:8px;">
                            <button type="submit" class="btn btn-primary"><?php echo icon('check'); ?> Bunlar Aynı, Birleştir</button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
