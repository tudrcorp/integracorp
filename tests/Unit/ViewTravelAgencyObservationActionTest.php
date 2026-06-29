<?php

declare(strict_types=1);

$basePath = dirname(__DIR__, 2);

it('agrega la accion de cabecera para registrar observaciones en la agencia de viajes', function () use ($basePath): void {
    $source = file_get_contents($basePath.'/app/Filament/Business/Resources/TravelAgencies/Pages/ViewTravelAgency.php');

    expect($source)
        ->toContain("Action::make('addObservation')")
        ->toContain("->label('Agregar observación')")
        ->toContain("Textarea::make('observation')")
        ->toContain('observationCommercialStructures()->create(')
        ->toContain("\$createdBy = Auth::user()?->name ?? 'Analista'")
        ->toContain("'created_by' => \$createdBy")
        ->toContain('SecurityAudit::log')
        ->toContain('AUDIT_BUSINESS_TRAVEL_AGENCY_OBSERVATION_ADDED')
        ->toContain("'module' => 'travel_agencies'")
        ->toContain("FilamentIosButton::extraClassForFilamentColor('info')");
});

it('muestra las observaciones en el infolist de la agencia de viajes', function () use ($basePath): void {
    $source = file_get_contents($basePath.'/app/Filament/Business/Resources/TravelAgencies/Schemas/TravelAgencyInfolist.php');

    expect($source)
        ->toContain("RepeatableEntry::make('observationCommercialStructures')")
        ->toContain("TextEntry::make('observation')")
        ->toContain("TextEntry::make('created_by')")
        ->toContain("TextEntry::make('date')");
});

it('ordena las observaciones de la mas nueva a la mas vieja en el modelo', function () use ($basePath): void {
    $source = file_get_contents($basePath.'/app/Models/TravelAgency.php');

    expect($source)
        ->toContain('public function observationCommercialStructures()')
        ->toContain("->orderByDesc('created_at')")
        ->toContain("->orderByDesc('id')");
});
