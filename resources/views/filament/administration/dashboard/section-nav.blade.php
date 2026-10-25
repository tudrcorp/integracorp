@php
    /** @var list<array{anchor: string, label: string, icon: string}> $sections */
@endphp

<x-filament-widgets::widget class="admin-section-nav sticky top-16 z-20">
    <nav
        aria-label="Secciones del escritorio"
        x-data="{
            active: @js($sections[0]['anchor'] ?? ''),
            observer: null,
            init() {
                this.observer = new IntersectionObserver((entries) => {
                    entries.filter((entry) => entry.isIntersecting).forEach((entry) => { this.active = entry.target.id; });
                }, { rootMargin: '-35% 0px -60% 0px' });
                this.$nextTick(() => {
                    @js(array_column($sections, 'anchor')).forEach((id) => {
                        const element = document.getElementById(id);
                        if (element) { this.observer.observe(element); }
                    });
                });
            },
            go(id) {
                const element = document.getElementById(id);
                if (!element) { return; }
                this.active = id;
                element.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
            },
            destroy() { this.observer?.disconnect(); },
        }"
        class="ops-hide-in-presentation flex gap-1 overflow-x-auto rounded-xl bg-white/90 p-1 shadow-sm ring-1 ring-gray-950/5 backdrop-blur [scrollbar-width:none] dark:bg-gray-900/90 dark:ring-white/10"
    >
        @foreach ($sections as $section)
            <button
                type="button"
                x-on:click="go(@js($section['anchor']))"
                x-bind:class="active === @js($section['anchor'])
                    ? 'bg-primary-50 text-primary-700 ring-1 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/30'
                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white'"
                class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition"
            >
                <x-filament::icon :icon="$section['icon']" class="size-4" />
                {{ $section['label'] }}
            </button>
        @endforeach
    </nav>
</x-filament-widgets::widget>
