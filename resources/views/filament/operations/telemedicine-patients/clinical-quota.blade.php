@php
    $tone = $tone ?? 'muted';
    $summary = $summary ?? null;
    $message = $message ?? null;
    $otpHint = (bool) ($otpHint ?? false);
    $rows = $rows ?? [];

    $shell = match ($tone) {
        'ok' => 'border-sky-200/80 bg-sky-50/50 dark:border-sky-500/25 dark:bg-sky-950/25',
        'warning' => 'border-amber-200/80 bg-amber-50/55 dark:border-amber-500/25 dark:bg-amber-950/20',
        'danger' => 'border-rose-200/80 bg-rose-50/50 dark:border-rose-500/25 dark:bg-rose-950/20',
        default => 'border-slate-200/80 bg-white/70 dark:border-white/10 dark:bg-white/5',
    };

    $pill = static fn (string $rowTone): string => match ($rowTone) {
        'ok' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200',
        'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200',
        'danger' => 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-200',
        default => 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300',
    };
@endphp

<div class="rounded-2xl border {{ $shell }} px-3.5 py-3 sm:px-4">
    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
        <p class="text-[13px] font-semibold tracking-tight text-slate-800 dark:text-slate-100">Cupo clínico</p>
        @if (filled($summary))
            <p class="text-[12px] text-slate-500 dark:text-slate-400">{{ $summary }}</p>
        @endif
    </div>
    <p class="mt-0.5 text-[11px] leading-snug text-slate-500 dark:text-slate-400">
        El médico descuenta al guardar la consulta. Operaciones solo consulta el saldo.
    </p>

    @if (filled($message))
        <p class="mt-2 text-[12.5px] leading-snug text-slate-700 dark:text-slate-200">{{ $message }}</p>
    @endif

    @if ($rows !== [])
        <ul class="mt-2 divide-y divide-slate-200/80 dark:divide-white/10">
            @foreach ($rows as $row)
                <li class="flex items-center justify-between gap-3 py-1.5 first:pt-0 last:pb-0">
                    <div class="min-w-0">
                        <p class="truncate text-[13px] font-medium text-slate-800 dark:text-slate-100">{{ $row['label'] }}</p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $row['channel'] }} · {{ $row['count'] }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $pill($row['tone']) }}">
                        {{ $row['balance'] }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($otpHint)
        <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">Si está agotado, el médico de TDG pide OTP. Operaciones no excepciona.</p>
    @endif
</div>
