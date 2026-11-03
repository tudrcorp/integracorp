<?php

declare(strict_types=1);

use App\Models\Company;
use App\Support\Companies\CompanyAssociateRegistrar;
use App\Support\Companies\CompanyResponsibleDays;

uses(Tests\TestCase::class);

it('cuenta un día por asociado vigente usando el conteo precargado', function (): void {
    $company = new Company;
    $company->setAttribute(CompanyResponsibleDays::CONSUMING_ASSOCIATES_COUNT_ATTRIBUTE, 378);

    expect(CompanyResponsibleDays::consumedDaysFor($company))
        ->toBe(378 * CompanyAssociateRegistrar::DAYS_PER_REGISTRATION);
});

it('devuelve cero cuando el conteo precargado es cero', function (): void {
    $company = new Company;
    $company->setAttribute(CompanyResponsibleDays::CONSUMING_ASSOCIATES_COUNT_ATTRIBUTE, 0);

    expect(CompanyResponsibleDays::consumedDaysFor($company))->toBe(0);
});

it('consulta la base si el conteo no viene precargado, sin contar anulados', function (): void {
    $company = new Company;
    $company->setAttribute('id', 0);

    expect(CompanyResponsibleDays::consumedDaysFor($company))->toBe(0);
});

it('precarga en el recurso el conteo de asociados que consumen días', function (): void {
    $source = file_get_contents(app_path('Filament/Business/Resources/Companies/CompanyResource.php'));

    expect($source)
        ->toContain("'associates as '.CompanyResponsibleDays::CONSUMING_ASSOCIATES_COUNT_ATTRIBUTE")
        ->toContain('->consumesRegistrationDay()');
});

it('muestra los días consumidos en la tabla y en la ficha de la empresa', function (string $path, string $component): void {
    $source = file_get_contents(app_path($path));

    expect($source)
        ->toContain($component)
        ->toContain("->label('Días consumidos')")
        ->toContain('CompanyResponsibleDays::consumedDaysFor($record)');
})->with([
    'tabla' => ['Filament/Business/Resources/Companies/Tables/CompaniesTable.php', "TextColumn::make('consumed_days')"],
    'ficha' => ['Filament/Business/Resources/Companies/Schemas/CompanyInfolist.php', "TextEntry::make('consumed_days')"],
]);
