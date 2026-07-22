<?php
/**
 * Ortak sidebar / mobil menü.
 * Kullanım: include etmeden önce $active_page tanımla (örn. 'dashboard', 'customers').
 */
require_once __DIR__ . '/icons.php';
$active_page = $active_page ?? '';

$nav_items = [
    ['page' => 'dashboard',          'href' => 'dashboard.php',          'icon' => 'home',    'label' => 'Ana Sayfa'],
    ['page' => 'customers',          'href' => 'customers.php',          'icon' => 'users',   'label' => 'Müşteriler'],
    ['page' => 'products',           'href' => 'products.php',           'icon' => 'package', 'label' => 'Ürünler'],
    ['page' => 'simcards',           'href' => 'simcards.php',           'icon' => 'sim',     'label' => 'Sim Kartlar'],
    ['page' => 'create-sale',        'href' => 'create-sale.php',        'icon' => 'dollar',  'label' => 'Satış'],
    ['page' => 'sales-list',         'href' => 'sales-list.php',         'icon' => 'list',    'label' => 'Satış Listesi'],
    ['page' => 'bulk-sales-upload',  'href' => 'bulk-sales-upload.php',  'icon' => 'upload',  'label' => 'Toplu Satış Yükle'],
    ['page' => 'subscriptions',      'href' => 'subscriptions.php',      'icon' => 'refresh', 'label' => 'Abonelikler'],
];
?>
<button class="mobile-menu-btn" onclick="toggleMenu()">☰</button>
<div class="sidebar-overlay" onclick="toggleMenu()"></div>

<div class="sidebar">
    <div class="logo-sidebar">
        <img src="assets/images/logo-light.png" alt="MYCB">
    </div>
    <nav class="nav-menu">
        <?php foreach ($nav_items as $item): ?>
            <a href="<?php echo $item['href']; ?>" class="nav-item<?php echo $active_page === $item['page'] ? ' active' : ''; ?>">
                <?php echo icon($item['icon']); ?>
                <span><?php echo $item['label']; ?></span>
            </a>
        <?php endforeach; ?>
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
