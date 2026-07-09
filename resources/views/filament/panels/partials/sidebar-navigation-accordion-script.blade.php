@props([
    /** @var list<string> $navigationGroupLabels */
    'navigationGroupLabels' => [],
    'accordionStorageKey' => 'navigationAccordionV1',
    'patchFlag' => '__navigationAccordionPatched',
])

<script>
    (() => {
        const navigationGroupLabels = @js($navigationGroupLabels);
        const accordionStorageKey = @js($accordionStorageKey);
        const patchFlag = @js($patchFlag);

        const normalizeCollapsedGroups = (sidebar) => {
            if (! Array.isArray(sidebar.collapsedGroups)) {
                sidebar.collapsedGroups = [...navigationGroupLabels];

                return;
            }

            if (! localStorage.getItem(accordionStorageKey)) {
                sidebar.collapsedGroups = [...navigationGroupLabels];
                localStorage.setItem(accordionStorageKey, '1');
            }
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
                    this.collapsedGroups = allGroupLabels.filter((label) => label !== group);

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
