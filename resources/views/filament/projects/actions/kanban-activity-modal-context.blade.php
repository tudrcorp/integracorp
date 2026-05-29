@php
    use App\Support\Filament\ProjectManagement\ProjectManagementActivityTable;

    $status = ProjectManagementActivityTable::statusMeta((string) $record->status);
    $priority = ProjectManagementActivityTable::priorityMeta((string) $record->priority);
    $color = ProjectManagementActivityTable::resolveColor($record);
    $isOverdue = ProjectManagementActivityTable::isOverdue($record);
    $notesCount = (int) ($record->notes_logs_count ?? $record->notesLogs()->count());
    $documentsCount = (int) ($record->documents_count ?? $record->documents()->count());
    $latestNote = $mode === 'notes'
        ? $record->notesLogs()->with('author:id,name')->latest()->first()
        : null;
    $viewAllNotesUrl = $mode === 'notes' && $notesCount > 0
        ? \App\Support\Filament\ProjectManagement\ProjectManagementKanbanActivityModalActions::activityViewBitacoraUrl($record)
        : null;
@endphp

<div class="overflow-hidden rounded-2xl border border-gray-200/90 bg-gradient-to-br from-slate-50 via-white to-slate-100 p-4 shadow-sm dark:border-white/10 dark:from-slate-900/90 dark:via-slate-950 dark:to-slate-900/80">
    <div class="flex items-start gap-3">
        <div
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border shadow-inner"
            style="border-color: {{ $color }}55; background: color-mix(in srgb, {{ $color }} 16%, transparent);"
        >
            <x-filament::icon icon="heroicon-o-clipboard-document-check" class="size-6" style="color: {{ $color }};" />
        </div>

        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                Actividad seleccionada
            </p>
            <p class="mt-0.5 truncate text-base font-semibold text-gray-950 dark:text-white">
                {{ $record->title }}
            </p>
            <p class="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">
                {{ $record->project?->name ?? 'Sin proyecto' }}
                @if ($record->subproject?->name)
                    · {{ $record->subproject->name }}
                @endif
            </p>
        </div>
    </div>

    <div class="mt-4 grid gap-2 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-200/80 bg-white/70 px-3 py-2 dark:border-white/10 dark:bg-white/5">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Estatus</p>
            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $status['label'] }}</p>
        </div>

        <div class="rounded-xl border border-gray-200/80 bg-white/70 px-3 py-2 dark:border-white/10 dark:bg-white/5">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Prioridad</p>
            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $priority['label'] }}</p>
        </div>

        <div @class([
            'rounded-xl border px-3 py-2',
            'border-danger-500/30 bg-danger-500/10' => $isOverdue,
            'border-gray-200/80 bg-white/70 dark:border-white/10 dark:bg-white/5' => ! $isOverdue,
        ])>
            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Fecha límite</p>
            <p @class([
                'mt-1 text-sm font-semibold',
                'text-danger-600 dark:text-danger-400' => $isOverdue,
                'text-gray-900 dark:text-white' => ! $isOverdue,
            ])>
                {{ $record->due_date?->format('d/m/Y') ?? 'Sin definir' }}
            </p>
        </div>
    </div>

    <div class="mt-3 grid gap-2 sm:grid-cols-2">
        <div class="rounded-xl border border-gray-200/80 bg-white/70 px-3 py-2 dark:border-white/10 dark:bg-white/5">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Notas registradas</p>
            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $notesCount }}</p>
        </div>

        <div class="rounded-xl border border-gray-200/80 bg-white/70 px-3 py-2 dark:border-white/10 dark:bg-white/5">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Documentos</p>
            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $documentsCount }}</p>
        </div>
    </div>

    @if ($mode === 'notes' && $latestNote !== null)
        <div class="mt-4 space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Última nota</p>
                @if ($notesCount > 1 && filled($viewAllNotesUrl))
                    <a
                        href="{{ $viewAllNotesUrl }}"
                        class="inline-flex items-center gap-1 rounded-lg border border-amber-200/80 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-800 transition hover:bg-amber-100 dark:border-amber-500/35 dark:bg-amber-500/15 dark:text-amber-200 dark:hover:bg-amber-500/25"
                    >
                        <x-filament::icon icon="heroicon-m-book-open" class="size-3.5" />
                        Ver todas ({{ $notesCount }})
                    </a>
                @endif
            </div>
            <div class="rounded-xl border border-gray-200/80 bg-white/80 px-3 py-2 dark:border-white/10 dark:bg-white/5">
                <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400">
                    {{ $latestNote->author?->name ?? 'Usuario' }}
                    · {{ $latestNote->created_at?->format('d/m/Y H:i') }}
                </p>
                <p class="mt-1 line-clamp-4 text-xs leading-5 text-gray-700 dark:text-gray-300">
                    {{ $latestNote->content }}
                </p>
            </div>
        </div>
    @endif

    <p class="mt-3 text-xs leading-5 text-gray-500 dark:text-gray-400">
        @if ($mode === 'notes')
            La nota quedará vinculada a esta actividad y visible en el historial de seguimiento.
        @else
            El archivo quedará disponible en el expediente documental de la actividad.
        @endif
    </p>
</div>
