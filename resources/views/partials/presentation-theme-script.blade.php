<script>
    (function () {
        const storageKey = 'systems_panel_theme';

        function readTheme() {
            try {
                const saved = localStorage.getItem(storageKey);
                return (saved === 'dark' || saved === 'light') ? saved : 'light';
            } catch (e) {
                return 'light';
            }
        }

        function applyTheme(theme) {
            const next = theme === 'dark' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', next);
            document.documentElement.style.colorScheme = next;

            const metaColor = document.querySelector('meta[data-theme-color]');
            const metaScheme = document.querySelector('meta[name="color-scheme"]');
            const metaStatus = document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]');

            if (metaColor) {
                metaColor.setAttribute('content', next === 'dark' ? '#050505' : '#F5F5F7');
            }
            if (metaScheme) {
                metaScheme.setAttribute('content', next);
            }
            if (metaStatus) {
                metaStatus.setAttribute('content', next === 'dark' ? 'black-translucent' : 'default');
            }

            try {
                localStorage.setItem(storageKey, next);
            } catch (e) {
                // ignore storage errors
            }
        }

        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            applyTheme(current === 'dark' ? 'light' : 'dark');
        }

        applyTheme(readTheme());

        document.querySelectorAll('[data-presentation-theme-toggle]').forEach((button) => {
            if (button.dataset.themeReady) {
                return;
            }
            button.dataset.themeReady = '1';
            button.addEventListener('click', toggleTheme);
        });
    })();
</script>
