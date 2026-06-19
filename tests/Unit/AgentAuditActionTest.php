<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agents\Pages\ViewAgent;

it('expone el catalogo de auditoria de agente con los seis puntos requeridos', function (): void {
    $catalog = ViewAgent::auditItemsCatalog();

    expect($catalog)->toHaveKeys([
        'main_info',
        'hierarchy',
        'commissions',
        'bank_national',
        'bank_foreign',
        'documents',
    ]);

    foreach ($catalog as $item) {
        expect($item)->toHaveKeys(['label', 'detail']);
    }
});

it('agrega el boton de auditoria warning que registra en la bitacora del agente con INTEGRACORP-AUDITORIA', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Pages/ViewAgent.php');

    expect($source)
        ->toContain("Action::make('audit')")
        ->toContain("->label('Auditoría')")
        ->toContain("->color('warning')")
        ->toContain("FilamentIosButton::extraClassForFilamentColor('warning')")
        ->toContain("CheckboxList::make('items')")
        ->toContain('pendingAuditItemOptions()')
        ->toContain("'created_by' => 'INTEGRACORP-AUDITORIA'")
        ->toContain('pendingAuditItems() !== []')
        ->toContain('observationCommercialStructures()->create(');
});

it('agrega la accion iOS para registrar observaciones en la cabecera del agente', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Pages/ViewAgent.php');

    expect($source)
        ->toContain("Action::make('addObservation')")
        ->toContain("->label('Agregar observación')")
        ->toContain("FilamentIosButton::extraClassForFilamentColor('info')")
        ->toContain("Textarea::make('observation')");
});

it('el modelo Agent castea audit_items a array', function (): void {
    $casts = (new App\Models\Agent)->getCasts();

    expect($casts)->toHaveKey('audit_items')
        ->and($casts['audit_items'])->toBe('array');
});
