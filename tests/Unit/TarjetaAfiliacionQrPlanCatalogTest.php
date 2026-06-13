<?php

declare(strict_types=1);

use App\Support\TarjetaAfiliacionQrPlanCatalog;

uses(Tests\TestCase::class);

it('resuelve nombres de archivo qr por plan_id incluyendo legacy', function (): void {
    expect(TarjetaAfiliacionQrPlanCatalog::qrFilenameForPlanId(1))->toBe('qr-plan-inicial.png')
        ->and(TarjetaAfiliacionQrPlanCatalog::qrFilenameForPlanId(2))->toBe('qr-plan-ideal.png')
        ->and(TarjetaAfiliacionQrPlanCatalog::qrFilenameForPlanId(3))->toBe('qr-plan-especial.png')
        ->and(TarjetaAfiliacionQrPlanCatalog::qrFilenameForPlanId(11))->toBe('qr-plan-11.png')
        ->and(TarjetaAfiliacionQrPlanCatalog::qrFilenameForPlanId(17))->toBe('qr-plan-17.png');
});

it('resuelve qr por plan_id antes que por descripcion', function (): void {
    expect(TarjetaAfiliacionQrPlanCatalog::resolveQrFilename(11, 'PLAN ESTUDIANTIL'))
        ->toBe('qr-plan-11.png');
});

it('resuelve qr legacy por descripcion cuando no hay plan_id', function (): void {
    expect(TarjetaAfiliacionQrPlanCatalog::resolveQrFilename(null, 'PLAN IDEAL'))
        ->toBe('qr-plan-ideal.png')
        ->and(TarjetaAfiliacionQrPlanCatalog::resolveQrFilename(null, 'PLAN ESPECIAL'))
        ->toBe('qr-plan-especial.png');
});

it('genera etiqueta legacy para planes iniciales ideal y especial', function (): void {
    expect(TarjetaAfiliacionQrPlanCatalog::displayTagForPlan(null, 'PLAN INICIAL'))->toBe('INICIAL')
        ->and(TarjetaAfiliacionQrPlanCatalog::displayTagForPlan(null, 'PLAN IDEAL'))->toBe('IDEAL')
        ->and(TarjetaAfiliacionQrPlanCatalog::displayTagForPlan(null, 'PLAN ESPECIAL'))->toBe('ESPECIAL');
});

it('carga opciones corporativas desde todos los registros de plans', function (): void {
    $catalogPath = dirname(__DIR__, 2).'/app/Support/TarjetaAfiliacionQrPlanCatalog.php';
    $contents = file_get_contents($catalogPath);

    expect($contents)
        ->toContain('public static function corporateSelectOptions(): array')
        ->toContain("->orderBy('id')")
        ->toContain("->pluck('description', 'id')")
        ->toContain('Affiliation::query()')
        ->not->toContain('AfilliationCorporatePlan::query()');
});

it('expone metodos de opciones para selects del generador qr', function (): void {
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Business/Pages/GeneradorQrPersonalizado.php';

    expect(file_get_contents($pagePath))
        ->toContain('getIndividualQrPlanOptions')
        ->toContain('getCorporateQrPlanOptions')
        ->toContain('TarjetaAfiliacionQrPlanCatalog::individualSelectOptions')
        ->toContain('TarjetaAfiliacionQrPlanCatalog::corporateSelectOptions');
});
