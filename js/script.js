// Keep Bootstrap's theme aligned with system preference unless a manual override is stored.
document.addEventListener('DOMContentLoaded', function () {
    var mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    var textSizeSelector = document.getElementById('text-size-selector');

    function getStoredTheme() {
        try {
            return localStorage.getItem('theme');
        } catch (error) {
            return null;
        }
    }

    function getStoredTextSize() {
        try {
            return localStorage.getItem('text_size');
        } catch (error) {
            return null;
        }
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
    }

    function applyTextSize(size) {
        var normalizedSize = size;
        if (normalizedSize !== 'large' && normalizedSize !== 'x-large') {
            normalizedSize = 'default';
        }

        document.documentElement.setAttribute('data-text-size', normalizedSize);
        if (textSizeSelector) {
            textSizeSelector.value = normalizedSize;
        }
    }

    function syncTheme() {
        var storedTheme = getStoredTheme();
        if (storedTheme === 'light' || storedTheme === 'dark') {
            applyTheme(storedTheme);
            return;
        }

        applyTheme(mediaQuery.matches ? 'dark' : 'light');
    }

    syncTheme();
    applyTextSize(getStoredTextSize());

    if (typeof mediaQuery.addEventListener === 'function') {
        mediaQuery.addEventListener('change', syncTheme);
    } else if (typeof mediaQuery.addListener === 'function') {
        mediaQuery.addListener(syncTheme);
    }

    if (textSizeSelector) {
        textSizeSelector.addEventListener('change', function (event) {
            var size = event.target.value;
            applyTextSize(size);

            try {
                localStorage.setItem('text_size', size);
            } catch (error) {
                // Ignore storage failures and keep the active in-memory setting.
            }
        });
    }
});
