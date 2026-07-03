@php
    use App\Support\Filament\BusinessPanelNavigationGroups;

    $navigationGroupLabels = BusinessPanelNavigationGroups::labels();
@endphp

<script>
    (() => {
        const businessNavigationGroupLabels = @js($navigationGroupLabels);
        const accordionStorageKey = 'businessNavigationAccordionV1';

        const normalizeCollapsedGroups = (sidebar) => {
            if (! Array.isArray(sidebar.collapsedGroups)) {
                sidebar.collapsedGroups = [...businessNavigationGroupLabels];

                return;
            }

            if (! localStorage.getItem(accordionStorageKey)) {
                sidebar.collapsedGroups = [...businessNavigationGroupLabels];
                localStorage.setItem(accordionStorageKey, '1');
            }
        };

        const patchSidebarAccordion = () => {
            const sidebar = window.Alpine?.store('sidebar');

            if (! sidebar || sidebar.__businessNavigationAccordionPatched) {
                return;
            }

            sidebar.__businessNavigationAccordionPatched = true;

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
