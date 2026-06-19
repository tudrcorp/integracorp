<?php

declare(strict_types=1);

use App\Models\Affiliation;
use App\Models\AffiliationObservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

uses(Tests\TestCase::class);

it('define la relacion affiliationObservations en el modelo Affiliation', function (): void {
    $relation = (new Affiliation)->affiliationObservations();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(AffiliationObservation::class);
});

it('no usa el nombre observations que colisiona con la columna existente', function (): void {
    $affiliation = new Affiliation;

    expect(method_exists($affiliation, 'affiliationObservations'))->toBeTrue();
    expect(in_array('observations', $affiliation->getFillable(), true))->toBeTrue();
});

it('el modelo AffiliationObservation expone autor y afiliacion', function (): void {
    $observation = new AffiliationObservation;

    expect($observation->affiliation())->toBeInstanceOf(BelongsTo::class)
        ->and($observation->createdBy())->toBeInstanceOf(BelongsTo::class)
        ->and($observation->createdBy()->getRelated())->toBeInstanceOf(User::class)
        ->and($observation->getFillable())->toContain('affiliation_id', 'description', 'created_by');
});

it('expone el tab de observaciones en el infolist con la bitacora', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationInfolist.php');

    expect($source)
        ->toContain("Tab::make('Observaciones')")
        ->toContain("RepeatableEntry::make('affiliationObservations')")
        ->toContain("TextEntry::make('description')")
        ->toContain("->label('Registrado por')")
        ->toContain('AffiliationObservation $record');
});

it('agrega la accion iOS para registrar observaciones en la cabecera', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Pages/ViewAffiliation.php');

    expect($source)
        ->toContain("Action::make('addObservation')")
        ->toContain("FilamentIosButton::extraClassForFilamentColor('info')")
        ->toContain("Textarea::make('description')")
        ->toContain('affiliationObservations()->create(')
        ->toContain('Auth::id()');
});

it('ya no registra un relation manager de observaciones', function (): void {
    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/AffiliationResource.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Pages/ViewAffiliation.php');

    expect($resource)->not->toContain('AffiliationObservationsRelationManager');
    expect($view)->not->toContain('AffiliationObservationsRelationManager');

    expect(file_exists(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/RelationManagers/AffiliationObservationsRelationManager.php'))->toBeFalse();
});
