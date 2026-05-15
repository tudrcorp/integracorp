<?php

declare(strict_types=1);

use App\Support\AffiliationCorporateRifLabel;

uses(Tests\TestCase::class);

it('añade prefijo J- al RIF corporativo cuando solo vienen dígitos', function (): void {
    expect(AffiliationCorporateRifLabel::withJPrefix('12345678'))->toBe('J-12345678');
});

it('normaliza RIF que ya trae J o J-', function (): void {
    expect(AffiliationCorporateRifLabel::withJPrefix('J-99'))->toBe('J-99')
        ->and(AffiliationCorporateRifLabel::withJPrefix('j-88'))->toBe('J-88')
        ->and(AffiliationCorporateRifLabel::withJPrefix('J77'))->toBe('J-77');
});

it('cadena vacía o null devuelve vacío', function (): void {
    expect(AffiliationCorporateRifLabel::withJPrefix(null))->toBe('')
        ->and(AffiliationCorporateRifLabel::withJPrefix('   '))->toBe('');
});
