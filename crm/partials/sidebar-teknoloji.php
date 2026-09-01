<?php
/**
 * Teknoloji alanının kendi sidebar'ı — Araç Takip (partials/sidebar.php) ile
 * karışmasın diye tamamen ayrı bir menü. Kullanım: include etmeden önce
 * $active_page tanımla (örn. 'teknoloji-urunler', 'create-camera-sale').
 */
require_once __DIR__ . '/icons.php';
$active_page = $active_page ?? '';
$_SESSION['crm_zone'] = 'teknoloji';

$nav_items = [
    ['page' => 'teknoloji',          'href' => 'teknoloji.php',          'icon' => 'home',  'label' => 'Ana Sayfa'],
    ['page' => 'customers',          'href' => 'customers.php',          'icon' => 'users', 'label' => 'Müşteriler'],
    ['page' => 'teknoloji-urunler',  'href' => 'teknoloji-urunler.php',  'icon' => 'cpu',   'label' => 'Ürünler'],
    ['page' => 'create-camera-sale', 'href' => 'create-camera-sale.php', 'icon' => 'dollar','label' => 'Kamera Satışı'],
    ['page' => 'camera-sales-list',  'href' => 'camera-sales-list.php',  'icon' => 'list',  'label' => 'Kamera Satış Listesi'],
];
?>
<button class="mobile-menu-btn" onclick="toggleMenu()">☰</button>
<div class="sidebar-overlay" onclick="toggleMenu()"></div>

<div class="sidebar">
    <div class="logo-sidebar">
        <img src="assets/images/logo-light.png" alt="MYCB">
    </div>
    <div style="padding: 0 16px 12px;">
        <span class="badge badge-gray" style="display:inline-block;">Teknoloji</span>
    </div>
    <nav class="nav-menu">
        <?php foreach ($nav_items as $item):
            $is_active = in_array($active_page, $item['group'] ?? [$item['page']], true);
        ?>
            <a href="<?php echo $item['href']; ?>" class="nav-item<?php echo $is_active ? ' active' : ''; ?>">
                <?php echo icon($item['icon']); ?>
                <span><?php echo $item['label']; ?></span>
            </a>
        <?php endforeach; ?>
        <a href="dashboard.php" class="nav-item nav-switch">
            <?php echo icon('chevron-left'); ?>
            <span>Araç Takip'e Dön</span>
        </a>
        <a href="logout.php" class="nav-item nav-logout">
            <?php echo icon('logout'); ?>
            <span>Çıkış Yap</span>
        </a>
    </nav>
</div>

<script>
function toggleMenu() {
    document.querySelector('.sidebar')?.classList.toggle('active');
    document.querySelector('.sidebar-overlay')?.classList.toggle('active');
}
document.addEventListener('click', function (event) {
    if (window.innerWidth <= 768) {
        const sidebar = document.querySelector('.sidebar');
        const menuBtn = document.querySelector('.mobile-menu-btn');
        if (sidebar && menuBtn && !sidebar.contains(event.target) && !menuBtn.contains(event.target)) {
            sidebar.classList.remove('active');
            document.querySelector('.sidebar-overlay')?.classList.remove('active');
        }
    }
});
</script>
