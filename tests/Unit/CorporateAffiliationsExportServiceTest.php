<?php

declare(strict_types=1);

use App\Support\Exports\CorporateAffiliationsExportService;

uses(Tests\TestCase::class);

it('define encabezados combinados de afiliacion corporativa plan y afiliado', function (): void {
    $headers = CorporateAffiliationsExportService::headers();

    expect($headers)
        ->toContain('ID Afiliación Corporativa')
        ->toContain('Plan Contrato')
        ->toContain('ID Afiliado Corporativo')
        ->toContain('Nombres Afiliado')
        ->and(count($headers))->toBe(64);
});

it('expone el job y comando de exportacion de afiliaciones corporativas', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Jobs/ExportCorporateAffiliations.php'))
        ->toContain('ReportsScheduledExecution')
        ->toContain('CorporateAffiliationsExportService')
        ->toContain('setDocumentAttachment');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Console/Commands/ExportCorporateAffiliationsCommand.php'))
        ->toContain('export:corporate-affiliations')
        ->toContain('--sync');

    expect(file_get_contents(dirname(__DIR__, 2).'/routes/console.php'))
        ->toContain('ExportCorporateAffiliations');
});

it('genera excel cuando existen tablas de afiliaciones corporativas', function (): void {
    if (! \Illuminate\Support\Facades\Schema::hasTable('affiliation_corporates')) {
        $this->markTestSkipped('La tabla affiliation_corporates no está disponible en este entorno de prueba.');
    }

    $result = app(CorporateAffiliationsExportService::class)->create();

    expect($result->filename)->toEndWith('.xlsx')
        ->and($result->bytes)->toBeGreaterThan(0)
        ->and($result->rowCount)->toBeGreaterThanOrEqual(0)
        ->and(\Illuminate\Support\Facades\Storage::disk('public')->exists($result->publicRelativePath))->toBeTrue();

    \Illuminate\Support\Facades\Storage::disk('public')->delete($result->publicRelativePath);
});
