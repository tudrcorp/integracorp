@php
    /** @var \App\Models\Collection $followUpRecord */
    $followUpRecord = $getRecord();
    $followUpStatus = $followUpRecord->follow_up_status ?: null;
    $followUpEditable = (bool) ($canEdit ?? false);
    $followUpOptions = \App\Enums\CollectionFollowUpStatus::cases();
    $followUpRetired = \App\Enums\CollectionFollowUpStatus::RETIRED;
    $followUpExclusive = array_values(array_map(fn ($case) => $case->value, array_filter($followUpOptions, fn ($case) => $case->isExclusive())));
@endphp

@once
    <style>
        .ic-fu { display: grid; grid-template-columns: repeat(3, auto); gap: 3px; }
        .ic-fu-opt { display: flex; align-items: center; gap: 5px; border-radius: 6px; padding: 3px 6px; font-size: 11px; line-height: 1.2; font-weight: 500; color: rgb(75 85 99); background: rgb(243 244 246); border: 1px solid transparent; text-align: start; white-space: nowrap; transition: background-color 120ms ease, color 120ms ease, border-color 120ms ease; }
        .dark .ic-fu-opt { color: rgb(209 213 219); background: rgb(255 255 255 / 0.06); }
        .ic-fu-opt:not(:disabled):hover { border-color: var(--fu-color); color: var(--fu-color); }
        .ic-fu-opt:focus-visible { outline: 2px solid var(--fu-color); outline-offset: 1px; }
        .ic-fu-opt:disabled { cursor: default; }
        .ic-fu-box { display: inline-flex; width: 12px; height: 12px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 3px; border: 1.5px solid currentColor; opacity: 0.7; }
        .ic-fu-opt.is-on { color: #fff; background: var(--fu-color); border-color: var(--fu-color); font-weight: 600; }
        .ic-fu-opt.is-on .ic-fu-box { opacity: 1; background: #fff; border-color: #fff; }
        .ic-fu-opt.is-on .ic-fu-box::after { content: ''; width: 6px; height: 3px; margin-top: -2px; border-left: 2px solid var(--fu-color); border-bottom: 2px solid var(--fu-color); transform: rotate(-45deg); }
        .ic-fu.is-saving { opacity: 0.55; }
        .ic-fu-retired { grid-column: 1 / -1; font-size: 10.5px; line-height: 1.25; color: rgb(107 114 128); white-space: normal; }
        .ic-fu-retired button { font-weight: 600; text-decoration: underline; color: rgb(55 65 81); }
        .dark .ic-fu-retired button { color: rgb(229 231 235); }
        .ic-fu-msg { grid-column: 1 / -1; font-size: 10.5px; line-height: 1.25; color: rgb(185 28 28); white-space: normal; }
        .dark .ic-fu-msg { color: rgb(252 165 165); }
    </style>
@endonce

<div
    wire:key="follow-up-{{ $followUpRecord->getKey() }}-{{ $followUpStatus ?? 'none' }}"
    x-data="{
        status: @js($followUpStatus),
        saving: false,
        message: null,
        timer: null,
        order: @js(array_map(fn ($case) => $case->value, $followUpOptions)),
        exclusive: @js($followUpExclusive),
        retired: @js($followUpRetired),
        selected() {
            return (this.status ?? '').split(',').filter((value) => this.order.includes(value));
        },
        isOn(value) {
            return this.selected().includes(value);
        },
        {{-- Misma regla que el servidor (CollectionFollowUpStatus::toggle), solo para responder al instante. --}}
        toggled(value) {
            const current = this.selected();

            if (current.includes(value)) {
                return current.filter((item) => item !== value);
            }

            if (this.exclusive.includes(value)) {
                return [value];
            }

            return [...current.filter((item) => ! this.exclusive.includes(item)), value];
        },
        encode(values) {
            const ordered = this.order.filter((value) => values.includes(value));

            return ordered.length ? ordered.join(',') : null;
        },
        async send(option, optimistic) {
            if (this.saving || ! @js($followUpEditable)) { return; }
            const previous = this.status;
            this.status = optimistic;
            this.saving = true;
            this.message = null;
            try {
                const result = await $wire.setCollectionFollowUp({{ (int) $followUpRecord->getKey() }}, option, previous);
                this.status = result?.status ?? null;
                if (! result?.ok) { this.flash(result?.message ?? 'No se guardó el estado.'); }
            } catch (error) {
                this.status = previous;
                this.flash('No se guardó el estado. Revise su conexión e intente de nuevo.');
            } finally {
                this.saving = false;
            }
        },
        pick(value) {
            this.send(value, this.encode(this.toggled(value)));
        },
        clear() {
            this.send(null, null);
        },
        flash(text) {
            this.message = text;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => { this.message = null; }, 6000);
        },
    }"
    x-on:click.stop
    class="ic-fu"
    x-bind:class="saving && 'is-saving'"
    role="group"
    aria-label="Estado de gestión de la cuota {{ $followUpRecord->collection_invoice_number }}"
>
    @foreach ($followUpOptions as $option)
        <button
            type="button"
            class="ic-fu-opt"
            style="--fu-color: {{ $option->hex() }};"
            role="checkbox"
            x-bind:class="isOn(@js($option->value)) && 'is-on'"
            x-bind:aria-checked="isOn(@js($option->value)) ? 'true' : 'false'"
            x-bind:disabled="saving"
            @disabled(! $followUpEditable)
            x-on:click="pick(@js($option->value))"
            title="{{ $followUpEditable ? ($option->isExclusive() ? 'Marcar «'.$option->label().'»: apaga los otros estados.' : 'Marcar «'.$option->label().'»: puede ir junto con el otro aviso y apaga «Aviso en revisión».').' Púlselo de nuevo para quitarlo.' : $option->label() }}"
        >
            <span class="ic-fu-box" aria-hidden="true"></span>
            {{ $option->label() }}
        </button>
    @endforeach

    <span class="ic-fu-retired" x-show="retired[status]" x-cloak>
        Estaba en «<span x-text="retired[status]"></span>», que ya no se usa. Marque otro estado
        @if ($followUpEditable)
            o <button type="button" x-bind:disabled="saving" x-on:click="clear()">quítelo</button>
        @endif
    </span>

    <span class="ic-fu-msg" x-show="message" x-text="message" x-cloak role="alert"></span>
</div>
