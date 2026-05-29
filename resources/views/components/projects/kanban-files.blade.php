@props([
    'files',
    'category' => 'all',
    'layout' => 'grid',
    'pinnedFileIds' => [],
])

@php
    $counts = $files['counts'] ?? ['all' => 0, 'images' => 0, 'documents' => 0];
    $items = $files['files'] ?? [];
    $tabs = [
        'all' => ['label' => 'Todos', 'count' => $counts['all'] ?? 0, 'icon' => 'folder'],
        'image' => ['label' => 'Imágenes', 'count' => $counts['images'] ?? 0, 'icon' => 'photo'],
        'document' => ['label' => 'Documentos', 'count' => $counts['documents'] ?? 0, 'icon' => 'document'],
    ];
@endphp

<section class="kanban-files overflow-hidden rounded-3xl">
    <header class="kanban-files-toolbar flex flex-col gap-4 border-b px-4 py-4 md:px-6 md:py-5 lg:flex-row lg:items-center lg:justify-between">
        <nav class="kanban-files-tabs" aria-label="Filtrar archivos">
            <div class="kanban-files-tabs__track inline-flex flex-wrap items-center gap-1 p-1">
                @foreach ($tabs as $tabKey => $tab)
                    <button
                        type="button"
                        wire:click="setFilesCategory('{{ $tabKey }}')"
                        wire:key="kanban-files-tab-{{ $tabKey }}"
                        @class([
                            'kanban-files-tab inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold transition duration-200',
                            'kanban-files-tab--active' => $category === $tabKey,
                        ])
                        @if ($category === $tabKey) aria-current="page" @endif
                    >
                        <span class="kanban-files-tab__icon flex size-7 items-center justify-center rounded-lg">
                            @if ($tab['icon'] === 'folder')
                                <x-heroicon-o-folder class="size-4" />
                            @elseif ($tab['icon'] === 'photo')
                                <x-heroicon-o-photo class="size-4" />
                            @else
                                <x-heroicon-o-document-text class="size-4" />
                            @endif
                        </span>
                        <span class="kanban-files-tab__label">{{ $tab['label'] }}</span>
                        <span @class([
                            'kanban-files-tab__count inline-flex min-w-[1.45rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold tabular-nums leading-none',
                            'kanban-files-tab__count--active' => $category === $tabKey,
                        ])>
                            {{ $tab['count'] }}
                        </span>
                    </button>
                @endforeach
            </div>
        </nav>

        <div class="flex flex-wrap items-center gap-3">
            <label class="kanban-files-sort flex items-center gap-2 text-xs font-medium">
                <span>Ordenar:</span>
                <select wire:model.live="filesSort" class="kanban-files-sort__select rounded-xl px-3 py-2 text-xs font-semibold">
                    <option value="newest">Más recientes</option>
                    <option value="oldest">Más antiguos</option>
                    <option value="name">Nombre A-Z</option>
                    <option value="size">Tamaño</option>
                </select>
            </label>

            <div class="kanban-files-layout-toggle inline-flex rounded-xl p-1">
                <button
                    type="button"
                    wire:click="setFilesLayout('grid')"
                    @class(['kanban-files-layout-btn rounded-lg p-2 transition', 'kanban-files-layout-btn--active' => $layout === 'grid'])
                    title="Vista cuadrícula"
                >
                    <x-heroicon-m-squares-2x2 class="size-4" />
                </button>
                <button
                    type="button"
                    wire:click="setFilesLayout('list')"
                    @class(['kanban-files-layout-btn rounded-lg p-2 transition', 'kanban-files-layout-btn--active' => $layout === 'list'])
                    title="Vista lista"
                >
                    <x-heroicon-m-bars-3-bottom-left class="size-4" />
                </button>
            </div>
        </div>
    </header>

    <div class="kanban-files-body p-4 md:p-6">
        @if ($items === [])
            <div class="kanban-files-empty py-20 text-center">
                <div class="kanban-files-empty-icon mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl">
                    <x-heroicon-o-folder-open class="size-7" />
                </div>
                <p class="kanban-files-empty-title text-sm font-semibold">Sin archivos para mostrar</p>
                <p class="kanban-files-empty-text mt-1 text-xs">Carga documentos desde las actividades o ajusta los filtros.</p>
            </div>
        @elseif ($layout === 'list')
            <div class="kanban-files-list space-y-3">
                @foreach ($items as $file)
                    @php $isPinned = in_array($file['id'], $pinnedFileIds, true); @endphp
                    <article wire:key="kanban-file-list-{{ $file['id'] }}" @class(['kanban-files-card kanban-files-card--list', 'kanban-files-card--pinned' => $isPinned])>
                        <div class="kanban-files-card__preview kanban-files-card__preview--list">
                            @if ($file['category'] === 'image')
                                <x-heroicon-o-photo class="size-6" />
                            @elseif ($file['category'] === 'document')
                                <x-heroicon-o-document-text class="size-6" />
                            @else
                                <x-heroicon-o-document class="size-6" />
                            @endif
                            <span class="text-[10px] font-bold uppercase tracking-wider">{{ $file['extension'] }}</span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="kanban-files-card__name truncate text-sm font-semibold">{{ $file['name'] }}</p>
                            <p class="kanban-files-card__meta mt-0.5 truncate text-xs">
                                {{ $file['project_name'] }} · {{ $file['activity_title'] }}
                            </p>
                            <p class="kanban-files-card__date mt-1 text-[11px]">{{ $file['uploaded_at'] }} · {{ $file['size_label'] }}</p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            @if (($file['assignees']['total_count'] ?? 0) > 0)
                                <x-collaborator-avatar-stack
                                    class="kanban-files-card__avatars"
                                    align="end"
                                    :avatars="$file['assignees']['visible_members'] ?? []"
                                    :overflow-count="$file['assignees']['overflow_count'] ?? 0"
                                />
                            @endif
                            <button type="button" wire:click="togglePinFile({{ $file['id'] }})" class="kanban-files-icon-btn" title="Favorito">
                                @if ($isPinned)
                                    <x-heroicon-s-star class="size-4 text-amber-400" />
                                @else
                                    <x-heroicon-o-star class="size-4" />
                                @endif
                            </button>
                            <a href="{{ $file['download_url'] }}" target="_blank" rel="noopener" class="kanban-files-icon-btn" title="Abrir archivo">
                                <x-heroicon-o-arrow-down-tray class="size-4" />
                            </a>
                            <a href="{{ $file['activity_view_url'] }}" class="kanban-files-icon-btn" title="Ver actividad">
                                <x-heroicon-o-eye class="size-4" />
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="kanban-files-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                @foreach ($items as $file)
                    @php $isPinned = in_array($file['id'], $pinnedFileIds, true); @endphp
                    <article wire:key="kanban-file-grid-{{ $file['id'] }}" @class(['kanban-files-card kanban-files-card--grid group', 'kanban-files-card--pinned' => $isPinned])>
                        <div class="kanban-files-card__top flex items-start justify-between gap-2">
                            <button type="button" wire:click="togglePinFile({{ $file['id'] }})" class="kanban-files-icon-btn" title="Favorito">
                                @if ($isPinned)
                                    <x-heroicon-s-star class="size-4 text-amber-400" />
                                @else
                                    <x-heroicon-o-star class="size-4" />
                                @endif
                            </button>

                            <div class="flex items-center gap-1.5">
                                <span class="kanban-files-sync-badge inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold">
                                    <x-heroicon-s-cloud-arrow-up class="size-3" />
                                    Sync
                                </span>
                                <details class="kanban-files-menu relative">
                                    <summary class="kanban-files-icon-btn list-none cursor-pointer">
                                        <x-heroicon-m-ellipsis-vertical class="size-4" />
                                    </summary>
                                    <div class="kanban-files-menu__panel absolute right-0 z-20 mt-1 min-w-[10rem] overflow-hidden rounded-xl py-1 shadow-xl">
                                        <a href="{{ $file['download_url'] }}" target="_blank" rel="noopener" class="kanban-files-menu__item block px-3 py-2 text-xs font-medium">Abrir archivo</a>
                                        <a href="{{ $file['activity_view_url'] }}" class="kanban-files-menu__item block px-3 py-2 text-xs font-medium">Ver actividad</a>
                                    </div>
                                </details>
                            </div>
                        </div>

                        <div class="kanban-files-card__preview mx-auto my-5 flex flex-col items-center gap-2">
                            @if ($file['category'] === 'image')
                                <x-heroicon-o-photo class="size-10 opacity-80" />
                            @elseif ($file['category'] === 'document')
                                <x-heroicon-o-document-text class="size-10 opacity-80" />
                            @else
                                <x-heroicon-o-document class="size-10 opacity-80" />
                            @endif
                            <span class="text-sm font-bold uppercase tracking-[0.14em]">{{ $file['extension'] }}</span>
                            <span class="kanban-files-card__size text-xs font-medium">{{ $file['size_label'] }}</span>
                        </div>

                        <div class="kanban-files-card__footer mt-auto space-y-2">
                            <div class="flex items-end justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="kanban-files-card__name truncate text-sm font-semibold">{{ $file['name'] }}</p>
                                    <p class="kanban-files-card__date mt-1 text-[11px]">{{ $file['uploaded_at'] }}</p>
                                    <p class="kanban-files-card__context mt-1 truncate text-[10px] font-medium">
                                        <span class="inline-block size-1.5 rounded-full align-middle" style="background: {{ $file['project_color'] }};"></span>
                                        {{ $file['project_name'] }}
                                    </p>
                                </div>
                                @if (($file['assignees']['total_count'] ?? 0) > 0)
                                    <x-collaborator-avatar-stack
                                        class="kanban-files-card__avatars shrink-0"
                                        align="end"
                                        :avatars="$file['assignees']['visible_members'] ?? []"
                                        :overflow-count="$file['assignees']['overflow_count'] ?? 0"
                                    />
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>

