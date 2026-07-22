<?php
function renderPagination($current_page, $total_pages, $base_url, $additional_params = []) {
    if ($total_pages <= 1) return '';
    
    $output = '<div class="pagination">';
    
    // İlk sayfa
    if ($current_page > 1) {
        $output .= '<a href="' . $base_url . '?page=1' . buildQueryString($additional_params) . '" class="page-btn">« İlk</a>';
        $output .= '<a href="' . $base_url . '?page=' . ($current_page - 1) . buildQueryString($additional_params) . '" class="page-btn">‹ Önceki</a>';
    }
    
    // Sayfa numaraları
    $start = max(1, $current_page - 3);
    $end = min($total_pages, $current_page + 3);
    
    // İlk sayfa her zaman gösterilsin
    if ($start > 1) {
        $output .= '<a href="' . $base_url . '?page=1' . buildQueryString($additional_params) . '" class="page-btn">1</a>';
        if ($start > 2) {
            $output .= '<span class="page-dots">...</span>';
        }
    }
    
    // Orta sayfalar
    for ($i = $start; $i <= $end; $i++) {
        $active = $i == $current_page ? 'active' : '';
        $output .= '<a href="' . $base_url . '?page=' . $i . buildQueryString($additional_params) . '" class="page-btn ' . $active . '">' . $i . '</a>';
    }
    
    // Son sayfa her zaman gösterilsin
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $output .= '<span class="page-dots">...</span>';
        }
        $output .= '<a href="' . $base_url . '?page=' . $total_pages . buildQueryString($additional_params) . '" class="page-btn">' . $total_pages . '</a>';
    }
    
    // Son sayfa
    if ($current_page < $total_pages) {
        $output .= '<a href="' . $base_url . '?page=' . ($current_page + 1) . buildQueryString($additional_params) . '" class="page-btn">Sonraki ›</a>';
        $output .= '<a href="' . $base_url . '?page=' . $total_pages . buildQueryString($additional_params) . '" class="page-btn">Son »</a>';
    }
    
    $output .= '</div>';
    
    return $output;
}

function buildQueryString($params) {
    if (empty($params)) return '';
    
    $query = '';
    foreach ($params as $key => $value) {
        if (!empty($value)) {
            $query .= '&' . urlencode($key) . '=' . urlencode($value);
        }
    }
    return $query;
}
?>