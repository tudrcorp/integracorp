@props([
    'associates' => [],
])

<div
    class="fi-scoped mt-4"
    wire:key="company-associate-documents-bulk-send-progress"
    x-data="{
        associates: @js($associates),
        processing: false,
        progress: @entangle('associateDocumentsBulkSendProgress').live,
        async start() {
            if (this.processing || this.associates.length === 0) {
                return;
            }

            this.processing = true;

            try {
                await this.$wire.initAssociateDocumentsBulkSend(this.associates.map((associate) => associate.id));

                for (const associate of this.associates) {
                    await this.$wire.sendAssociateDocument(associate.id);
                }

                await this.$wire.finishAssociateDocumentsBulkSendFromProgress();
            } finally {
                this.processing = false;
            }
        },
        summaryTitle() {
            if (! this.progress) {
                return '';
            }

            if ((this.progress.failed_messages ?? []).length === 0) {
                return 'Documentos enviados';
            }

            if ((this.progress.sent ?? 0) > 0) {
                return 'Envío parcial completado';
            }

            return 'No se pudieron enviar los documentos';
        },
        summaryBody() {
            if (! this.progress) {
                return '';
            }

            const sent = this.progress.sent ?? 0;
            const total = this.progress.total ?? 0;
            const failed = this.progress.failed_messages ?? [];

            if (failed.length === 0) {
                return sent === 1
                    ? 'El carnet y el QR se enviaron correctamente al asociado seleccionado.'
                    : `El carnet y el QR se enviaron correctamente a ${sent} asociados.`;
            }

            if (sent > 0) {
                return `Se enviaron ${sent} de ${total} seleccionados. Revise los detalles a continuación.`;
            }

            return 'Ningún envío pudo completarse. Revise los detalles a continuación.';
        },
    }"
    x-init="
        $nextTick(() => {
            const modal = $el.closest('.fi-modal-window') ?? $el.closest('[role=dialog]');

            if (! modal) {
                return;
            }

            const submitButton = modal.querySelector('[data-associate-documents-submit]')
                ?? Array.from(modal.querySelectorAll('button')).find((button) => button.textContent.trim() === 'Enviar documentos');

            if (submitButton && ! submitButton.dataset.associateDocumentsHooked) {
                submitButton.dataset.associateDocumentsHooked = '1';
                submitButton.dataset.associateDocumentsSubmit = '1';
                submitButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    start();
                }, true);
            }
        })
    "
    @company-associate-documents-send-start.window="start()"
>
    <template x-if="progress && progress.status === 'running'">
        <div class="overflow-hidden rounded-2xl border border-primary-200/70 bg-primary-50/40 p-5 dark:border-primary-500/20 dark:bg-primary-950/20">
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <svg
                        class="mt-0.5 size-5 shrink-0 animate-spin text-primary-600 dark:text-primary-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
                    <div class="min-w-0 flex-1 space-y-1">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            Enviando documentos...
                        </p>
                        <p
                            class="text-xs text-gray-600 dark:text-gray-300"
                            x-text="progress.current_name
                                ? `Generando carnet y enviando documentos a ${progress.current_name}...`
                                : 'Preparando envío...'"
                        ></p>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between text-xs font-semibold text-gray-600 dark:text-gray-300">
                        <span>Progreso del envío</span>
                        <span x-text="`${Math.max(0, Math.min(100, progress.percentage ?? 0))}%`"></span>
                    </div>
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-200/90 dark:bg-gray-700/60">
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-primary-500 via-primary-400 to-sky-400 transition-all duration-500 ease-out"
                            x-bind:style="`width: ${Math.max(0, Math.min(100, progress.percentage ?? 0))}%`"
                        ></div>
                    </div>
                    <p
                        class="text-xs text-gray-500 dark:text-gray-400"
                        x-text="`${progress.processed ?? 0} de ${progress.total ?? 0} asociados procesados`"
                    ></p>
                </div>
            </div>
        </div>
    </template>

    <template x-if="progress && progress.status === 'finished'">
        <div
            class="space-y-3 rounded-2xl border px-4 py-4"
            x-bind:class="(progress.failed_messages ?? []).length === 0
                ? 'border-success-200/80 bg-success-50/50 dark:border-success-500/20 dark:bg-success-950/20'
                : ((progress.sent ?? 0) > 0
                    ? 'border-warning-200/80 bg-warning-50/50 dark:border-warning-500/20 dark:bg-warning-950/20'
                    : 'border-danger-200/80 bg-danger-50/50 dark:border-danger-500/20 dark:bg-danger-950/20')"
        >
            <p
                class="text-sm font-semibold"
                x-bind:class="(progress.failed_messages ?? []).length === 0
                    ? 'text-success-700 dark:text-success-300'
                    : ((progress.sent ?? 0) > 0
                        ? 'text-warning-700 dark:text-warning-300'
                        : 'text-danger-700 dark:text-danger-300')"
                x-text="summaryTitle()"
            ></p>
            <p class="text-sm text-gray-600 dark:text-gray-300" x-text="summaryBody()"></p>

            <ul
                x-show="(progress.failed_messages ?? []).length > 0"
                x-cloak
                class="max-h-32 space-y-1 overflow-y-auto text-xs text-danger-700 dark:text-danger-300"
            >
                <template x-for="(message, index) in progress.failed_messages" :key="index">
                    <li x-text="message"></li>
                </template>
            </ul>
        </div>
    </template>
</div>
