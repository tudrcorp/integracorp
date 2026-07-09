@php
    use App\Support\Filament\BusinessPanelNavigationGroups;
@endphp

@include('filament.panels.partials.sidebar-navigation-accordion-script', [
    'navigationGroupLabels' => BusinessPanelNavigationGroups::labels(),
    'accordionStorageKey' => 'businessNavigationAccordionV1',
    'patchFlag' => '__businessNavigationAccordionPatched',
])
