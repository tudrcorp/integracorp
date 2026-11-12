<?php

declare(strict_types=1);

use App\Enums\OperationReportFormat;
use App\Enums\OperationReportType;
use App\Support\Operations\Reports\OperationReportGenerator;

it('registra el tipo en el generador con excel, csv y pdf', function (): void {
    $type = OperationReportType::CaseMedications;

    expect($type->group())->toBe(OperationReportType::GROUP_MEDICATIONS)
        ->and($type->isCaseMedications())->toBeTrue()
        ->and($type->isDetail())->toBeTrue()
        ->and($type->hasManyRows())->toBeFalse()
        ->and($type->rowNoun())->toBe(['medicamento', 'medicamentos'])
        ->and($type->formats())->toBe([
            OperationReportFormat::Excel,
            OperationReportFormat::Csv,
            OperationReportFormat::Pdf,
        ])
        ->and($type->label())->toContain('Medicamentos por caso')
        ->and($type->description())->toContain('sin indicaciones')
        ->and($type->description())->toContain('conciliación');
});

it('encola solo los pdf y excel/csv realmente grandes', function (): void {
    $type = OperationReportType::CaseMedications;

    expect(OperationReportGenerator::shouldQueue($type, OperationReportFormat::Pdf, 1))->toBeFalse()
        ->and(OperationReportGenerator::shouldQueue($type, OperationReportFormat::Pdf, 1000))->toBeFalse()
        ->and(OperationReportGenerator::shouldQueue($type, OperationReportFormat::Pdf, 1001))->toBeTrue()
        ->and(OperationReportGenerator::shouldQueue($type, OperationReportFormat::Excel, 10))->toBeFalse()
        ->and(OperationReportGenerator::shouldQueue($type, OperationReportFormat::Excel, 3001))->toBeTrue()
        ->and(OperationReportGenerator::shouldQueue($type, OperationReportFormat::Csv, 3000))->toBeFalse();
});

it('el orquestador y la página conocen el reporte de medicamentos por caso', function (): void {
    $root = dirname(__DIR__, 2).'/app';
    $builder = file_get_contents($root.'/Support/Operations/Reports/OperationReportBuilder.php');
    $page = file_get_contents($root.'/Filament/Operations/Pages/GeneradorDeReportes.php');
    $generator = file_get_contents($root.'/Support/Operations/Reports/OperationReportGenerator.php');
    $writer = file_get_contents($root.'/Support/Operations/Reports/OperationReportWriter.php');
    $data = file_get_contents($root.'/Support/Operations/Reports/OperationReportData.php');

    expect($builder)->toContain('CaseMedicationReportBuilder::count')
        ->and($builder)->toContain('CaseMedicationReportBuilder::build')
        ->and($page)->toContain('GROUP_MEDICATIONS')
        ->and($page)->toContain('isCaseMedications')
        ->and($page)->toContain('CaseMedicationReportBuilder::coverageOptions')
        ->and($generator)->toContain('isCaseMedications')
        ->and($writer)->toContain('$data->sections')
        ->and($data)->toContain('public array $sections = []')
        ->and(file_exists($root.'/Support/Operations/Reports/CaseMedicationReportBuilder.php'))->toBeTrue();
});
