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
            sendingCarnetEmails: false,
            carnetEmailsQueued: false,
            regenerated: false,
            backgroundWorking: false,
            error: null,
            emailMessage: null,
            carnetEmailMessage: null,
            documents: [],
            activeDoc: null,
            optionalEmail: '',
            regenerateUrl: config.regenerateUrl,
            sendEmailUrl: config.sendEmailUrl,
            sendCarnetEmailsUrl: config.sendCarnetEmailsUrl || null,
            useIndividualAffiliateCardLayout: config.useIndividualAffiliateCardLayout === true,
            statusUrlTemplate: config.statusUrlTemplate || null,
            tarjetasUrl: config.tarjetasUrl || null,
            affiliatesCount: null,
            statusPollTimer: null,
            pollIntervalMs: 3000,
            tarjetaSearch: '',
            tarjetaDocuments: [],
            tarjetaTotal: 0,
            tarjetaPage: 1,
            tarjetaLastPage: 1,
            loadingTarjetas: false,
            tarjetaSearchTimer: null,
            csrf() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            },
            stopPolling() {
                if (this.statusPollTimer) {
                    clearTimeout(this.statusPollTimer);
                    this.statusPollTimer = null;
                }
            },
            /**
             * Conserva el `previewUrl` de los documentos que ya se estaban viendo:
             * el backend reescribe el parámetro de versión en cada consulta y, sin
             * esto, el iframe se recargaría en cada ciclo de polling.
             */
            hydrateDocuments(items) {
                const previous = new Map(this.documents.map((doc) => [doc.filename, doc.previewUrl]));

                this.documents = (items || []).map((d) => {
                    const raw = d.preview_url || '';
                    const base = raw.split('#')[0];
                    const existing = previous.get(d.filename);

                    return {
                        ...d,
                        previewUrl: existing || (base ? `${base}#toolbar=1` : ''),
                    };
                });

                if (this.documents.length === 0) {
                    this.activeDoc = null;

                    return;
                }

                const stillThere = this.activeDoc
                    ? this.documents.find((doc) => doc.filename === this.activeDoc.filename)
                    : null;

                this.activeDoc = stillThere || this.activeDoc || this.documents[0];
            },
            activeDocument() {
                return this.activeDoc || this.documents[0] || null;
            },
            isActiveDocument(doc) {
                return !! doc && !! this.activeDocument() && doc.filename === this.activeDocument().filename;
            },
            setActiveDocument(index) {
                if (typeof index !== 'number' || index < 0 || index >= this.documents.length) {
                    return;
                }

                this.activeDoc = this.documents[index];
            },
            selectDocument(doc) {
                if (! doc) {
                    return;
                }

                if (! doc.previewUrl) {
                    const base = (doc.preview_url || '').split('#')[0];
                    doc.previewUrl = base ? `${base}#toolbar=1` : '';
                }

                this.activeDoc = doc;
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
            searchTarjetas() {
                if (this.tarjetaSearchTimer) {
                    clearTimeout(this.tarjetaSearchTimer);
                }

                this.tarjetaSearchTimer = setTimeout(() => {
                    this.tarjetaPage = 1;
                    this.loadTarjetas();
                }, 350);
            },
            goToTarjetaPage(page) {
                if (page < 1 || page > this.tarjetaLastPage || page === this.tarjetaPage) {
                    return;
                }

                this.tarjetaPage = page;
                this.loadTarjetas();
            },
            async loadTarjetas() {
                if (! this.tarjetasUrl) {
                    return;
                }

                this.loadingTarjetas = true;

                try {
                    const url = new URL(this.tarjetasUrl, window.location.origin);
                    url.searchParams.set('page', this.tarjetaPage);
                    if (this.tarjetaSearch.trim() !== '') {
                        url.searchParams.set('q', this.tarjetaSearch.trim());
                    }

                    const res = await fetch(url.toString(), {
                        method: 'GET',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const data = await res.json().catch(() => ({}));

                    if (!res.ok || !data.ok) {
                        throw new Error(data.message || 'No se pudieron cargar los carnets.');
                    }

                    this.tarjetaDocuments = (data.documents || []).map((doc) => {
                        const base = (doc.preview_url || '').split('#')[0];

                        return { ...doc, previewUrl: base ? `${base}#toolbar=1` : '' };
                    });
                    this.tarjetaTotal = data.total ?? 0;
                    this.tarjetaLastPage = data.last_page ?? 1;
                } catch (e) {
                    this.error = e.message || 'Error al cargar los carnets.';
                } finally {
                    this.loadingTarjetas = false;
                }
            },
            applyStatusPayload(data) {
                this.progressPercentage = typeof data.progress_percentage === 'number' ? data.progress_percentage : this.progressPercentage;
                this.processedJobs = typeof data.processed_jobs === 'number' ? data.processed_jobs : this.processedJobs;
                this.totalJobs = typeof data.total_jobs === 'number' ? data.total_jobs : this.totalJobs;
                this.etaSeconds = data.eta_seconds ?? this.etaSeconds;
                this.affiliatesCount = typeof data.affiliates_count === 'number' ? data.affiliates_count : this.affiliatesCount;

                if ((data.documents || []).length > 0) {
                    this.hydrateDocuments(data.documents);
                    this.regenerated = true;
                }
            },
            async pollStatus(taskId) {
                if (!this.statusUrlTemplate) {
                    throw new Error('No se encontró la URL para consultar el estado del proceso.');
                }
                this.loadingMessage = 'Preparando el certificado y los carnets...';
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

                    if (data.status === 'failed') {
                        throw new Error(data.message || 'No fue posible completar la generación.');
                    }

                    this.applyStatusPayload(data);

                    if (data.status === 'completed') {
                        this.progressPercentage = 100;
                        this.processedJobs = data.total_jobs ?? this.processedJobs;
                        this.etaSeconds = 0;
                        this.backgroundWorking = false;
                        this.loadingMessage = null;
                        this.stopPolling();
                        this.loadTarjetas();

                        return;
                    }

                    /**
                     * La vista previa se muestra en cuanto están el certificado y el
                     * PDF de carnets; el resto de los carnets sigue en segundo plano.
                     */
                    this.backgroundWorking = this.regenerated;
                    this.loadingMessage = data.message || this.loadingMessage;
                    this.statusPollTimer = setTimeout(pollOnce, this.pollIntervalMs);
                };

                await pollOnce();
            },
            async regenerate() {
                this.loading = true;
                this.error = null;
                this.emailMessage = null;
                this.carnetEmailMessage = null;
                this.documents = [];
                this.tarjetaDocuments = [];
                this.tarjetaPage = 1;
                this.tarjetaSearch = '';
                this.loadingMessage = null;
                this.progressPercentage = null;
                this.processedJobs = null;
                this.totalJobs = null;
                this.etaSeconds = null;
                this.backgroundWorking = false;
                this.activeDoc = null;
                this.stopPolling();
                try {
                    const regenerateHeaders = {
                        'X-CSRF-TOKEN': this.csrf(),
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    };
                    const regenerateOptions = {
                        method: 'POST',
                        headers: regenerateHeaders,
                    };

                    if (this.useIndividualAffiliateCardLayout) {
                        regenerateHeaders['Content-Type'] = 'application/json';
                        regenerateOptions.body = JSON.stringify({
                            use_individual_affiliate_card_layout: true,
                        });
                    }

                    const res = await fetch(this.regenerateUrl, regenerateOptions);
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) {
                        throw new Error(data.message || 'No se pudieron generar los documentos.');
                    }

                    if (typeof data.affiliates_count === 'number') {
                        this.affiliatesCount = data.affiliates_count;
                    }

                    if (data.queued === true && data.task_id) {
                        this.progressPercentage = typeof data.progress_percentage === 'number' ? data.progress_percentage : 0;
                        this.etaSeconds = data.eta_seconds ?? null;
                        await this.pollStatus(data.task_id);
                        return;
                    }

                    this.hydrateDocuments(data.documents || []);
                    this.regenerated = true;
                    this.loadTarjetas();
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
            async sendCarnetEmails() {
                if (! this.sendCarnetEmailsUrl) {
                    this.error = 'No se encontró la URL para enviar carnets a los afiliados.';

                    return;
                }

                this.sendingCarnetEmails = true;
                this.error = null;
                this.carnetEmailMessage = null;

                try {
                    const res = await fetch(this.sendCarnetEmailsUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({}),
                    });
                    const data = await res.json().catch(() => ({}));

                    if (!res.ok || !data.ok) {
                        throw new Error(data.message || 'No se pudieron encolar los correos.');
                    }

                    this.carnetEmailsQueued = true;
                    this.carnetEmailMessage = data.message
                        || 'Los carnets se envían en segundo plano. Puede cerrar esta ventana y seguir trabajando. Le avisaremos en la campanita cuando termine.';
                    this.notifyAnalystToast(
                        'Envío de carnets en segundo plano',
                        this.carnetEmailMessage,
                    );
                } catch (e) {
                    this.error = e.message || 'Error al encolar los correos.';
                } finally {
                    this.sendingCarnetEmails = false;
                }
            },
            notifyAnalystToast(title, body) {
                if (typeof FilamentNotification === 'undefined') {
                    return;
                }

                new FilamentNotification()
                    .title(title)
                    .body(body)
                    .success()
                    .duration(8000)
                    .send();
            },
        };
    };
</script>
