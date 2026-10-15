<script>
    /*
     * Panel del aviso de cobro (Administración).
     * - Con `config.form` (Gestión de Cobranza): primero el formulario editable; «Regenerar PDF» envía lo editado.
     * - Sin `config.form`: cada vista llama a regenerate() cuando corresponde, con los datos por defecto.
     * Las fechas viajan como dd/mm/aaaa; los campos de fecha del navegador usan aaaa-mm-dd.
     */
    window.avisoCobroPanel = function (config) {
        const toIso = (value) => {
            const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(String(value || '').trim());
            return match ? `${match[3]}-${match[2]}-${match[1]}` : '';
        };
        const toDisplay = (value) => {
            const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || '').trim());
            return match ? `${match[3]}/${match[2]}/${match[1]}` : '';
        };
        const form = config.form ? { ...config.form } : null;

        return {
            loading: false,
            sendingEmail: false,
            regenerated: false,
            editing: form !== null,
            error: null,
            emailMessage: null,
            previewUrl: '',
            directUrl: '',
            optionalEmail: '',
            fieldErrors: {},
            form,
            dates: form ? {
                emission_date: toIso(form.emission_date),
                period_from: toIso(form.period_from),
                period_to: toIso(form.period_to),
            } : {},
            regenerateUrl: config.regenerateUrl,
            sendEmailUrl: config.sendEmailUrl,
            csrf() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            },
            periodPreview() {
                const from = toDisplay(this.dates.period_from);
                const to = toDisplay(this.dates.period_to);
                return from && to ? `${from} AL ${to}` : '(complete las fechas)';
            },
            validateForm() {
                const errors = {};
                if (!String(this.form.full_name || '').trim()) errors.full_name = 'Escriba el nombre del cliente.';
                if (!this.dates.emission_date) errors.emission_date = 'Elija la fecha de emisión.';
                if (!this.dates.period_from) errors.period_from = 'Elija el inicio del período de cobro.';
                if (!this.dates.period_to) errors.period_to = 'Elija el fin del período de cobro.';
                if (this.dates.period_from && this.dates.period_to && this.dates.period_to < this.dates.period_from) {
                    errors.period_to = 'El fin del período debe ser igual o posterior al inicio.';
                }
                this.fieldErrors = errors;
                return Object.keys(errors).length === 0;
            },
            async regenerate() {
                if (this.loading) return;
                this.error = null;
                this.emailMessage = null;

                let body = null;
                if (this.form !== null) {
                    if (!this.validateForm()) return;
                    body = JSON.stringify({
                        notice: {
                            ...this.form,
                            emission_date: toDisplay(this.dates.emission_date),
                            period_from: toDisplay(this.dates.period_from),
                            period_to: toDisplay(this.dates.period_to),
                        },
                    });
                }

                this.loading = true;
                try {
                    const res = await fetch(this.regenerateUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body,
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) {
                        const errors = {};
                        for (const [key, messages] of Object.entries(data.errors || {})) {
                            errors[key.replace(/^notice\./, '')] = Array.isArray(messages) ? messages[0] : String(messages);
                        }
                        this.fieldErrors = errors;
                        throw new Error(res.status === 419
                            ? 'La sesión expiró. Recargue la página e intente de nuevo.'
                            : (data.message || 'No se pudo regenerar el PDF.'));
                    }
                    const raw = data.preview_url || data.direct_url || '';
                    const base = raw.split('#')[0];
                    this.previewUrl = base ? `${base}#toolbar=1` : '';
                    this.directUrl = data.direct_url || data.preview_url || '';
                    this.fieldErrors = {};
                    this.regenerated = true;
                    this.editing = false;
                } catch (e) {
                    this.error = e.message || 'Error al regenerar.';
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
