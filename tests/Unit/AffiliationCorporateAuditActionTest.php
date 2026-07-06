<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate;

it('expone el catalogo de auditoria corporativa con los seis puntos requeridos', function (): void {
    $catalog = ViewAffiliationCorporate::auditItemsCatalog();

    expect($catalog)->toHaveKeys([
        'affiliation_info',
        'payer_info',
        'affiliates_info',
        'medical_record',
        'main_documents',
        'ils_document',
    ]);

    foreach ($catalog as $item) {
        expect($item)->toHaveKeys(['label', 'detail']);
    }
});

it('agrega el boton de auditoria warning con checklist y autoria de INTEGRACORP-AUDITORIA en corporativo', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Pages/ViewAffiliationCorporate.php');

    expect($source)
        ->toContain("Action::make('audit')")
        ->toContain("->label('Auditoría')")
        ->toContain("->color('warning')")
        ->toContain("FilamentIosButton::extraClassForFilamentColor('warning')")
        ->toContain("CheckboxList::make('items')")
        ->toContain('pendingAuditItemOptions()')
        ->toContain("'created_by' => 'INTEGRACORP-AUDITORIA'")
        ->toContain('pendingAuditItems() !== []')
        ->toContain('affiliationCorporateObservations()->create(');
});

it('muestra el responsable de la observacion corporativa aunque no sea un usuario', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php');

    expect($source)
        ->toContain('$record->createdBy?->name ?? (string) ($record->created_by');
});
