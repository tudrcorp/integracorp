<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generar enlace temporal · Telemedicina</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="min-h-screen bg-slate-100 font-[Instrument_Sans,sans-serif] text-slate-900 dark:bg-[#0a0f14] dark:text-slate-100">
    <main class="mx-auto flex min-h-screen max-w-4xl items-center px-4 py-10 sm:px-6">
        <section class="w-full rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/50 sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-white px-2 py-1 ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700">
                        <img src="{{ asset('image/logoNewPdf.png') }}" alt="Logo Tu Doctor en Casa" class="h-10 w-auto sm:h-12">
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#0064a1] dark:text-emerald-300">Acceso temporal</p>
                        <h1 class="text-lg font-bold sm:text-xl">URL pública de documentación de telemedicina</h1>
                    </div>
                </div>
                <span class="rounded-full bg-[#0064a1]/10 px-3 py-1 text-xs font-semibold text-[#0064a1] dark:bg-emerald-900/40 dark:text-emerald-300">
                    Válida por 12 horas
                </span>
            </div>

            <p class="mt-5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                Comparte este enlace con el cliente. Cuando caduque, vuelve a esta pantalla y genera uno nuevo con un solo clic.
                La firma evita modificaciones del enlace y revoca el acceso automáticamente al vencer.
            </p>

            <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950/40">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">URL temporal</p>
                <textarea
                    id="temporaryUrl"
                    readonly
                    class="mt-2 h-28 w-full rounded-lg border border-slate-200 bg-white p-3 font-mono text-xs leading-relaxed text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                >{{ $temporaryUrl }}</textarea>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Expira: <span class="font-semibold">{{ $expiresAt->format('d/m/Y h:i A') }}</span>
                    </p>
                    <div class="flex items-center gap-2">
                        <button
                            id="copyButton"
                            type="button"
                            class="rounded-lg bg-[#0064a1] px-3 py-2 text-xs font-semibold text-white hover:bg-[#005080]"
                        >
                            Copiar URL
                        </button>
                        <a
                            href="{{ route('telemedicine.schema.documentation.link') }}"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Generar nueva URL
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-200">
                Seguridad recomendada: no publicar este enlace en canales abiertos. Compártelo por correo o WhatsApp directo al cliente.
            </div>
        </section>
    </main>

    <script>
        (function () {
            const button = document.getElementById('copyButton');
            const textarea = document.getElementById('temporaryUrl');

            button?.addEventListener('click', async () => {
                const text = textarea?.value?.trim() ?? '';
                if (text === '') {
                    return;
                }

                try {
                    await navigator.clipboard.writeText(text);
                    button.textContent = 'Copiada';
                    setTimeout(() => {
                        button.textContent = 'Copiar URL';
                    }, 1400);
                } catch (error) {
                    textarea?.focus();
                    textarea?.select();
                    document.execCommand('copy');
                    button.textContent = 'Copiada';
                    setTimeout(() => {
                        button.textContent = 'Copiar URL';
                    }, 1400);
                }
            });
        })();
    </script>
</body>
</html>
