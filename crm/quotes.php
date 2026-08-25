<?php
require_once 'config.php';
require_once 'pagination.php';
require_once 'partials/icons.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_name = $_SESSION['user_name'];

$months = [
    1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran',
    7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
];

// Filtreler
$year_filter = $_GET['year'] ?? '';
$month_filter = $_GET['month'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Aktif filtreleri korumak için (durum güncelleme sonrası aynı listeye dönmek için)
$return_qs = http_build_query(array_filter([
    'year' => $year_filter,
    'month' => $month_filter,
    'status' => $status_filter,
    'search' => $search,
    'page' => $page > 1 ? $page : '',
]));

// Yıl listesi (slicer)
$years = [];
$yres = $conn->query("SELECT DISTINCT YEAR(quote_date) as y FROM quotes ORDER BY y DESC");
while ($r = $yres->fetch_assoc()) $years[] = (int)$r['y'];

// KPI'lar (tüm tablo, filtrelerden bağımsız)
$kpi = $conn->query("
    SELECT
        COUNT(*) as total_quotes,
        SUM(CASE WHEN status = 'Beklemede' THEN 1 ELSE 0 END) as pending_quotes,
        SUM(CASE WHEN status = 'Olumlu' THEN 1 ELSE 0 END) as won_quotes,
        SUM(CASE WHEN status = 'Olumsuz' THEN 1 ELSE 0 END) as lost_quotes,
        SUM(CASE WHEN status = 'Beklemede' THEN total ELSE 0 END) as pending_amount,
        SUM(CASE WHEN status = 'Olumlu' THEN total ELSE 0 END) as won_amount
    FROM quotes
")->fetch_assoc();

// Dinamik WHERE
$where_conditions = [];
$params = [];
$types = '';

if ($year_filter !== '' && ctype_digit((string)$year_filter)) {
    $where_conditions[] = "YEAR(quote_date) = ?";
    $params[] = (int)$year_filter;
    $types .= 'i';
}
if ($month_filter !== '' && ctype_digit((string)$month_filter)) {
    $where_conditions[] = "MONTH(quote_date) = ?";
    $params[] = (int)$month_filter;
    $types .= 'i';
}
if (in_array($status_filter, ['Beklemede', 'Olumlu', 'Olumsuz'], true)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}
if (!empty($search)) {
    $where_conditions[] = "(quote_number LIKE ? OR customer_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Toplam sayı
$count_sql = "SELECT COUNT(*) as total FROM quotes $where_clause";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_quotes_filtered = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_quotes_filtered / $per_page);
$stmt->close();

// Teklifleri çek
$sql = "SELECT * FROM quotes $where_clause ORDER BY quote_date DESC, id DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$quotes = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Teklifler - CRM</title>
</head>
<body>
    <?php $active_page = 'quotes'; include 'partials/sidebar.php'; ?>

    <!-- Ana İçerik -->
    <div class="main-content">
        <div class="top-bar">
            <h1><?php echo icon('file-text'); ?> Teklifler</h1>
            <a href="create-quote.php" class="btn btn-primary"><?php echo icon('plus'); ?> Yeni Teklif</a>
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

        <!-- KPI -->
        <div class="stats-bar">
            <div class="stat-box">
                <h3><?php echo (int)$kpi['total_quotes']; ?></h3>
                <p>Toplam Teklif</p>
            </div>
            <div class="stat-box">
                <h3><?php echo (int)$kpi['pending_quotes']; ?></h3>
                <p>Beklemede</p>
            </div>
            <div class="stat-box success">
                <h3><?php echo (int)$kpi['won_quotes']; ?></h3>
                <p>Olumlu</p>
            </div>
            <div class="stat-box danger">
                <h3><?php echo (int)$kpi['lost_quotes']; ?></h3>
                <p>Olumsuz</p>
            </div>
            <div class="stat-box">
                <h3>₺<?php echo number_format((float)$kpi['pending_amount'], 2, ',', '.'); ?></h3>
                <p>Bekleyen Teklif Tutarı</p>
            </div>
            <div class="stat-box success">
                <h3>₺<?php echo number_format((float)$kpi['won_amount'], 2, ',', '.'); ?></h3>
                <p>Satışa Dönen Teklif</p>
            </div>
        </div>

        <!-- Durum Sekmeleri -->
        <div class="filter-row" style="margin-bottom: 12px;">
            <?php $sq = http_build_query(array_filter(['year' => $year_filter, 'month' => $month_filter, 'search' => $search])); ?>
            <a href="?<?php echo $sq; ?>" class="btn btn-light <?php echo $status_filter === '' ? 'active' : ''; ?>">Tümü</a>
            <a href="?status=Beklemede<?php echo $sq ? '&'.$sq : ''; ?>" class="btn btn-light <?php echo $status_filter === 'Beklemede' ? 'active' : ''; ?>">Beklemede</a>
            <a href="?status=Olumlu<?php echo $sq ? '&'.$sq : ''; ?>" class="btn btn-light <?php echo $status_filter === 'Olumlu' ? 'active' : ''; ?>">Olumlu</a>
            <a href="?status=Olumsuz<?php echo $sq ? '&'.$sq : ''; ?>" class="btn btn-light <?php echo $status_filter === 'Olumsuz' ? 'active' : ''; ?>">Olumsuz</a>
        </div>

        <!-- Yıl / Ay / Arama -->
        <div class="action-bar">
            <form method="GET">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <div class="search-filter-row">
                    <div class="search-box">
                        <input type="text" name="search" class="search-input" placeholder="Teklif no veya müşteri adı ara..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="filter-group">
                        <label>Yıl</label>
                        <select name="year" class="filter-select">
                            <option value="">Tümü</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?php echo $y; ?>" <?php echo (string)$year_filter === (string)$y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Ay</label>
                        <select name="month" class="filter-select">
                            <option value="">Tümü</option>
                            <?php foreach ($months as $m_num => $m_name): ?>
                                <option value="<?php echo $m_num; ?>" <?php echo (string)$month_filter === (string)$m_num ? 'selected' : ''; ?>><?php echo $m_name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary"><?php echo icon('filter'); ?> Filtrele</button>
                    <?php if ($search || $year_filter !== '' || $month_filter !== '' || $status_filter !== ''): ?>
                        <a href="quotes.php" class="btn btn-secondary"><?php echo icon('x'); ?> Temizle</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Teklif Tablosu -->
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Teklif No</th>
                        <th>Müşteri</th>
                        <th>Tarih</th>
                        <th>Geçerlilik</th>
                        <th>Tutar</th>
                        <th>Durum</th>
                        <th style="text-align: center;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($quotes->num_rows > 0): ?>
                        <?php while ($q = $quotes->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($q['quote_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($q['customer_name']); ?></td>
                                <td><?php echo date('d.m.Y', strtotime($q['quote_date'])); ?></td>
                                <td><?php echo $q['valid_until'] ? date('d.m.Y', strtotime($q['valid_until'])) : '-'; ?></td>
                                <td><strong>₺<?php echo number_format($q['total'], 2, ',', '.'); ?></strong></td>
                                <td>
                                    <select class="status-select status-<?php echo strtolower($q['status']); ?>" onchange="updateQuoteStatus(<?php echo $q['id']; ?>, this.value)">
                                        <option value="Beklemede" <?php echo $q['status'] === 'Beklemede' ? 'selected' : ''; ?>>Beklemede</option>
                                        <option value="Olumlu" <?php echo $q['status'] === 'Olumlu' ? 'selected' : ''; ?>>Olumlu</option>
                                        <option value="Olumsuz" <?php echo $q['status'] === 'Olumsuz' ? 'selected' : ''; ?>>Olumsuz</option>
                                    </select>
                                </td>
                                <td style="text-align: center;">
                                    <div class="action-btns">
                                        <a href="generate-quote-pdf.php?id=<?php echo $q['id']; ?>" class="icon-btn btn-edit" title="PDF İndir"><?php echo icon('download'); ?></a>
                                        <button onclick="deleteQuote(<?php echo $q['id']; ?>)" class="icon-btn btn-delete" title="Sil"><?php echo icon('trash'); ?></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-data">
                                <?php echo ($search || $year_filter !== '' || $month_filter !== '' || $status_filter !== '') ? "Filtrelere uygun teklif bulunamadı." : "Henüz teklif oluşturulmamış."; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Sayfalama -->
        <?php
        echo renderPagination($page, $total_pages, 'quotes.php', [
            'year' => $year_filter,
            'month' => $month_filter,
            'status' => $status_filter,
            'search' => $search,
        ]);
        ?>
    </div>

    <script>
        function updateQuoteStatus(id, status) {
            let url = 'toggle-quote-status.php?id=' + id + '&status=' + encodeURIComponent(status);
            url += '&return=' + encodeURIComponent('<?php echo $return_qs; ?>');
            window.location.href = url;
        }

        function deleteQuote(id) {
            if (confirm('Bu teklifi silmek istediğinizden emin misiniz?')) {
                window.location.href = 'delete-quote.php?id=' + id;
            }
        }
    </script>
</body>
</html>
