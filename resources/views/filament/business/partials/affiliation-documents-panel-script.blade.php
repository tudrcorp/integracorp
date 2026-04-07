<script>
    window.affiliationDocumentsPanel = window.affiliationDocumentsPanel || function (config) {
        return {
            loading: false,
            sendingEmail: false,
            regenerated: false,
            error: null,
            emailMessage: null,
            documents: [],
            optionalEmail: '',
            regenerateUrl: config.regenerateUrl,
            sendEmailUrl: config.sendEmailUrl,
            csrf() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            },
            async regenerate() {
                this.loading = true;
                this.error = null;
                this.emailMessage = null;
                this.documents = [];
                try {
                    const res = await fetch(this.regenerateUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': this.csrf(),
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) {
                        throw new Error(data.message || 'No se pudieron generar los documentos.');
                    }
                    this.documents = (data.documents || []).map((d) => {
                        const raw = d.preview_url || '';
                        const base = raw.split('#')[0];
                        return {
                            ...d,
                            previewUrl: base ? `${base}#toolbar=1` : '',
                        };
                    });
                    this.regenerated = true;
                } catch (e) {
                    this.error = e.message || 'Error al generar.';
                } finally {
                    this.loading = false;
                }
            },
            async sendEmail() {
                this.sendingEmail = true;
                this.error = null;
                this.emailMessage = null;
                try {
                    const body = {};
                    if (this.optionalEmail && this.optionalEmail.trim() !== '') {
                        body.email = this.optionalEmail.trim();
                    }
                    const res = await fetch(this.sendEmailUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(body),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) {
                        throw new Error(data.message || 'No se pudo enviar el correo.');
                    }
                    this.emailMessage = data.message || 'Enviado.';
                } catch (e) {
                    this.error = e.message || 'Error al enviar.';
                } finally {
                    this.sendingEmail = false;
                }
            },
        };
    };
</script>
