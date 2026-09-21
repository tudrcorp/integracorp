<span class="sf-btn__idle" wire:loading.remove @if ($target ?? '') wire:target="{{ $target }}" @endif>{{ $label }}</span>
<span class="sf-btn__busy hidden items-center justify-center gap-2" wire:loading.flex @if ($target ?? '') wire:target="{{ $target }}" @endif>
    <span class="sf-spinner" aria-hidden="true"></span>
    <span>{{ $wait ?? 'Un momento…' }}</span>
</span>
