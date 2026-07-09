@php
    use App\Support\Filament\MarketingPanelNavigationGroups;
@endphp

@include('filament.panels.partials.sidebar-navigation-accordion-script', [
    'navigationGroupLabels' => MarketingPanelNavigationGroups::labels(),
    'accordionStorageKey' => 'marketingNavigationAccordionV1',
    'patchFlag' => '__marketingNavigationAccordionPatched',
])
