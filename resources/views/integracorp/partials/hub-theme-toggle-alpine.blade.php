<script>
    (() => {
        const registerIcHubThemeToggle = () => {
            if (typeof Alpine === 'undefined' || typeof Alpine.data !== 'function') {
                return;
            }

            Alpine.data('icHubThemeToggle', () => ({
                theme: 'dark',
                jelly: false,
                jellyTimeout: null,

                init() {
                    this.theme = this.readTheme();
                    this.apply(false);
                },

                readTheme() {
                    try {
                        return localStorage.getItem('ic-hub-theme') === 'light' ? 'light' : 'dark';
                    } catch (error) {
                        return 'dark';
                    }
                },

                toggle() {
                    this.theme = this.theme === 'dark' ? 'light' : 'dark';

                    try {
                        localStorage.setItem('ic-hub-theme', this.theme);
                    } catch (error) {
                        //
                    }

                    this.triggerJelly();
                    this.apply(true);
                },

                triggerJelly() {
                    this.jelly = false;

                    if (this.jellyTimeout !== null) {
                        window.clearTimeout(this.jellyTimeout);
                    }

                    this.$nextTick(() => {
                        this.jelly = true;
                        this.jellyTimeout = window.setTimeout(() => {
                            this.jelly = false;
                            this.jellyTimeout = null;
                        }, 680);
                    });
                },

                apply(pulse) {
                    const isLight = this.theme === 'light';

                    const commitTheme = () => {
                        document.documentElement.classList.toggle('ic-hub-theme-light', isLight);

                        const body = document.querySelector('.ic-hub-body');
                        if (body instanceof HTMLElement) {
                            body.style.colorScheme = isLight ? 'light' : 'dark';
                        }
                    };

                    if (pulse) {
                        const isAuthHub = document.body?.classList.contains('ic-hub-body--auth') === true;

                        if (isAuthHub) {
                            commitTheme();

                            return;
                        }

                        document.documentElement.classList.add('ic-hub-theme-switching');

                        requestAnimationFrame(() => {
                            requestAnimationFrame(() => {
                                commitTheme();
                                window.setTimeout(() => {
                                    document.documentElement.classList.remove('ic-hub-theme-switching');
                                }, 1100);
                            });
                        });

                        return;
                    }

                    commitTheme();
                },
            }));
        };

        document.addEventListener('alpine:init', registerIcHubThemeToggle);

        if (window.Alpine) {
            registerIcHubThemeToggle();
        }
    })();
</script>
