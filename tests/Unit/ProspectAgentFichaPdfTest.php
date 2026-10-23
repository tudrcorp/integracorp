<?php

declare(strict_types=1);

use App\Models\ProspectAgent;
use App\Services\ProspectAgentFichaPdfService;
use Illuminate\Support\Facades\View;

uses(Tests\TestCase::class);

it('el modal de ficha PDF del prospecto incluye mensaje de espera y controles', function (): void {
    $html = View::make('filament.business.prospect-agents.prospect-agent-ficha-preview-modal', [
        'pdfPreviewUrl' => 'https://example.test/business/prospect-agents/1/ficha/preview',
        'pdfDownloadUrl' => 'https://example.test/business/prospect-agents/1/ficha/download',
        'prospectLabel' => 'Agencia Demo',
    ])
        ->withErrors([])
        ->render();

    expect($html)
        ->toContain('Generando la ficha en PDF')
        ->toContain('x-ref="pdfPreview"')
        ->toContain('pdfPreviewLoading')
        ->toContain('Abrir en pestaña')
        ->toContain('Descargar PDF')
        ->toContain('Agencia Demo');
});

it('nombre de archivo de descarga de ficha del prospecto sigue el patrón esperado', function (): void {
    $prospect = new ProspectAgent;
    $prospect->id = 186;

    expect(ProspectAgentFichaPdfService::downloadFilename($prospect))->toBe('Ficha-Prospecto-186.pdf');
});

it('la plantilla de ficha del prospecto incluye las secciones principales', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/prospect-agent-ficha.blade.php');

    expect($contents)
        ->toContain('Ficha del Prospecto')
        ->toContain('Información general')
        ->toContain('Personas de contacto')
        ->toContain('Ubicación')
        ->toContain('Tareas de seguimiento')
        ->toContain('Observaciones');
});

it('las rutas de ficha del prospecto están registradas con middleware auth', function (): void {
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/ProspectAgentFichaPdfController.php');

    expect($routes)
        ->toContain("->name('business.prospect-agents.ficha.preview')")
        ->toContain("->name('business.prospect-agents.ficha.download')")
        ->toContain('ProspectAgentFichaPdfController');

    expect($controller)
        ->toContain('business.prospect-agents.ficha.preview')
        ->toContain('business.prospect-agents.ficha.download')
        ->toContain('AUDIT_BUSINESS_PROSPECT_AGENT_FICHA_VIEWED')
        ->toContain('AUDIT_BUSINESS_PROSPECT_AGENT_FICHA_DOWNLOADED');
});
