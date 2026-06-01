@php
    $documentId = (int) ($fileId ?? 0);
    $normalizedPinnedFileIds = $normalizedPinnedFileIds ?? [];
@endphp

<label
    wire:key="kanban-pin-label-{{ $documentId }}"
    @class([
        'kanban-files-icon-btn kanban-files-pin-btn cursor-pointer',
        'kanban-files-pin-btn--active' => in_array($documentId, $normalizedPinnedFileIds, true),
    ])
    title="{{ in_array($documentId, $normalizedPinnedFileIds, true) ? 'Quitar de favoritos' : 'Marcar favorito' }}"
>
    <input
        type="checkbox"
        class="peer sr-only"
        wire:model.live="pinnedFileIds"
        value="{{ (string) $documentId }}"
        wire:key="kanban-pin-checkbox-{{ $documentId }}"
        aria-label="{{ in_array($documentId, $normalizedPinnedFileIds, true) ? 'Quitar de favoritos' : 'Marcar favorito' }}"
    />
    <x-heroicon-o-star class="kanban-files-pin-btn__icon size-5 peer-checked:hidden" />
    <x-heroicon-s-star class="kanban-files-pin-btn__icon kanban-files-pin-btn__icon--active hidden size-5 peer-checked:inline-flex" />
</label>
