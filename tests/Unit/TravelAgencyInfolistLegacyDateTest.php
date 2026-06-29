<?php

declare(strict_types=1);

use App\Filament\Business\Resources\TravelAgencies\Schemas\TravelAgencyInfolist;

function invokeFormatLegacyDate(mixed $state): ?string
{
    $method = new ReflectionMethod(TravelAgencyInfolist::class, 'formatLegacyDate');
    $method->setAccessible(true);

    return $method->invoke(null, $state);
}

it('formatea fechas legadas en formato d/m/Y sin lanzar excepción', function (): void {
    expect(invokeFormatLegacyDate('26/10/2023'))->toBe('26/10/2023');
    expect(invokeFormatLegacyDate('01/01/2020'))->toBe('01/01/2020');
});

it('devuelve null cuando la fecha está vacía', function (): void {
    expect(invokeFormatLegacyDate(null))->toBeNull();
    expect(invokeFormatLegacyDate(''))->toBeNull();
});

it('normaliza objetos Carbon a d/m/Y', function (): void {
    expect(invokeFormatLegacyDate(Carbon\Carbon::create(2023, 10, 26)))->toBe('26/10/2023');
});

it('parsea fechas ISO a d/m/Y', function (): void {
    expect(invokeFormatLegacyDate('2023-10-26'))->toBe('26/10/2023');
});

it('ya no usa el modificador ->date sobre fechas legadas en el infolist', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/TravelAgencies/Schemas/TravelAgencyInfolist.php');

    expect($source)
        ->toContain('private static function formatLegacyDate')
        ->not->toContain("->date('d/m/Y')");
});
