<script>
    /*
     * Panel del aviso de cobro (Administración).
     * - Con `config.form` (Gestión de Cobranza): primero el formulario editable; «Regenerar PDF» envía lo editado.
     * - Sin `config.form`: cada vista llama a regenerate() cuando corresponde, con los datos por defecto.
     * Las fechas viajan como dd/mm/aaaa; los campos de fecha del navegador usan aaaa-mm-dd.
     * - Envío: cada regeneración devuelve los copys ya renderizados con los datos de ese PDF
     *   (`email_options`); el analista elige uno por destinatario y confirma antes de enviar.
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
        const pickTemplate = (options, kind, current) => {
            const templates = options?.[kind]?.templates || [];
            if (current && templates.some((template) => String(template.id) === String(current))) {
                return String(current);
            }
            return options?.[kind]?.default_id ? String(options[kind].default_id) : '';
        };

        return {
            loading: false,
            sendingEmail: false,
            regenerated: false,
            editing: form !== null,
            error: null,
            emailMessage: null,
            previewUrl: '',
            directUrl: '',
            fieldErrors: {},
            showPreview: false,
            emailOptions: config.emailOptions || null,
            affiliateTemplateId: pickTemplate(config.emailOptions, 'affiliate', ''),
            responsibleTemplateId: pickTemplate(config.emailOptions, 'responsible', ''),
            attachPaymentMethods: !!config.emailOptions?.payment_methods_available,
            testEmail: config.testEmailDefault || '',
            sendingTest: false,
            testMessage: null,
            testError: null,
            confirming: false,
            emailError: null,
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
            applyEmailOptions(options) {
                if (!options) {
                    return;
                }
                const firstLoad = this.emailOptions === null;
                this.emailOptions = options;
                this.affiliateTemplateId = pickTemplate(options, 'affiliate', this.affiliateTemplateId);
                this.responsibleTemplateId = pickTemplate(options, 'responsible', this.responsibleTemplateId);
                if (!options.payment_methods_available) {
                    this.attachPaymentMethods = false;
                } else if (firstLoad) {
                    this.attachPaymentMethods = true;
                }
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
                    && !this.editing
                    && !this.sendingEmail
                    && this.emailOptions !== null
                    && (this.templateFor('affiliate') !== null || this.templateFor('responsible') !== null);
            },
            confirmationLines() {
                const lines = [];
                for (const kind of ['affiliate', 'responsible']) {
                    const template = this.templateFor(kind);
                    if (!template) continue;
                    const to = (this.emailOptions[kind].emails || []).join(', ');
                    lines.push(`${this.emailOptions[kind].label} → ${to || 'sin correo'} con «${template.name}»`);
                }
                lines.push(this.attachPaymentMethods ? 'Con el PDF de métodos de pago adjunto.' : 'Sin métodos de pago adjuntos.');
                return lines;
            },
            get canTest() {
                return this.regenerated
                    && !this.editing
                    && !this.sendingTest
                    && !this.sendingEmail
                    && this.emailOptions !== null
                    && (this.templateFor('affiliate') !== null || this.templateFor('responsible') !== null);
            },
            async sendTest() {
                this.testError = null;
                this.testMessage = null;

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
                    const res = await fetch(this.sendEmailUrl, {
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
                            attach_payment_methods: this.attachPaymentMethods,
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
                this.emailError = null;
                this.emailMessage = null;

                if (this.templateFor('affiliate') === null && this.templateFor('responsible') === null) {
                    this.emailError = 'Elija el copy de al menos un destinatario: el afiliado o el agente / agencia.';
                    return;
                }

                for (const kind of ['affiliate', 'responsible']) {
                    if (this.templateFor(kind) && !this.hasEmails(kind)) {
                        this.emailError = `${this.emailOptions[kind].label}: no tiene un correo válido. Elija «No enviar» o corrija el correo en «Editar datos».`;
                        return;
                    }
                }

                this.confirming = true;
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
                    this.applyEmailOptions(data.email_options);
                    this.confirming = false;
                    this.testMessage = null;
                    this.testError = null;
                    this.regenerated = true;
                    this.editing = false;
                } catch (e) {
                    this.error = e.message || 'Error al regenerar.';
                } finally {
                    this.loading = false;
                }
            },
            async sendEmail() {
                if (!this.canSend) return;

                this.sendingEmail = true;
                this.emailError = null;
                this.emailMessage = null;
                try {
                    const res = await fetch(this.sendEmailUrl, {
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
                            attach_payment_methods: this.attachPaymentMethods,
                        }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) {
                        const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                        throw new Error(res.status === 419
                            ? 'La sesión expiró. Recargue la página e intente de nuevo.'
                            : (firstError || data.message || 'No se pudo enviar el aviso.'));
                    }
                    this.emailMessage = data.message || 'Aviso enviado.';
                    if (window.FilamentNotification) {
                        new window.FilamentNotification().title('Aviso enviado').body(this.emailMessage).success().send();
                    }
                } catch (e) {
                    this.emailError = e.message || 'No se pudo enviar el aviso.';
                } finally {
                    this.confirming = false;
                    this.sendingEmail = false;
                }
            },
        };
    };
</script>
