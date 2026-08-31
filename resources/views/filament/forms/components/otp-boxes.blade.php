@php
    $length = $getLength();
    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
    $autofocus = $isAutofocused();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <style>
        .ic-otp-boxes {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
        }

        .ic-otp-boxes input[type="text"] {
            width: 2.75rem;
            height: 3.85rem;
            padding: 0;
            border: 1px solid #d0d5dd;
            border-radius: 1.05rem;
            background: #eceff3;
            color: #111827;
            text-align: center;
            font-size: 1.4rem;
            font-weight: 650;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            caret-color: #052F60;
            transition: border-color 150ms ease, box-shadow 150ms ease, background-color 150ms ease;
        }

        .ic-otp-boxes input[type="text"]:hover:not(:disabled) {
            background: #e4e7ec;
        }

        .ic-otp-boxes input[type="text"]:focus {
            outline: none;
            background: #ffffff;
            border-color: #052F60;
            box-shadow: 0 0 0 3px rgba(5, 47, 96, 0.18);
        }

        .ic-otp-boxes input[type="text"]:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .dark .ic-otp-boxes input[type="text"] {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.16);
            color: #f9fafb;
            caret-color: #93c5fd;
        }

        .dark .ic-otp-boxes input[type="text"]:hover:not(:disabled) {
            background: rgba(255, 255, 255, 0.12);
        }

        .dark .ic-otp-boxes input[type="text"]:focus {
            background: rgba(15, 23, 42, 0.9);
            border-color: #93c5fd;
            box-shadow: 0 0 0 3px rgba(147, 197, 253, 0.22);
        }
    </style>

    <div
        class="ic-otp-boxes"
        x-data="{
            length: {{ $length }},
            digits: Array({{ $length }}).fill(''),
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')", isOptimisticallyLive: false) }},
            init() {
                this.hydrate(this.state)
                this.$watch('state', (value) => this.hydrate(value))
                @if ($autofocus)
                    this.$nextTick(() => this.focusAt(this.firstEmpty()))
                @endif
            },
            hydrate(value) {
                const raw = String(value ?? '').replace(/\D/g, '').slice(0, this.length)
                const next = Array.from({ length: this.length }, (_, index) => raw[index] ?? '')
                if (next.join('') !== this.digits.join('')) {
                    this.digits = next
                }
            },
            commit() {
                this.state = this.digits.join('')
            },
            firstEmpty() {
                const index = this.digits.findIndex((digit) => digit === '')
                return index === -1 ? this.length - 1 : index
            },
            focusAt(index) {
                this.$refs['box' + index]?.focus()
                this.$refs['box' + index]?.select()
            },
            onInput(index, event) {
                const incoming = String(event.target.value ?? '').replace(/\D/g, '')
                if (incoming.length > 1) {
                    this.fillFrom(incoming, index)
                    return
                }
                this.digits[index] = incoming.slice(-1)
                this.commit()
                if (this.digits[index] !== '' && index < this.length - 1) {
                    this.focusAt(index + 1)
                }
            },
            onKeydown(index, event) {
                if (event.key === 'Backspace' && this.digits[index] === '' && index > 0) {
                    this.digits[index - 1] = ''
                    this.commit()
                    this.focusAt(index - 1)
                    event.preventDefault()
                }
                if (event.key === 'ArrowLeft' && index > 0) {
                    this.focusAt(index - 1)
                    event.preventDefault()
                }
                if (event.key === 'ArrowRight' && index < this.length - 1) {
                    this.focusAt(index + 1)
                    event.preventDefault()
                }
            },
            onPaste(event) {
                event.preventDefault()
                this.fillFrom((event.clipboardData || window.clipboardData).getData('text') || '', 0)
            },
            fillFrom(raw, start) {
                const digits = String(raw).replace(/\D/g, '').slice(0, this.length - start).split('')
                digits.forEach((digit, offset) => {
                    this.digits[start + offset] = digit
                })
                this.commit()
                this.focusAt(Math.min(start + digits.length, this.length - 1))
            },
        }"
        wire:ignore.self
    >
        @for ($index = 0; $index < $length; $index++)
            <input
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="1"
                autocomplete="{{ $index === 0 ? 'one-time-code' : 'off' }}"
                aria-label="Dígito {{ $index + 1 }} de {{ $length }}"
                x-ref="box{{ $index }}"
                x-model="digits[{{ $index }}]"
                @input="onInput({{ $index }}, $event)"
                @keydown="onKeydown({{ $index }}, $event)"
                @paste="onPaste($event)"
                @disabled($isDisabled)
            />
        @endfor
    </div>
</x-dynamic-component>
