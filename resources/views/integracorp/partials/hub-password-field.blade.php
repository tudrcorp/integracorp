@props([
    'id',
    'model',
    'label' => 'Contraseña',
    'autocomplete' => 'current-password',
])

<label
    class="ic-auth-field ic-auth-field--password"
    x-data="{ showPassword: false }"
    for="{{ $id }}"
>
    <span class="ic-auth-field__label">{{ $label }}</span>
    <div class="ic-auth-input-row ic-auth-input-row--password">
        <input
            id="{{ $id }}"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="{{ $autocomplete }}"
            class="ic-auth-input"
            wire:model="{{ $model }}"
            placeholder="••••••••"
            required
        />
        <button
            type="button"
            class="ic-auth-input__reveal"
            @click="showPassword = ! showPassword"
            :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
            :aria-pressed="showPassword.toString()"
        >
            <span x-show="! showPassword" x-cloak>Mostrar</span>
            <span x-show="showPassword" x-cloak>Ocultar</span>
        </button>
    </div>
</label>
