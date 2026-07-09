@php
    use App\Support\Filament\OperationsPanelNavigationGroups;
@endphp

@include('filament.panels.partials.sidebar-navigation-accordion-script', [
    'navigationGroupLabels' => OperationsPanelNavigationGroups::labels(),
    'accordionStorageKey' => 'operationsNavigationAccordionV1',
    'patchFlag' => '__operationsNavigationAccordionPatched',
])
