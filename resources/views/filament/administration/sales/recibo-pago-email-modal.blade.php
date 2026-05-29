@props([
    'sale' => null,
    'recipients' => [],
])

@php
    /** @var array<string, mixed> $recipients */
    $agent = $recipients['agent'] ?? ['linked' => false, 'name' => null, 'code' => null, 'email' => null];
    $agency = $recipients['agency'] ?? ['linked' => false, 'name' => null, 'code' => null, 'email' => null];
    $emails = $recipients['emails'] ?? [];
    $hasRecipients = (bool) ($recipients['has_recipients'] ?? false);
@endphp

@if (! $sale)
    <p class="text-sm text-gray-500 dark:text-gray-400">No hay venta asociada.</p>
@else
    <div class="fi-scoped space-y-4">
        <article class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70">
            <div class="border-b border-gray-200/80 px-4 py-3 dark:border-white/10">
                <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Recibo de pago</p>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                    {{ $sale->invoice_number }} · {{ $sale->affiliate_full_name }}
                </p>
            </div>

            <div class="space-y-4 p-4">
                <section class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4 dark:border-white/10 dark:bg-white/5">
                    <div class="mb-3 flex items-center gap-2">
                        <span class="inline-flex size-8 items-center justify-center rounded-full bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300">
                            <x-heroicon-o-user-circle class="size-4" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Agente</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Correo registrado en la tabla de agentes</p>
                        </div>
                    </div>

                    @if (! ($agent['linked'] ?? false))
                        <p class="text-sm text-gray-500 dark:text-gray-400">Sin agente asociado a esta venta.</p>
                    @else
                        <dl class="grid gap-2 text-sm">
                            <div class="flex flex-wrap justify-between gap-2">
                                <dt class="text-gray-500 dark:text-gray-400">Nombre</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ $agent['name'] ?: '—' }}</dd>
                            </div>
                            <div class="flex flex-wrap justify-between gap-2">
                                <dt class="text-gray-500 dark:text-gray-400">Código</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ $agent['code'] ?: '—' }}</dd>
                            </div>
                            <div class="flex flex-wrap justify-between gap-2">
                                <dt class="text-gray-500 dark:text-gray-400">Correo</dt>
                                <dd class="font-semibold {{ filled($agent['email']) ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300' }}">
                                    {{ $agent['email'] ?: 'Sin correo registrado' }}
                                </dd>
                            </div>
                        </dl>
                    @endif
                </section>

                <section class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4 dark:border-white/10 dark:bg-white/5">
                    <div class="mb-3 flex items-center gap-2">
                        <span class="inline-flex size-8 items-center justify-center rounded-full bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300">
                            <x-heroicon-o-building-office-2 class="size-4" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Agencia</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Correo registrado en la tabla de agencias</p>
                        </div>
                    </div>

                    @if (! ($agency['linked'] ?? false))
                        <p class="text-sm text-gray-500 dark:text-gray-400">Sin agencia asociada a esta venta.</p>
                    @else
                        <dl class="grid gap-2 text-sm">
                            <div class="flex flex-wrap justify-between gap-2">
                                <dt class="text-gray-500 dark:text-gray-400">Nombre</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ $agency['name'] ?: '—' }}</dd>
                            </div>
                            <div class="flex flex-wrap justify-between gap-2">
                                <dt class="text-gray-500 dark:text-gray-400">Código</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ $agency['code'] ?: '—' }}</dd>
                            </div>
                            <div class="flex flex-wrap justify-between gap-2">
                                <dt class="text-gray-500 dark:text-gray-400">Correo</dt>
                                <dd class="font-semibold {{ filled($agency['email']) ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300' }}">
                                    {{ $agency['email'] ?: 'Sin correo registrado' }}
                                </dd>
                            </div>
                        </dl>
                    @endif
                </section>

                <section class="rounded-2xl border border-emerald-200/80 bg-emerald-50/70 p-4 dark:border-emerald-500/20 dark:bg-emerald-950/20">
                    <p class="mb-2 text-sm font-semibold text-emerald-900 dark:text-emerald-200">
                        Correos a los que se enviará el recibo
                    </p>

                    @if ($hasRecipients)
                        <ul class="space-y-2">
                            @foreach ($emails as $email)
                                <li class="flex items-center gap-2 rounded-xl border border-emerald-200/70 bg-white/80 px-3 py-2 text-sm font-medium text-gray-800 dark:border-emerald-500/20 dark:bg-gray-900/60 dark:text-gray-100">
                                    <x-heroicon-o-envelope class="size-4 shrink-0 text-emerald-600 dark:text-emerald-300" />
                                    <span>{{ $email }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-amber-700 dark:text-amber-300">
                            No hay correos válidos disponibles para el envío.
                        </p>
                    @endif
                </section>
            </div>
        </article>
    </div>
@endif
