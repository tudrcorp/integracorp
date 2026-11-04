<?php

declare(strict_types=1);

use App\Models\AffiliationCertificateIssue;
use App\Models\User;
use App\Services\AffiliationCertificateGeneratorService;
use App\Support\Filament\SpaDownloadExceptions;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Filament\Support\generate_href_html;

uses(Tests\TestCase::class);

/**
 * Escribe en base de datos: todo ocurre dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Filament::setCurrentPanel('business');
    Filament::getPanel('business')->boot();
    test()->actingAs(User::factory()->make([
        'id' => 999_999_101,
        'name' => 'QA CERTIFICADO',
        'email' => 'qa.certificado@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'NEGOCIOS'],
    ]));
});

afterEach(fn () => DB::rollBack());

it('los enlaces de descarga del certificado no usan la navegación SPA del panel', function (): void {
    $download = route('business.affiliation-certificate.pdf', ['issue' => 23, 'descargar' => 1]);

    expect((string) generate_href_html($download))->not->toContain('wire:navigate')
        ->and((string) generate_href_html(route('business.affiliation-certificate.pdf', ['issue' => 23])))->not->toContain('wire:navigate')
        ->and((string) generate_href_html(url('/certificado/envio/5/descargar')))->not->toContain('wire:navigate')
        ->and((string) generate_href_html(url('/business/affiliations')))->toContain('wire:navigate')
        ->and(Str::is(SpaDownloadExceptions::patterns(), $download))->toBeTrue();
});

it('descarga el certificado como archivo PDF adjunto, con sus cabeceras', function (): void {
    $issue = AffiliationCertificateIssue::query()
        ->whereNull('pdf_status')
        ->where('affiliation_type', AffiliationCertificateIssue::TYPE_CORPORATE)
        ->orderByDesc('id')
        ->first();

    if (! $issue instanceof AffiliationCertificateIssue) {
        test()->markTestSkipped('No hay certificados emitidos en la base para probar la descarga.');
    }

    $hadFile = filled($issue->pdf_path) && Storage::disk('local')->exists((string) $issue->pdf_path);

    try {
        $response = test()->get(route('business.affiliation-certificate.pdf', ['issue' => $issue->getKey(), 'descargar' => 1]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        expect((string) $response->headers->get('Content-Disposition'))->toStartWith('attachment;')
            ->and((string) $response->headers->get('Content-Disposition'))->toContain(AffiliationCertificateGeneratorService::fileName($issue))
            ->and(file_get_contents($response->baseResponse->getFile()->getPathname(), length: 5))->toBe('%PDF-');

        expect((string) test()->get(route('business.affiliation-certificate.pdf', ['issue' => $issue->getKey()]))->headers->get('Content-Disposition'))
            ->toStartWith('inline;');
    } finally {
        /** El PDF dibujado por el test no se queda en disco si antes no existía. */
        if (! $hadFile) {
            Storage::disk('local')->delete(AffiliationCertificateGeneratorService::STORAGE_DIRECTORY.'/'.$issue->verification_key.'.pdf');
        }
    }
});

it('pedir la descarga de uno que se está generando vuelve a la pantalla con aviso; la vista previa recibe 409', function (): void {
    $issue = AffiliationCertificateIssue::query()->create([
        'affiliation_type' => AffiliationCertificateIssue::TYPE_CORPORATE,
        'affiliation_id' => 999_999_999,
        'affiliation_code' => 'QA-COR-0001',
        'verification_key' => 'CER-QA-'.Str::upper(Str::random(10)),
        'pdf_status' => AffiliationCertificateIssue::PDF_PROCESSING,
    ]);

    test()->from(url('/business/affiliation-corporates'))
        ->get(route('business.affiliation-certificate.pdf', ['issue' => $issue->getKey(), 'descargar' => 1]))
        ->assertRedirect(url('/business/affiliation-corporates'));

    expect(session('filament.notifications'))->not->toBeEmpty()
        ->and(collect(session('filament.notifications'))->pluck('title')->all())->toContain('Certificado en preparación');

    test()->get(route('business.affiliation-certificate.pdf', ['issue' => $issue->getKey()]))
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
});
