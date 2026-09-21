<?php

declare(strict_types=1);

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\CorporateQuote;
use App\Models\IndividualQuote;
use App\Support\PaidMemberships\AffiliationQuoteNumber;

it('usa el codigo de la cotizacion individual cuando existe', function (): void {
    $affiliation = new Affiliation;
    $affiliation->setRelation('individual_quote', new IndividualQuote(['code' => 'COT-IND-0004005']));

    expect(AffiliationQuoteNumber::forIndividual($affiliation))->toBe('COT-IND-0004005');
});

it('usa el codigo desnormalizado si la cotizacion individual ya no existe', function (): void {
    $affiliation = new Affiliation([
        'code_individual_quote' => 'COT-IND-0001002',
    ]);
    $affiliation->setRelation('individual_quote', null);

    expect(AffiliationQuoteNumber::forIndividual($affiliation))->toBe('COT-IND-0001002');
});

it('devuelve N/A cuando no hay cotizacion individual ni codigo guardado', function (): void {
    $affiliation = new Affiliation;
    $affiliation->setRelation('individual_quote', null);

    expect(AffiliationQuoteNumber::forIndividual($affiliation))->toBe('N/A')
        ->and(AffiliationQuoteNumber::forIndividual(null))->toBe('N/A');
});

it('usa el codigo de la cotizacion corporativa cuando existe', function (): void {
    $affiliation = new AffiliationCorporate;
    $affiliation->setRelation('corporate_quote', new CorporateQuote(['code' => 'COT-COR-00012']));

    expect(AffiliationQuoteNumber::forCorporate($affiliation))->toBe('COT-COR-00012');
});

it('devuelve N/A cuando no hay cotizacion corporativa', function (): void {
    $affiliation = new AffiliationCorporate;
    $affiliation->setRelation('corporate_quote', null);

    expect(AffiliationQuoteNumber::forCorporate($affiliation))->toBe('N/A')
        ->and(AffiliationQuoteNumber::forCorporate(null))->toBe('N/A');
});

it('aprueba pagos individuales sin exigir cotizacion ni cobertura vigentes', function (): void {
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/PaidMembershipController.php');

    expect($controller)
        ->toContain('AffiliationQuoteNumber::forIndividual')
        ->toContain('$record->coverage?->price')
        ->toContain('$record->affiliation->coverage?->price')
        ->toContain('$record->plan?->description')
        ->not->toContain('$record->affiliation->individual_quote->code')
        ->not->toContain('$record->coverage->price ?? null')
        ->not->toContain('$record->affiliation->coverage->price ?? \'\'');
});

it('aprueba pagos corporativos sin exigir cotizacion ni cobertura vigentes', function (): void {
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/PaidMembershipCorporateController.php');

    expect($controller)
        ->toContain('AffiliationQuoteNumber::forCorporate')
        ->toContain('$record->coverage?->price')
        ->not->toContain('$record->affiliation_corporate->corporate_quote->code')
        ->not->toContain('$record->coverage->price ?? null');
});

it('genera el certificado individual con cobertura opcional', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/AffiliationController.php');

    expect($source)
        ->toContain('$record->coverage?->price ?? 0')
        ->not->toContain('$record->coverage->price ?? 0');
});
