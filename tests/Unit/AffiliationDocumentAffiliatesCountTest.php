<?php

declare(strict_types=1);

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Support\Affiliation\AffiliationDocumentAffiliatesCount;

it('resuelve el total de afiliados individuales desde withCount', function (): void {
    $affiliation = new Affiliation;
    $affiliation->affiliates_count = 4;

    expect(AffiliationDocumentAffiliatesCount::forIndividual($affiliation))->toBe(4);
});

it('resuelve el total de afiliados corporativos desde withCount', function (): void {
    $affiliation = new AffiliationCorporate;
    $affiliation->corporate_affiliates_count = 12;

    expect(AffiliationDocumentAffiliatesCount::forCorporate($affiliation))->toBe(12);
});

it('incluye el total de afiliados en aviso de cobro individual', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/aviso-de-cobro.blade.php');

    expect($source)
        ->toContain('TOTAL DE AFILIADOS ASOCIADOS A LA AFILIACIÓN')
        ->toContain('affiliates_count');
});

it('incluye el total de afiliados en aviso de cobro corporativo', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/aviso-de-cobro-corporativo.blade.php');

    expect($source)
        ->toContain('TOTAL DE AFILIADOS ASOCIADOS A LA AFILIACIÓN')
        ->toContain('affiliates_count');
});

it('incluye el total de afiliados en aviso de pago corporativo', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/aviso-de-pago-corporativo.blade.php');

    expect($source)
        ->toContain('TOTAL DE AFILIADOS ASOCIADOS A LA AFILIACIÓN')
        ->toContain('affiliates_count');
});
