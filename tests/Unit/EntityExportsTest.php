<?php

declare(strict_types=1);

use App\Support\Exports\AgenciesExportService;
use App\Support\Exports\AgentsExportService;
use App\Support\Exports\CollaboratorsExportService;
use App\Support\Exports\Concerns\SpreadsheetExportHelpers;
use App\Support\Exports\JuridicalProvidersExportService;
use App\Support\Exports\NaturalProvidersExportService;
use App\Support\Exports\TelemedicineDoctorsExportService;

uses(Tests\TestCase::class);

it('define encabezados para exportaciones de entidades maestras', function (): void {
    expect(AgentsExportService::headers())
        ->toContain('Notas Bitácora')
        ->toContain('Obs. Estructura Comercial');

    expect(AgenciesExportService::headers())
        ->toContain('Notas Bitácora');

    expect(NaturalProvidersExportService::headers())
        ->toContain('Notas');

    expect(JuridicalProvidersExportService::headers())
        ->toContain('Notas Bitácora')
        ->toContain('Observaciones (campo)');

    expect(CollaboratorsExportService::headers())
        ->toContain('Nombre Completo');

    expect(TelemedicineDoctorsExportService::headers())
        ->toContain('Especialidad');
});

it('concatena observaciones con formato fecha autor y separador', function (): void {
    $helper = new class
    {
        use SpreadsheetExportHelpers;

        public function format(iterable $entries): ?string
        {
            return self::concatObservationEntries(
                $entries,
                fn ($entry) => $entry->text,
                fn ($entry) => $entry->author,
                fn ($entry) => $entry->date,
            );
        }
    };

    $entries = [
        (object) ['text' => 'Primera nota', 'author' => 'Ana', 'date' => '2026-06-01 10:00:00'],
        (object) ['text' => 'Segunda nota', 'author' => 'Luis', 'date' => '2026-06-02 11:30:00'],
    ];

    expect($helper->format($entries))
        ->toContain('[2026-06-01 10:00 | Ana] Primera nota')
        ->toContain(' || ')
        ->toContain('[2026-06-02 11:30 | Luis] Segunda nota');
});

it('expone job comando y schedule para exportaciones de entidades', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Jobs/ExportScheduledEntity.php'))
        ->toContain('ExportScheduledEntity')
        ->toContain('setDocumentAttachment');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Console/Commands/ExportEntitySpreadsheetCommand.php'))
        ->toContain('export:entity')
        ->toContain('--sync');

    expect(file_get_contents(dirname(__DIR__, 2).'/routes/console.php'))
        ->toContain("ExportScheduledEntity('agents')")
        ->toContain("ExportScheduledEntity('doctors')");
});

it('genera excel de agentes cuando la tabla existe', function (): void {
    if (! \Illuminate\Support\Facades\Schema::hasTable('agents')) {
        $this->markTestSkipped('La tabla agents no está disponible en este entorno de prueba.');
    }

    $result = app(AgentsExportService::class)->create();

    expect($result->filename)->toEndWith('.xlsx')
        ->and($result->bytes)->toBeGreaterThan(0)
        ->and(\Illuminate\Support\Facades\Storage::disk('public')->exists($result->publicRelativePath))->toBeTrue();

    \Illuminate\Support\Facades\Storage::disk('public')->delete($result->publicRelativePath);
});

it('registra servicios de exportacion en config scheduled-exports', function (): void {
    $exports = config('scheduled-exports.exports');

    expect($exports)->toHaveKeys([
        'agents',
        'agencies',
        'natural_providers',
        'juridical_providers',
        'collaborators',
        'doctors',
    ]);
});
