// Sayfa yüklendiğinde tema kontrolü
document.addEventListener('DOMContentLoaded', function() {
    const theme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', theme);
    updateLogo(theme);
    updateThemeIcon(theme);
});

// Tema değiştirme
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateLogo(newTheme);
    updateThemeIcon(newTheme);
}

// Logo güncelleme
function updateLogo(theme) {
    const logo = document.getElementById('logo');
    if (logo) {
        logo.src = theme === 'dark' 
            ? 'assets/images/logo-dark.png' 
            : 'assets/images/logo-light.png';
    }
}

// Tema ikonu güncelleme
function updateThemeIcon(theme) {
    const themeToggle = document.querySelector('.theme-toggle');
    if (themeToggle) {
        themeToggle.innerHTML = theme === 'dark' ? '☀️' : '🌙';
    }
}