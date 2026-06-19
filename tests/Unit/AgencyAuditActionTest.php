<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agencies\Pages\ViewAgency;

it('expone el catalogo de auditoria de agencia con los seis puntos requeridos', function (): void {
    $catalog = ViewAgency::auditItemsCatalog();

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

it('agrega el boton de auditoria warning que registra en la bitacora con INTEGRACORP-AUDITORIA', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Pages/ViewAgency.php');

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

it('el modelo Agency castea audit_items a array', function (): void {
    $casts = (new App\Models\Agency)->getCasts();

    expect($casts)->toHaveKey('audit_items')
        ->and($casts['audit_items'])->toBe('array');
});
