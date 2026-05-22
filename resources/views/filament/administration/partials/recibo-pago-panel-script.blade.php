<script>
    window.reciboPagoPanel = window.reciboPagoPanel || function (config) {
        const isViewOnly = config.mode === 'view';
        const initialPreview = config.previewUrl || '';
        const initialBase = initialPreview ? initialPreview.split('#')[0] : '';

        return {
            loading: false,
            regenerated: isViewOnly && !!initialBase,
            error: isViewOnly && !initialBase ? 'No existe el PDF del recibo. Regenérelo primero desde «Regenerar PDF».' : null,
            previewUrl: initialBase ? `${initialBase}#toolbar=1` : '',
            desde: config.desdeDefault || '',
            hasta: config.hastaDefault || '',
            regenerateUrl: config.regenerateUrl || '',
            csrf() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            },
            async regenerate() {
                if (!this.desde || !this.hasta) {
                    this.error = 'Indique el periodo de vigencia (desde y hasta).';

                    return;
                }

                this.loading = true;
                this.error = null;
                this.regenerated = false;

                try {
                    const res = await fetch(this.regenerateUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            desde: this.desde,
                            hasta: this.hasta,
                        }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) {
                        throw new Error(data.message || 'No se pudo regenerar el PDF.');
                    }
                    const raw = data.preview_url || data.direct_url || '';
                    const base = raw.split('#')[0];
                    this.previewUrl = base ? `${base}#toolbar=1` : '';
                    this.regenerated = true;
                } catch (e) {
                    this.error = e.message || 'Error al regenerar.';
                } finally {
                    this.loading = false;
                }
            },
        };
    };
</script>
