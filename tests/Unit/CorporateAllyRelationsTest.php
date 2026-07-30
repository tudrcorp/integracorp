<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\CorporateAllies\CorporateAllyResource;
use App\Models\CorporateAlly;
use App\Models\CorporateAllyObservacion;

it('gestiona observaciones desde formulario e infolist sin relation manager', function () {
    expect(CorporateAllyResource::getRelations())->toBeEmpty();

    $formPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/CorporateAllies/Schemas/CorporateAllyForm.php';
    $infolistPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/CorporateAllies/Schemas/CorporateAllyInfolist.php';

    expect(file_get_contents($formPath))->toContain("Repeater::make('corporateAllyObservacions')");
    expect(file_get_contents($infolistPath))->toContain("RepeatableEntry::make('corporateAllyObservacions')");
});

it('modelo corporate ally observacion define tabla y relación', function () {
    $observacion = new CorporateAllyObservacion;

    expect($observacion->getTable())->toBe('corporate_ally_observacions')
        ->and($observacion->getFillable())->toContain(
            'corporate_ally_id',
            'observation',
            'created_by',
            'updated_by',
        );

    $ally = new CorporateAlly;

    expect(method_exists($ally, 'corporateAllyObservacions'))->toBeTrue()
        ->and(method_exists($ally, 'state'))->toBeTrue()
        ->and(method_exists($ally, 'city'))->toBeTrue();
});

it('tabla de aliados corporativos compacta ubicación contacto y acciones', function () {
    $tablePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/CorporateAllies/Tables/CorporateAlliesTable.php';
    $contents = file_get_contents($tablePath);

    expect($contents)
        ->toContain("TextColumn::make('company_name')")
        ->toContain("TextColumn::make('location')")
        ->toContain("TextColumn::make('contact')")
        ->toContain('formatLocation')
        ->toContain('formatContactHtml')
        ->toContain('ActionGroup::make([')
        ->toContain('CreateAction::make()')
        ->toContain("->label('Estatus convenio')")
        ->toContain("->label('Estatus')")
        ->toContain('isToggledHiddenByDefault: true')
        ->toContain("->description('Use filtros y columnas ocultas para afinar por ubicación, convenio, contacto o datos de pago.')");
});
