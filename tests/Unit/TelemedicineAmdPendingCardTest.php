<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Widgets\CaseStats;
use App\Models\TelemedicineCase;
use App\Support\Telemedicine\TelemedicineCaseDerivedService;

/**
 * Tarjeta «AMD POR REALIZAR» del escritorio médico: casos cuya última
 * consulta dejó como derivado una AMD que todavía no se registró. Solo arma
 * SQL y lee código fuente: no escribe en la base.
 */
uses(Tests\TestCase::class);

$basePath = dirname(__DIR__, 2);

it('reconoce el servicio AMD por su nombre, con o sin tildes', function (string $name): void {
    expect(TelemedicineCaseDerivedService::driftNameIsAmd($name))->toBeTrue();
})->with([
    'AMD (ASISTENCIA MEDICA DOMICILIARIA)',
    'amd',
    'Asistencia Médica Domiciliaria',
]);

it('no confunde otros derivados con una AMD', function (?string $name): void {
    expect(TelemedicineCaseDerivedService::driftNameIsAmd($name))->toBeFalse();
})->with([
    'SEGUIMIENTO MÉDICO/LECTURA DE RESULTADOS',
    'TRASLADO EN AMBULANCIA',
    'URGENCIA MENOR EN DOMICILIO',
    '',
    null,
]);

it('filtra por el derivado de la última consulta del caso', function (): void {
    $sql = TelemedicineCaseDerivedService::whereAmdPending(TelemedicineCase::query())->toRawSql();

    expect($sql)
        ->toContain("like 'AMD%'")
        ->toContain("like '%ASISTENCIA MEDICA DOMICILIARIA%'")
        ->toContain('select max(dmx.id) from telemedicine_consultation_patients dmx');
});

it('la tarjeta cuenta con la misma regla y el mismo alcance que la tabla', function () use ($basePath): void {
    $stats = file_get_contents($basePath.'/app/Filament/Telemedicina/Widgets/CaseStats.php');
    $table = file_get_contents($basePath.'/app/Filament/Telemedicina/Widgets/TelemedicineCaseTableDash.php');

    expect(CaseStats::FILTER_AMD_PENDING)->toBe('amd_pending')
        ->and($stats)->toContain("\$this->filterStat('AMD POR REALIZAR', \$this->countAmdPending()")
        ->and($stats)->toContain('TelemedicineCaseDerivedService::whereAmdPending($this->scopedQuery())')
        ->and($stats)->toContain('fi-telemedicine-case-stat-ios--amd')
        ->and($table)->toContain('CaseStats::FILTER_AMD_PENDING => TelemedicineCaseDerivedService::whereAmdPending($query)')
        ->and($table)->toContain("CaseStats::FILTER_AMD_PENDING => 'AMD por realizar'");
});
