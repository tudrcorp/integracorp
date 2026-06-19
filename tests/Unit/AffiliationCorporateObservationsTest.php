<?php

declare(strict_types=1);

use App\Models\AffiliationCorporate;
use App\Models\AffiliationCorporateObservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

uses(Tests\TestCase::class);

it('define la relacion affiliationCorporateObservations en el modelo', function (): void {
    $relation = (new AffiliationCorporate)->affiliationCorporateObservations();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(AffiliationCorporateObservation::class);
});

it('usa un nombre de relacion que no colisiona con la columna observations', function (): void {
    $affiliation = new AffiliationCorporate;

    expect(method_exists($affiliation, 'affiliationCorporateObservations'))->toBeTrue();
    expect(in_array('observations', $affiliation->getFillable(), true))->toBeTrue();
});

it('el modelo AffiliationCorporateObservation expone autor y afiliacion corporativa', function (): void {
    $observation = new AffiliationCorporateObservation;

    expect($observation->affiliationCorporate())->toBeInstanceOf(BelongsTo::class)
        ->and($observation->createdBy())->toBeInstanceOf(BelongsTo::class)
        ->and($observation->createdBy()->getRelated())->toBeInstanceOf(User::class)
        ->and($observation->getFillable())->toContain('affiliation_corporate_id', 'description', 'created_by');
});

it('expone el tab de observaciones en el infolist corporativo', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php');

    expect($source)
        ->toContain("Tab::make('Observaciones')")
        ->toContain("RepeatableEntry::make('affiliationCorporateObservations')")
        ->toContain("TextEntry::make('description')")
        ->toContain("->label('Registrado por')")
        ->toContain('AffiliationCorporateObservation $record');
});

it('agrega la accion iOS para registrar observaciones corporativas en la cabecera', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Pages/ViewAffiliationCorporate.php');

    expect($source)
        ->toContain("Action::make('addObservation')")
        ->toContain("FilamentIosButton::extraClassForFilamentColor('info')")
        ->toContain("Textarea::make('description')")
        ->toContain('affiliationCorporateObservations()->create(')
        ->toContain('Auth::id()')
        ->toContain('affiliationCorporateObservations.createdBy:id,name,email');
});
