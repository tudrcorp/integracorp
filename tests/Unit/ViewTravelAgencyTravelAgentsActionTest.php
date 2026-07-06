<?php

declare(strict_types=1);

$basePath = dirname(__DIR__, 2);

it('agrega la accion de cabecera para registrar agentes en la agencia de viajes', function () use ($basePath): void {
    $source = file_get_contents($basePath.'/app/Filament/Business/Resources/TravelAgencies/Pages/ViewTravelAgency.php');

    expect($source)
        ->toContain("Action::make('addTravelAgents')")
        ->toContain("->label('Agregar agentes')")
        ->toContain('TravelAgencyForm::travelAgentsRepeater(useRelationship: false)')
        ->toContain('travelAgents()->create(')
        ->toContain("\$createdBy = Auth::user()?->name ?? 'Analista'")
        ->toContain('AUDIT_BUSINESS_TRAVEL_AGENCY_AGENTS_ADDED')
        ->toContain("'module' => 'travel_agencies'")
        ->toContain("FilamentIosButton::extraClassForFilamentColor('warning')");
});

it('expone el repeater de agentes reutilizable en el formulario de agencia de viajes', function () use ($basePath): void {
    $source = file_get_contents($basePath.'/app/Filament/Business/Resources/TravelAgencies/Schemas/TravelAgencyForm.php');

    expect($source)
        ->toContain('public static function travelAgentsRepeater(bool $useRelationship = true): Repeater')
        ->toContain("Repeater::make('travelAgents')")
        ->toContain("TableColumn::make('Nombre y Apellido')")
        ->toContain("DatePicker::make('fechaNacimiento')")
        ->toContain('self::travelAgentsRepeater()');
});

it('muestra los agentes en una pestaña dedicada del infolist de agencia de viajes', function () use ($basePath): void {
    $source = file_get_contents($basePath.'/app/Filament/Business/Resources/TravelAgencies/Schemas/TravelAgencyInfolist.php');

    expect($source)
        ->toContain("Tab::make('Agentes')")
        ->toContain("RepeatableEntry::make('travelAgents')")
        ->toContain("TextEntry::make('name')")
        ->toContain("TextEntry::make('cargo')")
        ->toContain("TextEntry::make('email')")
        ->toContain("TextEntry::make('phone')")
        ->toContain("TextEntry::make('fechaNacimiento')");
});

it('ordena los agentes de la mas nueva a la mas vieja en el modelo', function () use ($basePath): void {
    $source = file_get_contents($basePath.'/app/Models/TravelAgency.php');

    expect($source)
        ->toContain('public function travelAgents()')
        ->toContain("->orderByDesc('created_at')")
        ->toContain("->orderByDesc('id')");
});
