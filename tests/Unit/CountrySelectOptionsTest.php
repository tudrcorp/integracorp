<?php

declare(strict_types=1);

use App\Support\CountrySelectOptions;

it('traduce nombres de paises de la base de datos al español', function (): void {
    expect(CountrySelectOptions::spanishNameForDatabaseCountry('UNITED STATES OF AMERICA'))
        ->toBe('Estados Unidos')
        ->and(CountrySelectOptions::spanishNameForDatabaseCountry('ARGENTINA'))
        ->toBe('Argentina')
        ->and(CountrySelectOptions::spanishNameForDatabaseCountry('SPAIN'))
        ->toBe('España')
        ->and(CountrySelectOptions::spanishNameForDatabaseCountry('MEXICO'))
        ->toBe('México')
        ->and(CountrySelectOptions::spanishNameForDatabaseCountry('GERMANY'))
        ->toBe('Alemania')
        ->and(CountrySelectOptions::spanishNameForDatabaseCountry('CABO VERDE'))
        ->toBe('Cabo Verde');
});

it('define helper reutilizable para selects de paises en español', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/CountrySelectOptions.php'))
        ->toContain('final class CountrySelectOptions')
        ->toContain('exceptVenezuelaInSpanish')
        ->toContain('spanishNameForDatabaseCountry')
        ->toContain('UNITED STATES OF AMERICA')
        ->toContain('ICUDATA-region');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Agents/Resources/Agents/Schemas/AgentForm.php'))
        ->toContain('use App\Support\CountrySelectOptions;')
        ->toContain('CountrySelectOptions::exceptVenezuelaInSpanish()');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Schemas/AgentForm.php'))
        ->toContain('use App\Support\CountrySelectOptions;')
        ->toContain('CountrySelectOptions::exceptVenezuelaInSpanish()');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Schemas/AgencyForm.php'))
        ->toContain('use App\Support\CountrySelectOptions;')
        ->toContain('CountrySelectOptions::exceptVenezuelaInSpanish()');
});
