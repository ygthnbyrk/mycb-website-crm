<?php
/**
 * Minimal SVG ikon seti (Feather-stili, stroke tabanlı, MIT lisans mantığıyla elle yazıldı).
 * Emoji yerine kullanılır. Harici bağımlılık / CDN yok.
 */
function icon($name, $class = '') {
    $paths = [
        'home'          => '<path d="M3 12l9-9 9 9"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
        'users'         => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'package'       => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
        'sim'           => '<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M9 7h6v6H9z"/>',
        'dollar'        => '<circle cx="12" cy="12" r="10"/><path d="M12 6v12"/><path d="M15.5 9.5c0-1.4-1.6-2.5-3.5-2.5s-3.5 1-3.5 2.3c0 3.2 7 1.5 7 4.7 0 1.3-1.6 2.3-3.5 2.3s-3.5-1.1-3.5-2.5"/>',
        'list'          => '<path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/>',
        'upload'        => '<path d="M12 3v13"/><path d="M7 8l5-5 5 5"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
        'refresh'       => '<path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.5 9a9 9 0 0 1 14.85-3.36L23 10"/><path d="M20.5 15a9 9 0 0 1-14.85 3.36L1 14"/>',
        'logout'        => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'search'        => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/>',
        'filter'        => '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>',
        'edit'          => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>',
        'trash'         => '<path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
        'x'             => '<path d="M18 6L6 18"/><path d="M6 6l12 12"/>',
        'check'         => '<path d="M20 6L9 17l-5-5"/>',
        'plus'          => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'clock'         => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'bell'          => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'menu'          => '<path d="M3 12h18"/><path d="M3 6h18"/><path d="M3 18h18"/>',
        'chevron-down'  => '<path d="M6 9l6 6 6-6"/>',
        'chevron-left'  => '<path d="M15 18l-6-6 6-6"/>',
        'building'      => '<rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01"/>',
    ];
    $body = $paths[$name] ?? $paths['check'];
    return '<svg class="' . htmlspecialchars($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $body . '</svg>';
}
