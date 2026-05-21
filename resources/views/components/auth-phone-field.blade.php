<div class="flex flex-col gap-2">
    <flux:label class="text-sm font-medium text-zinc-800 dark:text-white">
        {{ __('WhatsApp / Teléfono') }}
    </flux:label>
    <flux:description>
        {{ __('Seleccione el país y escriba solo el número local (sin repetir el código). Se usará para WhatsApp.') }}
    </flux:description>

    <div
        class="flex w-full max-w-full flex-row flex-nowrap items-stretch overflow-hidden rounded-lg shadow-xs ring-1 ring-inset ring-zinc-300 transition-[box-shadow] focus-within:ring-2 focus-within:ring-[#0064a1] dark:ring-white/10 dark:focus-within:ring-white/25"
    >
        <div class="w-[5.75rem] shrink-0 border-e border-zinc-200 bg-zinc-50/80 dark:border-white/10 dark:bg-white/5">
            <flux:select
                wire:model.live="country_code"
                searchable
                variant="listbox"
                :placeholder="__('País')"
                class="h-full w-full [&_[data-flux-select-button]]:h-full [&_[data-flux-select-button]]:min-h-[2.5rem] [&_[data-flux-select-button]]:rounded-none [&_[data-flux-select-button]]:border-0 [&_[data-flux-select-button]]:bg-transparent [&_[data-flux-select-button]]:px-2 [&_[data-flux-select-button]]:text-sm [&_[data-flux-select-button]]:shadow-none [&_[data-flux-select-button]]:ring-0"
            >
                @foreach ($this->countryCodeOptions() as $code => $label)
                    <flux:select.option :value="$code">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="min-w-0 flex-1">
            <flux:input
                wire:model.live.debounce.150ms="phone"
                type="tel"
                inputmode="numeric"
                autocomplete="tel"
                :placeholder="$this->phonePlaceholder()"
                :maxlength="$this->isVenezuelaSelected() ? 13 : 18"
                class="h-full w-full [&_[data-flux-input]]:rounded-none [&_[data-flux-input]]:border-0 [&_[data-flux-input]]:bg-transparent [&_[data-flux-input]]:shadow-none [&_[data-flux-input]]:ring-0 focus:[&_[data-flux-input]]:ring-0 dark:[&_[data-flux-input]]:bg-transparent"
            />
        </div>
    </div>

    @if (filled($phone))
        <p @class([
            'text-xs',
            'text-emerald-600 dark:text-emerald-400' => $this->isPhoneValid(),
            'text-zinc-500 dark:text-zinc-400' => ! $this->isPhoneValid(),
        ])>
            @if ($this->isPhoneValid())
                {{ __('Listo para WhatsApp:') }}
            @else
                {{ __('Formato esperado:') }}
            @endif
            <span class="font-mono font-semibold">{{ $this->phoneForStorage() }}</span>
            @if (! $this->isPhoneValid() && $this->isVenezuelaSelected())
                <span class="block mt-0.5 text-zinc-400 dark:text-zinc-500">
                    {{ __('Ejemplo móvil: 0412 701 8390 · Ejemplo fijo: 0212 555 1234') }}
                </span>
            @elseif (! $this->isPhoneValid())
                <span class="block mt-0.5 text-zinc-400 dark:text-zinc-500">
                    {{ __('Ejemplo Colombia: 300 123 4567 · Ejemplo EE. UU.: 305 555 0100') }}
                </span>
            @endif
        </p>
    @endif

    <flux:error name="phone" />
    <flux:error name="country_code" />
</div>