<style>
    .kanban-files {
        --kf-surface: #ffffff;
        --kf-surface-border: rgba(15, 23, 42, 0.1);
        --kf-toolbar-bg: rgba(248, 250, 252, 0.95);
        --kf-toolbar-border: rgba(15, 23, 42, 0.08);
        --kf-body-bg: #f8fafc;
        --kf-tab-text: rgb(100, 116, 139);
        --kf-tab-active: rgb(29, 78, 216);
        --kf-tab-active-border: rgb(37, 99, 235);
        --kf-tab-track-bg: rgba(241, 245, 249, 0.95);
        --kf-tab-track-border: rgba(15, 23, 42, 0.08);
        --kf-tab-idle-hover: rgba(255, 255, 255, 0.85);
        --kf-tab-active-bg: #ffffff;
        --kf-tab-active-shadow: 0 8px 22px -14px rgba(37, 99, 235, 0.35), inset 0 0 0 1px rgba(37, 99, 235, 0.12);
        --kf-tab-icon-bg: rgba(255, 255, 255, 0.75);
        --kf-tab-icon-text: rgb(100, 116, 139);
        --kf-tab-icon-active-bg: rgba(219, 234, 254, 0.95);
        --kf-tab-icon-active-text: rgb(29, 78, 216);
        --kf-tab-count-bg: rgba(226, 232, 240, 0.95);
        --kf-tab-count-text: rgb(71, 85, 105);
        --kf-tab-count-active-bg: rgba(37, 99, 235, 0.14);
        --kf-tab-count-active-text: rgb(29, 78, 216);
        --kf-card-bg: #ffffff;
        --kf-card-border: rgba(15, 23, 42, 0.08);
        --kf-card-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        --kf-card-hover-border: rgba(37, 99, 235, 0.35);
        --kf-card-hover-shadow: 0 14px 34px -18px rgba(37, 99, 235, 0.28);
        --kf-card-pinned-border: rgba(251, 191, 36, 0.45);
        --kf-preview-bg: rgba(241, 245, 249, 0.95);
        --kf-preview-text: rgb(71, 85, 105);
        --kf-name-text: rgb(15, 23, 42);
        --kf-meta-text: rgb(100, 116, 139);
        --kf-date-text: rgb(148, 163, 184);
        --kf-icon-btn: rgb(100, 116, 139);
        --kf-icon-btn-hover: rgb(15, 23, 42);
        --kf-icon-btn-bg: rgba(241, 245, 249, 0.9);
        --kf-sort-bg: #ffffff;
        --kf-sort-border: rgba(15, 23, 42, 0.1);
        --kf-sort-text: rgb(51, 65, 85);
        --kf-layout-bg: rgba(241, 245, 249, 0.95);
        --kf-layout-active-bg: #ffffff;
        --kf-layout-active-text: rgb(37, 99, 235);
        --kf-sync-bg: rgba(219, 234, 254, 0.9);
        --kf-sync-text: rgb(29, 78, 216);
        --kf-menu-bg: #ffffff;
        --kf-menu-border: rgba(15, 23, 42, 0.08);
        --kf-menu-item: rgb(51, 65, 85);
        --kf-menu-item-hover: rgb(241, 245, 249);
        --kf-empty-icon-bg: rgba(241, 245, 249, 0.95);
        --kf-empty-icon-text: rgb(148, 163, 184);
        --kf-empty-title: rgb(51, 65, 85);
        --kf-empty-text: rgb(100, 116, 139);

        border: 1px solid var(--kf-surface-border);
        background: var(--kf-surface);
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        color-scheme: light;
    }

    :is(.dark, .dark *) .kanban-files {
        --kf-surface: #0b0f17;
        --kf-surface-border: rgba(255, 255, 255, 0.08);
        --kf-toolbar-bg: rgba(10, 14, 22, 0.98);
        --kf-toolbar-border: rgba(255, 255, 255, 0.06);
        --kf-body-bg: #070a10;
        --kf-tab-text: rgb(148, 163, 184);
        --kf-tab-active: rgb(248, 250, 252);
        --kf-tab-active-border: rgb(59, 130, 246);
        --kf-tab-track-bg: rgba(255, 255, 255, 0.04);
        --kf-tab-track-border: rgba(255, 255, 255, 0.07);
        --kf-tab-idle-hover: rgba(255, 255, 255, 0.06);
        --kf-tab-active-bg: rgba(255, 255, 255, 0.08);
        --kf-tab-active-shadow: 0 10px 28px -16px rgba(59, 130, 246, 0.55), inset 0 0 0 1px rgba(96, 165, 250, 0.28);
        --kf-tab-icon-bg: rgba(255, 255, 255, 0.05);
        --kf-tab-icon-text: rgb(148, 163, 184);
        --kf-tab-icon-active-bg: rgba(59, 130, 246, 0.18);
        --kf-tab-icon-active-text: rgb(147, 197, 253);
        --kf-tab-count-bg: rgba(255, 255, 255, 0.07);
        --kf-tab-count-text: rgb(203, 213, 225);
        --kf-tab-count-active-bg: rgba(59, 130, 246, 0.22);
        --kf-tab-count-active-text: rgb(191, 219, 254);
        --kf-card-bg: #12161f;
        --kf-card-border: rgba(255, 255, 255, 0.07);
        --kf-card-shadow: 0 1px 0 rgba(255, 255, 255, 0.03);
        --kf-card-hover-border: rgba(96, 165, 250, 0.4);
        --kf-card-hover-shadow: 0 18px 40px -20px rgba(59, 130, 246, 0.35);
        --kf-card-pinned-border: rgba(251, 191, 36, 0.35);
        --kf-preview-bg: rgba(255, 255, 255, 0.04);
        --kf-preview-text: rgb(203, 213, 225);
        --kf-name-text: rgb(248, 250, 252);
        --kf-meta-text: rgb(148, 163, 184);
        --kf-date-text: rgb(100, 116, 139);
        --kf-icon-btn: rgb(148, 163, 184);
        --kf-icon-btn-hover: rgb(248, 250, 252);
        --kf-icon-btn-bg: rgba(255, 255, 255, 0.05);
        --kf-sort-bg: rgba(255, 255, 255, 0.04);
        --kf-sort-border: rgba(255, 255, 255, 0.1);
        --kf-sort-text: rgb(226, 232, 240);
        --kf-layout-bg: rgba(255, 255, 255, 0.05);
        --kf-layout-active-bg: rgba(255, 255, 255, 0.1);
        --kf-layout-active-text: rgb(147, 197, 253);
        --kf-sync-bg: rgba(59, 130, 246, 0.16);
        --kf-sync-text: rgb(147, 197, 253);
        --kf-menu-bg: #161923;
        --kf-menu-border: rgba(255, 255, 255, 0.08);
        --kf-menu-item: rgb(226, 232, 240);
        --kf-menu-item-hover: rgba(255, 255, 255, 0.06);
        --kf-empty-icon-bg: rgba(255, 255, 255, 0.04);
        --kf-empty-icon-text: rgb(100, 116, 139);
        --kf-empty-title: rgb(226, 232, 240);
        --kf-empty-text: rgb(100, 116, 139);

        color-scheme: dark;
    }

    .kanban-files-toolbar {
        background: var(--kf-toolbar-bg);
        border-color: var(--kf-toolbar-border);
    }

    .kanban-files-body {
        background: var(--kf-body-bg);
    }

    .kanban-files-tabs__track {
        border: 1px solid var(--kf-tab-track-border);
        background: var(--kf-tab-track-bg);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
    }

    .kanban-files-tab {
        color: var(--kf-tab-text);
    }

    .kanban-files-tab:hover:not(.kanban-files-tab--active) {
        background: var(--kf-tab-idle-hover);
        color: var(--kf-name-text);
    }

    .kanban-files-tab--active {
        color: var(--kf-tab-active);
        background: var(--kf-tab-active-bg);
        box-shadow: var(--kf-tab-active-shadow);
    }

    .kanban-files-tab__icon {
        background: var(--kf-tab-icon-bg);
        color: var(--kf-tab-icon-text);
        transition: background 180ms ease, color 180ms ease;
    }

    .kanban-files-tab--active .kanban-files-tab__icon {
        background: var(--kf-tab-icon-active-bg);
        color: var(--kf-tab-icon-active-text);
    }

    .kanban-files-tab__label {
        letter-spacing: 0.01em;
    }

    .kanban-files-tab__count {
        background: var(--kf-tab-count-bg);
        color: var(--kf-tab-count-text);
        transition: background 180ms ease, color 180ms ease, transform 180ms ease;
    }

    .kanban-files-tab__count--active,
    .kanban-files-tab--active .kanban-files-tab__count {
        background: var(--kf-tab-count-active-bg);
        color: var(--kf-tab-count-active-text);
        transform: scale(1.04);
    }

    .kanban-files-sort {
        color: var(--kf-meta-text);
    }

    .kanban-files-sort__select {
        border: 1px solid var(--kf-sort-border);
        background: var(--kf-sort-bg);
        color: var(--kf-sort-text);
    }

    .kanban-files-layout-toggle {
        background: var(--kf-layout-bg);
    }

    .kanban-files-layout-btn {
        color: var(--kf-icon-btn);
    }

    .kanban-files-layout-btn--active {
        background: var(--kf-layout-active-bg);
        color: var(--kf-layout-active-text);
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
    }

    .kanban-files-card {
        border: 1px solid var(--kf-card-border);
        background: var(--kf-card-bg);
        box-shadow: var(--kf-card-shadow);
        transition:
            border-color 220ms ease,
            box-shadow 220ms ease,
            transform 220ms cubic-bezier(0.25, 1, 0.5, 1);
    }

    .kanban-files-card:hover {
        border-color: var(--kf-card-hover-border);
        box-shadow: var(--kf-card-hover-shadow);
        transform: translateY(-2px);
    }

    .kanban-files-card--pinned {
        border-color: var(--kf-card-pinned-border);
    }

    .kanban-files-card--grid {
        display: flex;
        min-height: 15.5rem;
        flex-direction: column;
        border-radius: 1.25rem;
        padding: 1rem;
    }

    .kanban-files-card--list {
        display: flex;
        align-items: center;
        gap: 1rem;
        border-radius: 1rem;
        padding: 0.85rem 1rem;
    }

    .kanban-files-card__preview {
        color: var(--kf-preview-text);
    }

    .kanban-files-card__preview--list {
        display: flex;
        width: 3.25rem;
        height: 3.25rem;
        shrink: 0;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.15rem;
        border-radius: 0.85rem;
        background: var(--kf-preview-bg);
    }

    .kanban-files-card__name {
        color: var(--kf-name-text);
    }

    .kanban-files-card__meta,
    .kanban-files-card__context {
        color: var(--kf-meta-text);
    }

    .kanban-files-card__date,
    .kanban-files-card__size {
        color: var(--kf-date-text);
    }

    .kanban-files-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.55rem;
        padding: 0.35rem;
        color: var(--kf-icon-btn);
        background: transparent;
        transition: color 160ms ease, background 160ms ease;
    }

    .kanban-files-icon-btn:hover {
        color: var(--kf-icon-btn-hover);
        background: var(--kf-icon-btn-bg);
    }

    .kanban-files-sync-badge {
        background: var(--kf-sync-bg);
        color: var(--kf-sync-text);
    }

    .kanban-files-menu__panel {
        border: 1px solid var(--kf-menu-border);
        background: var(--kf-menu-bg);
    }

    .kanban-files-menu__item {
        color: var(--kf-menu-item);
    }

    .kanban-files-menu__item:hover {
        background: var(--kf-menu-item-hover);
    }

    .kanban-files-card__avatars .tdg-calendar-avatar-stack__item {
        width: 1.375rem;
        height: 1.375rem;
        font-size: 9px;
    }

    .kanban-files-empty-icon {
        background: var(--kf-empty-icon-bg);
        color: var(--kf-empty-icon-text);
    }

    .kanban-files-empty-title {
        color: var(--kf-empty-title);
    }

    .kanban-files-empty-text {
        color: var(--kf-empty-text);
    }

    .kanban-files-card {
        animation: kanban-files-card-in 420ms cubic-bezier(0.25, 1, 0.5, 1) both;
    }

    @keyframes kanban-files-card-in {
        from {
            opacity: 0;
            transform: translateY(8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
