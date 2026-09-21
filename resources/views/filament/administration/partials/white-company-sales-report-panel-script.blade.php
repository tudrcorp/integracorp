<script>
    window.whiteCompanySalesReportPanel = window.whiteCompanySalesReportPanel || function (config) {
        return {
            previewUrl: config.previewUrl,
            sendUrl: config.sendUrl,
            defaultRecipient: config.defaultRecipient || '',
            defaultPhone: config.defaultPhone || '',
            from: config.defaultFrom || '',
            to: config.defaultTo || '',
            loading: false,
            sending: false,
            error: null,
            successMessage: null,
            report: null,
            documentUrl: null,
            emptyMessage: null,
            recipients: [],
            newRecipient: '',
            phones: [],
            newPhone: '',
            csrf() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            },
            money(value) {
                return new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    .format(Number(value ?? 0)) + ' US$';
            },
            /** Un cambio de rango invalida la vista previa: no debe enviarse un PDF que ya no corresponde. */
            resetPreview() {
                this.report = null;
                this.documentUrl = null;
                this.emptyMessage = null;
                this.successMessage = null;
            },
            async generate() {
                if (! this.from || ! this.to) {
                    this.error = 'Indique el rango de fechas.';
                    return;
                }

                this.loading = true;
                this.error = null;
                this.resetPreview();

                try {
                    const res = await fetch(this.previewUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ from: this.from, to: this.to }),
                    });
                    const data = await res.json().catch(() => ({}));

                    if (!res.ok || !data.ok) {
                        throw new Error(data.message || 'No se pudo generar la vista previa.');
                    }

                    if (data.has_rows === false) {
                        this.emptyMessage = data.message;
                        return;
                    }

                    this.report = data;
                    this.documentUrl = (data.preview_url || '') + '#toolbar=1';

                    if (this.recipients.length === 0) {
                        const fallback = data.default_recipient || this.defaultRecipient;
                        if (fallback) {
                            this.recipients = [fallback];
                        }
                    }

                    if (this.phones.length === 0) {
                        const phone = data.default_phone || this.defaultPhone;
                        if (phone) {
                            this.phones = [phone];
                        }
                    }
                } catch (e) {
                    this.error = e.message || 'Error al generar la vista previa.';
                } finally {
                    this.loading = false;
                }
            },
            addRecipient() {
                const email = (this.newRecipient || '').trim().toLowerCase();

                if (email === '') {
                    return;
                }

                if (! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    this.error = 'El correo «' + email + '» no tiene un formato válido.';
                    return;
                }

                if (this.recipients.includes(email)) {
                    this.newRecipient = '';
                    return;
                }

                this.recipients.push(email);
                this.newRecipient = '';
                this.error = null;
            },
            removeRecipient(email) {
                this.recipients = this.recipients.filter((item) => item !== email);
            },
            addPhone() {
                const phone = (this.newPhone || '').trim().replace(/[^0-9+]/g, '');

                if (phone === '') {
                    return;
                }

                if (phone.replace(/\D/g, '').length < 7) {
                    this.error = 'El número «' + this.newPhone + '» es demasiado corto.';
                    return;
                }

                if (! this.phones.includes(phone)) {
                    this.phones.push(phone);
                }

                this.newPhone = '';
                this.error = null;
            },
            removePhone(phone) {
                this.phones = this.phones.filter((item) => item !== phone);
            },
            get totalDestinations() {
                return this.recipients.length + this.phones.length;
            },
            async send() {
                if (this.totalDestinations === 0) {
                    this.error = 'Agregue al menos un correo o un número de WhatsApp.';
                    return;
                }

                this.sending = true;
                this.error = null;
                this.successMessage = null;

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
                            from: this.from,
                            to: this.to,
                            recipients: this.recipients,
                            phones: this.phones,
                        }),
                    });
                    const data = await res.json().catch(() => ({}));

                    if (!res.ok || !data.ok) {
                        throw new Error(data.message || 'No se pudo enviar el reporte.');
                    }

                    this.successMessage = data.message;
                } catch (e) {
                    this.error = e.message || 'Error al enviar.';
                } finally {
                    this.sending = false;
                }
            },
        };
    };
</script>
