<div
    class="ic-hub-theme-toggle-wrap"
    x-data="icHubThemeToggle()"
    x-init="init()"
    wire:ignore
>
    <button
        type="button"
        class="ic-hub-theme-switch"
        role="switch"
        :aria-checked="theme === 'light'"
        @click="toggle()"
        :aria-label="theme === 'dark' ? 'Activar modo blanco' : 'Activar modo negro'"
        :title="theme === 'dark' ? 'Modo blanco' : 'Modo negro'"
    >
        <span
            class="ic-hub-theme-switch__track"
            :class="{ 'is-jelly': jelly }"
            aria-hidden="true"
        >
            <span
                class="ic-hub-theme-switch__label ic-hub-theme-switch__label--dark"
                :class="{ 'is-visible': theme === 'dark' }"
            >Black</span>
            <span
                class="ic-hub-theme-switch__label ic-hub-theme-switch__label--light"
                :class="{ 'is-visible': theme === 'light' }"
            >White</span>
            <span
                class="ic-hub-theme-switch__thumb"
                :class="theme === 'light' ? 'is-light' : 'is-dark'"
            >
                <span
                    class="ic-hub-theme-switch__thumb-inner"
                    :class="{ 'is-jelly': jelly }"
                >
                <span class="ic-hub-theme-switch__thumb-glass"></span>
                <span class="ic-hub-theme-switch__thumb-shine"></span>
                <svg
                    x-show="theme === 'light'"
                    x-cloak
                    class="ic-hub-theme-switch__icon"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.75"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                </svg>
                <svg
                    x-show="theme === 'dark'"
                    x-cloak
                    class="ic-hub-theme-switch__icon"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.75"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                </svg>
                </span>
            </span>
        </span>
    </button>
</div>
