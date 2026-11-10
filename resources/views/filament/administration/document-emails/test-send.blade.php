@props([
    'inputId' => 'document-email-test',
    'inputClass' => '',
    'labelClass' => '',
])

{{--
    Prueba de envío compartida por los modales del recibo de pago y del aviso de cobro.
    El componente Alpine que la contiene expone: testEmail, sendingTest, testMessage, testError,
    canTest y sendTest().
--}}
<section class="space-y-3 rounded-2xl border border-amber-200/80 bg-amber-50/60 p-4 dark:border-amber-500/20 dark:bg-amber-950/20">
    <div class="flex items-start gap-3">
        <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">
            <x-heroicon-o-beaker class="size-4" />
        </span>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-gray-900 dark:text-white">Probar antes de enviar</p>
            <p class="text-xs text-gray-600 dark:text-gray-400">
                Envía los copys elegidos, con sus adjuntos, solo a este correo: uno por cada destinatario. Llegan con «[PRUEBA]» en el asunto y un aviso de a quién llegarían en el envío real. No se notifica al afiliado, al agente ni a los testigos.
            </p>
        </div>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="min-w-0 flex-1">
            <label class="{{ $labelClass ?: 'mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400' }}" for="{{ $inputId }}">Correo de prueba</label>
            <input
                id="{{ $inputId }}"
                type="email"
                maxlength="190"
                x-model="testEmail"
                x-on:input="testMessage = null; testError = null"
                x-bind:disabled="sendingTest"
                placeholder="analista@tudrencasa.com"
                autocomplete="email"
                class="{{ $inputClass ?: 'w-full rounded-2xl border border-gray-200/90 bg-white/95 px-4 py-2.5 text-sm text-gray-950 outline-none ring-1 ring-gray-950/5 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/30 dark:border-white/10 dark:bg-gray-900/80 dark:text-white' }} disabled:opacity-60"
            />
        </div>
        <button
            type="button"
            x-on:click="sendTest()"
            x-bind:disabled="! canTest"
            class="inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-full border border-amber-300 bg-white px-5 py-2.5 text-sm font-semibold text-amber-800 transition-all duration-200 hover:bg-amber-50 active:scale-[0.98] disabled:opacity-60 dark:border-amber-500/40 dark:bg-gray-900 dark:text-amber-200 dark:hover:bg-amber-500/10"
        >
            <x-heroicon-o-paper-airplane class="size-4 shrink-0" x-show="! sendingTest" />
            <span x-show="! sendingTest">Enviar prueba</span>
            <span x-show="sendingTest" x-cloak>Enviando prueba…</span>
        </button>
    </div>

    <p x-show="testError" x-cloak class="text-sm text-danger-600 dark:text-danger-400" role="alert" x-text="testError"></p>
    <p x-show="testMessage" x-cloak class="text-sm font-medium text-emerald-700 dark:text-emerald-300" x-text="testMessage"></p>
</section>
