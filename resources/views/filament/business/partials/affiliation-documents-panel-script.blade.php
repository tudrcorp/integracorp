<script>
    window.affiliationDocumentsPanel = window.affiliationDocumentsPanel || function (config) {
        return {
            loading: false,
            loadingMessage: null,
            progressPercentage: null,
            processedJobs: null,
            totalJobs: null,
            etaSeconds: null,
            sendingEmail: false,
            regenerated: false,
            error: null,
            emailMessage: null,
            documents: [],
            optionalEmail: '',
            regenerateUrl: config.regenerateUrl,
            sendEmailUrl: config.sendEmailUrl,
            statusUrlTemplate: config.statusUrlTemplate || null,
            statusPollTimer: null,
            csrf() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            },
            stopPolling() {
                if (this.statusPollTimer) {
                    clearTimeout(this.statusPollTimer);
                    this.statusPollTimer = null;
                }
            },
            formatEta(seconds) {
                if (seconds === null || seconds === undefined) {
                    return 'Calculando...';
                }
                const value = Number(seconds);
                if (!Number.isFinite(value) || value < 0) {
                    return 'Calculando...';
                }
                if (value === 0) {
                    return 'Menos de 1s';
                }
                if (value < 60) {
                    return `${Math.ceil(value)}s`;
                }
                const totalMinutes = Math.floor(value / 60);
                const remSeconds = value % 60;
                if (totalMinutes < 60) {
                    return `${totalMinutes}m ${String(remSeconds).padStart(2, '0')}s`;
                }
                const hours = Math.floor(totalMinutes / 60);
                const minutes = totalMinutes % 60;
                return `${hours}h ${String(minutes).padStart(2, '0')}m`;
            },
            async pollStatus(taskId) {
                if (!this.statusUrlTemplate) {
                    throw new Error('No se encontró la URL para consultar el estado del proceso.');
                }
                this.loadingMessage = 'Procesando lotes de tarjetas. Puedes mantener esta ventana abierta.';
                const pollOnce = async () => {
                    const statusUrl = this.statusUrlTemplate.replace('__TASK_ID__', encodeURIComponent(taskId));
                    const res = await fetch(statusUrl, {
                        method: 'GET',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const data = await res.json().catch(() => ({}));

                    if (!res.ok || !data.ok) {
                        throw new Error(data.message || 'No se pudo consultar el estado de generación.');
                    }

                    if (data.status === 'completed') {
                        this.progressPercentage = 100;
                        this.processedJobs = data.processed_jobs ?? this.processedJobs;
                        this.totalJobs = data.total_jobs ?? this.totalJobs;
                        this.etaSeconds = 0;
                        this.documents = (data.documents || []).map((d) => {
                            const raw = d.preview_url || '';
                            const base = raw.split('#')[0];
                            return {
                                ...d,
                                previewUrl: base ? `${base}#toolbar=1` : '',
                            };
                        });
                        this.regenerated = true;
                        this.loadingMessage = null;
                        this.stopPolling();
                        return;
                    }

                    if (data.status === 'failed') {
                        throw new Error(data.message || 'No fue posible completar la generación.');
                    }

                    this.loadingMessage = data.message || this.loadingMessage;
                    this.progressPercentage = typeof data.progress_percentage === 'number' ? data.progress_percentage : this.progressPercentage;
                    this.processedJobs = typeof data.processed_jobs === 'number' ? data.processed_jobs : this.processedJobs;
                    this.totalJobs = typeof data.total_jobs === 'number' ? data.total_jobs : this.totalJobs;
                    this.etaSeconds = data.eta_seconds ?? this.etaSeconds;
                    this.statusPollTimer = setTimeout(pollOnce, 1500);
                };

                await pollOnce();
            },
            async regenerate() {
                this.loading = true;
                this.error = null;
                this.emailMessage = null;
                this.documents = [];
                this.loadingMessage = null;
                this.progressPercentage = null;
                this.processedJobs = null;
                this.totalJobs = null;
                this.etaSeconds = null;
                this.stopPolling();
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

                    if (data.queued === true && data.task_id) {
                        this.progressPercentage = typeof data.progress_percentage === 'number' ? data.progress_percentage : 0;
                        this.etaSeconds = data.eta_seconds ?? null;
                        await this.pollStatus(data.task_id);
                        return;
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
                    this.stopPolling();
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
