@php
    $avatarUrl = $colaborador->avatar ? Storage::disk('public')->url($colaborador->avatar) : null;
    $nameParts = \Illuminate\Support\Str::of((string) ($colaborador->fullName ?? ''))
        ->upper()
        ->replaceMatches('/[^A-Z0-9\s]/', ' ')
        ->squish()
        ->explode(' ')
        ->filter(function (string $part): bool {
            return ! in_array($part, ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'Y', 'E', 'DA', 'DO', 'DOS', 'DAS'], true);
        })
        ->values();

    $firstPart = $nameParts->first();
    $lastPart = $nameParts->last();

    $avatarInitials = '';
    if ($firstPart) {
        $avatarInitials .= \Illuminate\Support\Str::substr($firstPart, 0, 1);
    }
    if ($lastPart && $lastPart !== $firstPart) {
        $avatarInitials .= \Illuminate\Support\Str::substr($lastPart, 0, 1);
    }

    if ($avatarInitials === '' && filled($colaborador->fullName)) {
        $avatarInitials = \Illuminate\Support\Str::of((string) $colaborador->fullName)
            ->replaceMatches('/[^A-Za-z0-9]/', '')
            ->upper()
            ->substr(0, 2)
            ->value();
    }
@endphp

<div class="space-y-4 px-1 py-1">
    <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-5 shadow-sm backdrop-blur dark:border-white/10 dark:bg-slate-900/70">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                @if ($avatarUrl)
                    <img
                        src="{{ $avatarUrl }}"
                        alt="Avatar de {{ $colaborador->fullName }}"
                        class="h-20 w-20 rounded-full border border-slate-200/80 object-cover shadow-sm dark:border-white/10"
                        onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');"
                    >
                    <div class="hidden flex h-20 w-20 items-center justify-center rounded-full border border-slate-200/80 bg-slate-400 text-xl font-semibold tracking-wide text-white shadow-sm dark:border-white/10 dark:bg-slate-600">
                        {{ $avatarInitials !== '' ? $avatarInitials : 'N' }}
                    </div>
                @else
                    <div class="flex h-20 w-20 items-center justify-center rounded-full border border-slate-200/80 bg-slate-400 text-xl font-semibold tracking-wide text-white shadow-sm dark:border-white/10 dark:bg-slate-600">
                        {{ $avatarInitials !== '' ? $avatarInitials : 'N' }}
                    </div>
                @endif
                <div class="space-y-1">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Colaborador</p>
                    <h3 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ $colaborador->fullName ?: 'Sin nombre' }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $colaborador->cedula ?: 'Sin cédula' }}</p>
                </div>
            </div>
            <span @class([
                'rounded-full px-3 py-1 text-xs font-semibold ring-1',
                'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-700/50' => ($colaborador->status ?? null) === 'activo',
                'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:ring-rose-700/50' => ($colaborador->status ?? null) !== 'activo',
            ])>
                {{ strtoupper((string) ($colaborador->status ?: 'sin estatus')) }}
            </span>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Datos personales</p>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Cédula</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $colaborador->cedula ?: 'N/A' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Sexo</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $colaborador->sexo ?: 'N/A' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Fecha de nacimiento</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $colaborador->fechaNacimiento ?: 'N/A' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Hijos</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $colaborador->nroHijos ?? '0' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Dependientes</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $colaborador->nroHijoDependiente ?? '0' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Talla camisa</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $colaborador->tallaCamisa ?: 'N/A' }}</dd></div>
            </dl>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Contacto</p>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Teléfono personal</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $colaborador->telefono ?: 'N/A' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Teléfono corporativo</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $colaborador->telefonoCorporativo ?: 'N/A' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Email corporativo</dt><dd class="font-medium text-slate-900 dark:text-slate-100 break-all">{{ $colaborador->emailCorporativo ?: 'N/A' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Email personal</dt><dd class="font-medium text-slate-900 dark:text-slate-100 break-all">{{ $colaborador->emailPersonal ?: 'N/A' }}</dd></div>
            </dl>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
        <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Contexto laboral</p>
        <dl class="grid gap-2 text-sm md:grid-cols-2">
            <div><dt class="text-slate-500 dark:text-slate-400">Departamento</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ data_get($colaborador, 'departamento.description', 'N/A') }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Cargo</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ data_get($colaborador, 'cargo.description', 'N/A') }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Fecha de ingreso</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $colaborador->fechaIngreso ?: 'N/A' }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Sueldo</dt><dd class="font-medium text-slate-900 dark:text-slate-100">US$ {{ $colaborador->sueldo !== null ? number_format((float) $colaborador->sueldo, 2, ',', '.') : '0,00' }}</dd></div>
            <div class="md:col-span-2"><dt class="text-slate-500 dark:text-slate-400">Dirección</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $colaborador->direccion ?: 'N/A' }}</dd></div>
        </dl>
    </div>
</div>
