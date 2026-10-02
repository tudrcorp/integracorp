@props([
    'target',
    'idle',
    'loading',
])

<button
    type="submit"
    class="ic-auth-submit"
    wire:loading.attr="disabled"
>
    <span wire:loading.remove wire:target="{{ $target }}">{{ $idle }}</span>
    <span wire:loading wire:target="{{ $target }}">{{ $loading }}</span>
    <span class="ic-auth-submit__line" wire:loading.remove wire:target="{{ $target }}" aria-hidden="true"></span>
</button>
