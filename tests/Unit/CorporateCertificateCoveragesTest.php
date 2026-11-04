<?php

declare(strict_types=1);

use App\Models\AffiliationCertificateIssue;
use App\Models\AffiliationCorporate;
use App\Support\Affiliations\Certificates\CorporateCertificateDocument;
use Illuminate\Support\Facades\DB;

/**
 * Solo lee datos reales: arma el certificado en memoria, sin emitirlo ni guardarlo.
 */
uses(Tests\TestCase::class);

function corporateWithCoverages(bool $several): ?AffiliationCorporate
{
    $id = DB::table('affiliate_corporates as a')
        ->join('affiliation_corporates as f', 'f.id', '=', 'a.affiliation_corporate_id')
        ->whereNull('f.deleted_at')
        ->whereNull('a.deleted_at')
        ->whereNotNull('a.coverage_id')
        ->groupBy('a.affiliation_corporate_id')
        ->havingRaw($several ? 'count(distinct a.coverage_id) > 1' : 'count(distinct a.coverage_id) = 1')
        ->havingRaw('count(distinct a.plan_id) = 1')
        ->orderByRaw('count(*)')
        ->value('a.affiliation_corporate_id');

    return $id !== null ? AffiliationCorporate::query()->find($id) : null;
}

/**
 * @return array<string, mixed>
 */
function corporateCertificateData(AffiliationCorporate $affiliation): array
{
    $issue = new AffiliationCertificateIssue([
        'affiliation_type' => AffiliationCertificateIssue::TYPE_CORPORATE,
        'affiliation_id' => $affiliation->getKey(),
        'affiliation_code' => $affiliation->code,
        'verification_key' => 'CER-PRUEBA-TEST',
    ]);
    $issue->id = 0;
    $issue->created_at = now();

    return CorporateCertificateDocument::build($affiliation, $issue, []);
}

it('con varias coberturas no muestra el monto destacado y los beneficios con monto dicen «Según cobertura»', function (): void {
    $affiliation = corporateWithCoverages(several: true);

    if ($affiliation === null) {
        test()->markTestSkipped('No hay afiliaciones corporativas con varias coberturas en la base.');
    }

    $data = corporateCertificateData($affiliation);
    $amounts = collect($data['pages'])->where('kind', 'rel')->flatMap(fn (array $page): array => $page['people'])->pluck('coberturaMonto')->unique();
    $benefitRows = collect($data['pages'])->firstWhere('isCert', true)['rows'] ?? [];

    expect($data['heroHidden'])->toBeTrue()
        ->and($data['relationCoverageColumn'])->toBeTrue()
        ->and($amounts->count())->toBeGreaterThan(1)
        ->and($amounts->every(fn (string $amount): bool => str_starts_with($amount, 'US$ ') || $amount === '—'))->toBeTrue()
        ->and(collect($benefitRows)->where('limited', true)->pluck('cob')->unique()->values()->all())->toBe([CorporateCertificateDocument::BY_COVERAGE]);
});

it('con una sola cobertura conserva el monto destacado y también lleva la columna de cobertura', function (): void {
    $affiliation = corporateWithCoverages(several: false);

    if ($affiliation === null) {
        test()->markTestSkipped('No hay afiliaciones corporativas con una sola cobertura en la base.');
    }

    $data = corporateCertificateData($affiliation);
    $person = collect($data['pages'])->firstWhere('kind', 'rel')['people'][0] ?? null;

    expect($data['heroHidden'])->toBeFalse()
        ->and($data['relationCoverageColumn'])->toBeTrue()
        ->and($person['coberturaMonto'] ?? null)->toStartWith('US$ ');
});

it('el monto de cobertura de la relación se formatea y sin cobertura queda en guion', function (): void {
    expect(CorporateCertificateDocument::coverageLabel(5000))->toBe('US$ 5.000')
        ->and(CorporateCertificateDocument::coverageLabel('10000.00'))->toBe('US$ 10.000')
        ->and(CorporateCertificateDocument::coverageLabel(null))->toBe('—')
        ->and(CorporateCertificateDocument::coverageLabel(0))->toBe('—');
});

it('la plantilla oculta el bloque destacado y agrega la columna sin cambiar el ancho de la tabla', function (): void {
    $template = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/affiliation-certificate/document.blade.php');

    expect($template)->toContain("@unless (\$data['heroHidden'] ?? false)")
        ->and($template)->toContain('Cobertura</td>')
        ->and($template)->toContain("\$b['coberturaMonto'] ?? '—'")
        ->and(34 + 246 + 116 + 100 + 120 + 90)->toBe(34 + 300 + 132 + 120 + 120);
});
