<template x-if="successOpen">
    <div
        class="sf-success"
        role="dialog"
        aria-modal="true"
        aria-labelledby="sf-success-title"
        aria-describedby="sf-success-copy"
        x-on:click.self="dismissSuccess()"
    >
        <div class="sf-success__card" x-on:click.stop>
            <div class="sf-success__grab">
                <span class="sf-success__handle" aria-hidden="true"></span>
            </div>

            <span class="sf-success__seal" aria-hidden="true">
                <svg viewBox="0 0 96 96" fill="none">
                    <path
                        class="sf-success__burst"
                        fill="#cceed9"
                        d="M48 4.5 55.4 11l10.4-1.6 4.6 9.5 10.1 2.2.6 10.3 9.5 4.6-1.6 10.4L96 48l-6.5 7.4 1.6 10.4-9.5 4.6-.6 10.3-10.1 2.2-4.6 9.5-10.4-1.6L48 91.5 40.6 85l-10.4 1.6-4.6-9.5-10.1-2.2-.6-10.3-9.5-4.6 1.6-10.4L0 48l6.5-7.4-1.6-10.4 9.5-4.6.6-10.3 10.1-2.2 4.6-9.5 10.4 1.6z"
                    />
                    <path
                        class="sf-success__check"
                        d="M30 50.5 42.5 63 67 34"
                        stroke="#0b6b45"
                        stroke-width="7.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </span>

            <h2 id="sf-success-title">Cotización generada</h2>
            <p id="sf-success-copy" x-text="successCopy()"></p>

            <div class="sf-success__actions">
                <button
                    type="button"
                    class="sf-success__done"
                    x-ref="successDone"
                    x-on:click="dismissSuccess()"
                >
                    Ver propuesta
                </button>
                <button
                    type="button"
                    class="sf-success__pdf"
                    x-show="successPdfUrl"
                    x-bind:disabled="successPdfBusy"
                    x-bind:class="successPdfBusy && 'is-busy'"
                    x-bind:aria-busy="successPdfBusy.toString()"
                    x-on:click="downloadSuccessPdf()"
                >
                    <span class="sf-success__pdf-idle" x-show="! successPdfBusy">Descargar cotización en PDF</span>
                    <span class="sf-success__pdf-busy" x-show="successPdfBusy" x-cloak>
                        <span class="sf-spinner" aria-hidden="true"></span>
                        <span>Preparando PDF…</span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>
