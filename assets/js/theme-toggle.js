/**
 * assets/js/theme-toggle.js
 * Handles global theme switching from the sidebar
 */

document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('sidebarThemeToggle');
    const sunIcon = document.getElementById('sidebarSunIcon');
    const moonIcon = document.getElementById('sidebarMoonIcon');
    const themeText = document.getElementById('sidebarThemeText');
    const htmlElement = document.documentElement;

    // Check saved theme
    const currentTheme = localStorage.getItem('theme') || 'dark';
    updateUI(currentTheme);

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            const isLight = htmlElement.getAttribute('data-theme') === 'light';
            const newTheme = isLight ? 'dark' : 'light';

            setTheme(newTheme);
        });
    }

    function setTheme(theme) {
        if (theme === 'light') {
            htmlElement.setAttribute('data-theme', 'light');
            localStorage.setItem('theme', 'light');
        } else {
            htmlElement.removeAttribute('data-theme');
            localStorage.setItem('theme', 'dark');
        }
        updateUI(theme);
    }

    function updateUI(theme) {
        if (!sunIcon || !moonIcon) return;

        if (theme === 'light') {
            sunIcon.style.display = 'inline-block';
            moonIcon.style.display = 'none';
            if (themeText) themeText.textContent = 'Light Mode';
        } else {
            sunIcon.style.display = 'none';
            moonIcon.style.display = 'inline-block';
            if (themeText) themeText.textContent = 'Dark Mode';
        }
    }
});
