<script>
    (() => {
        const registerIcHubModulesCarousel = () => {
            if (typeof Alpine === 'undefined' || typeof Alpine.data !== 'function') {
                return;
            }

            Alpine.data('icHubModulesCarousel', () => ({
                activeIndex: 0,
                intervalId: null,
                moduleCount: 0,
                userPaused: false,
                accordionEnabled: false,

                init() {
                    const cards = this.$el.querySelectorAll('.ic-module-card');
                    this.moduleCount = cards.length;

                    this.accordionEnabled = this.moduleCount > 1
                        && window.matchMedia('(min-width: 900px)').matches
                        && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                    if (this.moduleCount <= 1) {
                        cards.forEach((card) => card.classList.add('ic-module-card--spotlight'));

                        return;
                    }

                    cards.forEach((card, index) => {
                        card.addEventListener('mouseenter', () => {
                            this.userPaused = true;
                            this.stopInterval();
                            this.setActive(index);
                        });

                        card.addEventListener('mouseleave', () => {
                            this.userPaused = false;
                            this.applySpotlight();
                            this.ensureInterval();
                        });

                        card.addEventListener('focusin', () => {
                            this.userPaused = true;
                            this.stopInterval();
                            this.setActive(index);
                        });

                        card.addEventListener('focusout', () => {
                            this.userPaused = false;
                            this.applySpotlight();
                            this.ensureInterval();
                        });
                    });

                    this.applySpotlight();

                    if (this.accordionEnabled) {
                        this.ensureInterval();
                    }
                },

                setActive(index) {
                    if (index < 0 || index >= this.moduleCount) {
                        return;
                    }

                    this.activeIndex = index;
                    this.applySpotlight();
                },

                ensureInterval() {
                    if (! this.accordionEnabled || this.intervalId !== null || this.userPaused || this.moduleCount <= 1) {
                        return;
                    }

                    this.intervalId = window.setInterval(() => {
                        if (this.userPaused) {
                            return;
                        }

                        this.activeIndex = (this.activeIndex + 1) % this.moduleCount;
                        this.applySpotlight();
                    }, 3000);
                },

                stopInterval() {
                    if (this.intervalId === null) {
                        return;
                    }

                    window.clearInterval(this.intervalId);
                    this.intervalId = null;
                },

                applySpotlight() {
                    this.$el.querySelectorAll('.ic-module-card').forEach((card, index) => {
                        const isActive = index === this.activeIndex;
                        card.classList.toggle('ic-module-card--spotlight', isActive);
                        card.classList.toggle('ic-module-card--hall-collapsed', this.accordionEnabled && ! isActive);
                    });
                },

                destroy() {
                    this.stopInterval();
                    this.$el.querySelectorAll('.ic-module-card').forEach((card) => {
                        card.classList.remove('ic-module-card--spotlight', 'ic-module-card--hall-collapsed');
                    });
                },
            }));
        };

        document.addEventListener('alpine:init', registerIcHubModulesCarousel);

        if (window.Alpine) {
            registerIcHubModulesCarousel();
        }
    })();
</script>
