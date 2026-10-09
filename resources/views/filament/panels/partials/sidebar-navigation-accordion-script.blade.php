@props([
    /** @var list<string> $navigationGroupLabels */
    'navigationGroupLabels' => [],
    'accordionStorageKey' => 'navigationAccordionV1',
    'patchFlag' => '__navigationAccordionPatched',
    /** @var list<string> Grupos fuera del acordeón: abiertos al entrar y no se cierran al abrir otro. */
    'alwaysOpenGroupLabels' => [\App\Support\Filament\SharedNavigationGroups::USER],
])

<script>
    (() => {
        const navigationGroupLabels = @js($navigationGroupLabels);
        const accordionStorageKey = @js($accordionStorageKey);
        const patchFlag = @js($patchFlag);
        const alwaysOpenGroupLabels = @js($alwaysOpenGroupLabels);
        const isAccordionGroup = (label) => ! alwaysOpenGroupLabels.includes(label);

        const normalizeCollapsedGroups = (sidebar) => {
            const accordionGroupLabels = navigationGroupLabels.filter(isAccordionGroup);

            if (! Array.isArray(sidebar.collapsedGroups)) {
                sidebar.collapsedGroups = [...accordionGroupLabels];

                return;
            }

            if (! localStorage.getItem(accordionStorageKey)) {
                sidebar.collapsedGroups = [...accordionGroupLabels];
                localStorage.setItem(accordionStorageKey, '1');

                return;
            }

            sidebar.collapsedGroups = sidebar.collapsedGroups.filter(isAccordionGroup);
        };

        const patchSidebarAccordion = () => {
            const sidebar = window.Alpine?.store('sidebar');

            if (! sidebar || sidebar[patchFlag]) {
                return;
            }

            sidebar[patchFlag] = true;

            normalizeCollapsedGroups(sidebar);

            sidebar.toggleCollapsedGroup = function (group) {
                const allGroupLabels = Array.from(
                    document.querySelectorAll('.fi-main-sidebar .fi-sidebar-group[data-group-label]'),
                )
                    .map((element) => element.dataset.groupLabel)
                    .filter(Boolean);

                if (this.collapsedGroups.includes(group)) {
                    this.collapsedGroups = allGroupLabels.filter((label) => label !== group && isAccordionGroup(label));

                    return;
                }

                if (! this.collapsedGroups.includes(group)) {
                    this.collapsedGroups = this.collapsedGroups.concat(group);
                }
            };
        };

        document.addEventListener('alpine:init', patchSidebarAccordion);
        document.addEventListener('livewire:navigated', patchSidebarAccordion);

        if (window.Alpine?.store('sidebar')) {
            patchSidebarAccordion();
        }
    })();
</script>
