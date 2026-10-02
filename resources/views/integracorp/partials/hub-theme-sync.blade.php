<script>
    (() => {
        const applyIcHubThemeFromStorage = () => {
            try {
                const isLight = localStorage.getItem('ic-hub-theme') === 'light';
                document.documentElement.classList.toggle('ic-hub-theme-light', isLight);

                const body = document.querySelector('.ic-hub-body');
                if (body instanceof HTMLElement) {
                    body.style.colorScheme = isLight ? 'light' : 'dark';
                }
            } catch (error) {
                //
            }
        };

        applyIcHubThemeFromStorage();
        document.addEventListener('livewire:navigated', applyIcHubThemeFromStorage);
        document.addEventListener('DOMContentLoaded', applyIcHubThemeFromStorage);
    })();
</script>
