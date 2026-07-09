@php
    use App\Support\Filament\AdministrationPanelNavigationGroups;
@endphp

@include('filament.panels.partials.sidebar-navigation-accordion-script', [
    'navigationGroupLabels' => AdministrationPanelNavigationGroups::labels(),
    'accordionStorageKey' => 'administrationNavigationAccordionV1',
    'patchFlag' => '__administrationNavigationAccordionPatched',
])
