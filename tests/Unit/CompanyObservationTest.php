<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanyObservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

uses(Tests\TestCase::class);

it('define la relacion companyObservations en el modelo Company', function (): void {
    $relation = (new Company)->companyObservations();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(CompanyObservation::class);
});

it('el modelo CompanyObservation expone autor y empresa', function (): void {
    $observation = new CompanyObservation;

    expect($observation->company())->toBeInstanceOf(BelongsTo::class)
        ->and($observation->createdBy())->toBeInstanceOf(BelongsTo::class)
        ->and($observation->createdBy()->getRelated())->toBeInstanceOf(User::class)
        ->and($observation->getFillable())->toContain('company_id', 'description', 'created_by');
});

it('expone el tab de notas y observaciones en el infolist con la bitacora', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Companies/Schemas/CompanyInfolist.php');

    expect($source)
        ->toContain("Tab::make('Notas y Observaciones')")
        ->toContain("RepeatableEntry::make('companyObservations')")
        ->toContain("TextEntry::make('description')")
        ->toContain("->label('Registrado por')")
        ->toContain('CompanyObservation $record');
});

it('agrega la accion para registrar notas u observaciones en la cabecera', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Companies/Pages/ViewCompany.php');

    expect($source)
        ->toContain("Action::make('addObservation')")
        ->toContain("->label('Agregar Notas/Observaciones')")
        ->toContain("Textarea::make('description')")
        ->toContain('companyObservations()->create(')
        ->toContain('Auth::id()');
});

it('precarga las observaciones en el resource de empresas', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Companies/CompanyResource.php');

    expect($source)->toContain("'companyObservations.createdBy:id,name,email'");
});
