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
            showPreview: false,
            generatedDesde: null,
            generatedHasta: null,
            emailOptions: config.emailOptions || null,
            sendUrl: config.sendUrl || '',
            affiliateTemplateId: config.emailOptions?.affiliate?.default_id ? String(config.emailOptions.affiliate.default_id) : '',
            responsibleTemplateId: config.emailOptions?.responsible?.default_id ? String(config.emailOptions.responsible.default_id) : '',
            sending: false,
            confirming: false,
            testEmail: config.testEmailDefault || '',
            sendingTest: false,
            testMessage: null,
            testError: null,
            sendError: null,
            sendSuccess: null,
            get isStale() {
                return !isViewOnly
                    && this.regenerated
                    && (this.desde !== this.generatedDesde || this.hasta !== this.generatedHasta);
            },
            templateFor(kind) {
                const id = kind === 'affiliate' ? this.affiliateTemplateId : this.responsibleTemplateId;
                const templates = this.emailOptions?.[kind]?.templates || [];

                return templates.find((template) => String(template.id) === String(id)) || null;
            },
            hasEmails(kind) {
                return (this.emailOptions?.[kind]?.emails || []).length > 0;
            },
            get canSend() {
                return this.regenerated
                    && !this.isStale
                    && !this.sending
                    && (this.templateFor('affiliate') !== null || this.templateFor('responsible') !== null);
            },
            get canTest() {
                return this.regenerated
                    && !this.isStale
                    && !this.sendingTest
                    && !this.sending
                    && (this.templateFor('affiliate') !== null || this.templateFor('responsible') !== null);
            },
            async sendTest() {
                this.testError = null;
                this.testMessage = null;

                if (this.isStale) {
                    this.testError = 'Cambió el periodo de vigencia. Genere de nuevo el recibo antes de probar.';
                    return;
                }
                if (this.templateFor('affiliate') === null && this.templateFor('responsible') === null) {
                    this.testError = 'Elija el copy de al menos un destinatario para probarlo.';
                    return;
                }
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.testEmail.trim())) {
                    this.testError = 'Escriba un correo de prueba válido.';
                    return;
                }

                this.sendingTest = true;
                try {
                    const res = await fetch(this.sendUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            affiliate_template_id: this.templateFor('affiliate')?.id ?? null,
                            responsible_template_id: this.templateFor('responsible')?.id ?? null,
                            test_email: this.testEmail.trim(),
                        }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) {
                        const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                        throw new Error(res.status === 419
                            ? 'La sesión expiró. Recargue la página e intente de nuevo.'
                            : (firstError || data.message || 'No se pudo enviar la prueba.'));
                    }
                    this.testMessage = data.message || 'Prueba enviada.';
                } catch (e) {
                    this.testError = e.message || 'No se pudo enviar la prueba.';
                } finally {
                    this.sendingTest = false;
                }
            },
            startSend() {
                this.sendError = null;
                this.sendSuccess = null;

                if (this.isStale) {
                    this.sendError = 'Cambió el periodo de vigencia. Genere de nuevo la vista previa antes de enviar.';

                    return;
                }

                if (this.templateFor('affiliate') === null && this.templateFor('responsible') === null) {
                    this.sendError = 'Elija el copy de al menos un destinatario: el afiliado o el agente / agencia.';

                    return;
                }

                this.confirming = true;
            },
            async send() {
                if (!this.canSend) {
                    return;
                }

                this.sending = true;
                this.sendError = null;

                try {
                    const res = await fetch(this.sendUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            affiliate_template_id: this.templateFor('affiliate')?.id ?? null,
                            responsible_template_id: this.templateFor('responsible')?.id ?? null,
                        }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) {
                        const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                        throw new Error(firstError || data.message || 'No se pudo enviar el recibo.');
                    }
                    this.sendSuccess = data.message || 'Recibo enviado.';
                    this.confirming = false;
                    if (window.FilamentNotification) {
                        new window.FilamentNotification().title('Recibo enviado').body(this.sendSuccess).success().send();
                    }
                } catch (e) {
                    this.sendError = e.message || 'No se pudo enviar el recibo.';
                    this.confirming = false;
                } finally {
                    this.sending = false;
                }
            },
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
                    this.generatedDesde = this.desde;
                    this.generatedHasta = this.hasta;
                    this.sendSuccess = null;
                    this.sendError = null;
                    this.testMessage = null;
                    this.testError = null;
                    this.confirming = false;
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
