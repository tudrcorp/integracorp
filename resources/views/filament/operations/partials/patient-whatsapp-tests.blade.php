@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\ServiceOrderPatientNotification> $tests */
    $tone = fn (\App\Models\ServiceOrderPatientNotification $test): string => match (true) {
        $test->status === \App\Models\ServiceOrderPatientNotification::STATUS_ANSWERED => 'bg-emerald-100 text-emerald-800 ring-emerald-300/70 dark:bg-emerald-500/20 dark:text-emerald-100',
        in_array($test->status, [\App\Models\ServiceOrderPatientNotification::STATUS_SENT, \App\Models\ServiceOrderPatientNotification::STATUS_SENDING, \App\Models\ServiceOrderPatientNotification::STATUS_WAITING_TURN], true) => 'bg-sky-100 text-sky-800 ring-sky-300/70 dark:bg-sky-500/20 dark:text-sky-100',
        default => 'bg-rose-100 text-rose-800 ring-rose-300/70 dark:bg-rose-500/20 dark:text-rose-100',
    };
    $deliveryTone = fn (\App\Models\ServiceOrderPatientNotification $test): string => match ($test->deliveryColor()) {
        'info' => 'bg-sky-100 text-sky-800 ring-sky-300/70 dark:bg-sky-500/20 dark:text-sky-100',
        'success' => 'bg-emerald-100 text-emerald-800 ring-emerald-300/70 dark:bg-emerald-500/20 dark:text-emerald-100',
        'warning' => 'bg-amber-100 text-amber-800 ring-amber-300/70 dark:bg-amber-500/20 dark:text-amber-100',
        default => 'bg-gray-100 text-gray-700 ring-gray-300/70 dark:bg-white/10 dark:text-gray-200',
    };
@endphp

<div wire:poll.10s class="text-sm">
    @if ($tests->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300/80 px-4 py-3 text-gray-600 dark:border-white/15 dark:text-gray-300">
            Aún no se ha enviado ninguna prueba. Use «Enviar prueba» arriba a la derecha.
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl border border-gray-200/80 dark:border-white/10">
            <table class="w-full min-w-[760px] text-left">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-2">Enviada</th>
                        <th class="px-3 py-2">Número</th>
                        <th class="px-3 py-2">Orden de muestra</th>
                        <th class="px-3 py-2">Estatus</th>
                        <th class="px-3 py-2">¿Le llegó?</th>
                        <th class="px-3 py-2">Respuesta</th>
                        <th class="px-3 py-2">Por</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tests as $test)
                        <tr wire:key="patient-whatsapp-test-{{ $test->id }}" class="border-t border-gray-100 align-top dark:border-white/10">
                            <td class="px-3 py-2 whitespace-nowrap">{{ ($test->sent_at ?? $test->created_at)?->format('d/m/Y h:i A') }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">+{{ $test->phone }}</td>
                            <td class="px-3 py-2">{{ $test->order_number }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $tone($test) }}">{{ $test->statusLabel() }}</span>
                                @if (filled($test->error))
                                    <div class="mt-1 text-xs text-rose-700 dark:text-rose-300">{{ \Illuminate\Support\Str::limit($test->error, 180) }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                @if ($test->deliveryLabel() !== null)
                                    <span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $deliveryTone($test) }}">{{ $test->deliveryLabel() }}</span>
                                    @if ($test->deliveredMoment() !== null)
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Llegó {{ $test->deliveredMoment()->format('h:i A') }}@if ($test->read_at !== null) · leído {{ $test->read_at->format('h:i A') }}@endif
                                        </div>
                                    @endif
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                @if (filled($test->answer))
                                    <span class="font-semibold">{{ $test->answer }}</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">· {{ $test->answered_at?->format('h:i A') }}</span>
                                @elseif ($test->invalid_replies > 0)
                                    <span class="text-xs text-amber-700 dark:text-amber-300">Escribió algo que no es SI ni NO ({{ $test->invalid_replies }})</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $test->created_by }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            «✓✓ Entregado» confirma que el mensaje llegó al teléfono, aunque no haya respondido. «Leído» solo aparece si esa persona tiene activadas las confirmaciones de lectura de WhatsApp.
        </p>
    @endif
</div>
