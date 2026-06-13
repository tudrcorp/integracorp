<?php

declare(strict_types=1);

use App\Support\Exports\IndividualAffiliationsExportService;

uses(Tests\TestCase::class);

it('define encabezados combinados de afiliacion y afiliado', function (): void {
    $headers = IndividualAffiliationsExportService::headers();

    expect($headers)
        ->toContain('ID Afiliación')
        ->toContain('Código Afiliación')
        ->toContain('Nombre Afiliado')
        ->toContain('Relación Afiliado')
        ->and(count($headers))->toBe(47);
});

it('expone el job y comando de exportacion de afiliaciones individuales', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Jobs/ExportIndividualAffiliations.php'))
        ->toContain('ReportsScheduledExecution')
        ->toContain('IndividualAffiliationsExportService')
        ->toContain('setDocumentAttachment');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Console/Commands/ExportIndividualAffiliationsCommand.php'))
        ->toContain('export:individual-affiliations')
        ->toContain('--sync');

    expect(file_get_contents(dirname(__DIR__, 2).'/routes/console.php'))
        ->toContain('ExportIndividualAffiliations');
});

it('genera excel cuando existen tablas de afiliaciones', function (): void {
    if (! \Illuminate\Support\Facades\Schema::hasTable('affiliations')) {
        $this->markTestSkipped('La tabla affiliations no está disponible en este entorno de prueba.');
    }

    $result = app(IndividualAffiliationsExportService::class)->create();

    expect($result->filename)->toEndWith('.xlsx')
        ->and($result->bytes)->toBeGreaterThan(0)
        ->and($result->rowCount)->toBeGreaterThanOrEqual(0)
        ->and(\Illuminate\Support\Facades\Storage::disk('public')->exists($result->publicRelativePath))->toBeTrue();

    \Illuminate\Support\Facades\Storage::disk('public')->delete($result->publicRelativePath);
});
