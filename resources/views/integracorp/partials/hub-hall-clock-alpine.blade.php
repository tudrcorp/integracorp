<script>
    (() => {
        const registerIcHubHallClock = () => {
            if (typeof Alpine === 'undefined' || typeof Alpine.data !== 'function') {
                return;
            }

            Alpine.data('icHubHallClock', () => ({
                dateLabel: '',
                timeLabel: '',
                intervalId: null,

                init() {
                    this.tick();
                    this.intervalId = window.setInterval(() => this.tick(), 20_000);
                },

                tick() {
                    const now = new Date();
                    const dateRaw = now.toLocaleDateString('es-VE', {
                        weekday: 'long',
                        day: 'numeric',
                        month: 'long',
                    });
                    this.dateLabel = dateRaw.toLocaleUpperCase('es-VE');
                    const timeRaw = now.toLocaleTimeString('es-VE', {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: true,
                    });
                    this.timeLabel = timeRaw.replace(/\s*a\.?\s*m\.?/i, ' A.M.').replace(/\s*p\.?\s*m\.?/i, ' P.M.').toUpperCase();
                },

                destroy() {
                    if (this.intervalId !== null) {
                        window.clearInterval(this.intervalId);
                        this.intervalId = null;
                    }
                },
            }));
        };

        document.addEventListener('alpine:init', registerIcHubHallClock);

        if (window.Alpine) {
            registerIcHubHallClock();
        }
    })();
</script>
