<div
    class="hidden"
    x-data
    x-init="
        const stepEl = $el.closest('.fi-sc-wizard-step');
        if (! stepEl) {
            return;
        }
        let opened = false;
        const tryOpen = () => {
            if (! stepEl.classList.contains('fi-active')) {
                opened = false;
                return;
            }
            if (opened) {
                return;
            }
            opened = true;
            Livewire.dispatch('open-medicamentos-step-info-modal');
        };
        tryOpen();
        new MutationObserver(tryOpen).observe(stepEl, {
            attributes: true,
            attributeFilter: ['class'],
        });
    "
></div>
