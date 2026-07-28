<?php

declare(strict_types=1);

use App\Models\IndividualQuote;
use App\Support\IndividualQuotes\IndividualQuoteFollowUp;
use Illuminate\Database\Eloquent\Builder;

uses(Tests\TestCase::class);

it('activa el filtro temporal de colaboradores cuando el flag esta en true', function (): void {
    config(['individual-quotes.follow_up_only_collaborators' => true]);

    expect(IndividualQuoteFollowUp::isRestrictedToCollaborators())->toBeTrue();
});

it('permite desactivar el filtro temporal de colaboradores', function (): void {
    config(['individual-quotes.follow_up_only_collaborators' => false]);

    expect(IndividualQuoteFollowUp::isRestrictedToCollaborators())->toBeFalse();
});

it('define el flag temporal de colaboradores en la config del modulo', function (): void {
    $configSource = file_get_contents(dirname(__DIR__, 2).'/config/individual-quotes.php');

    expect($configSource)
        ->toContain("'follow_up_only_collaborators'")
        ->toContain('INDIVIDUAL_QUOTE_FOLLOW_UP_ONLY_COLLABORATORS')
        ->toContain(', true)');
});

it('restringe la consulta a emails de rrhh_colaboradors', function (): void {
    $sql = IndividualQuoteFollowUp::constrainToCollaboratorEmails(IndividualQuote::query())->toSql();

    expect($sql)
        ->toContain('exists')
        ->toContain('rrhh_colaboradors')
        ->toContain('emailCorporativo')
        ->toContain('emailAlternativo')
        ->toContain('emailPersonal');
});

it('aplica el filtro de colaboradores en el agrupado cuando el flag esta activo', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/IndividualQuotes/IndividualQuoteFollowUp.php');

    expect($source)
        ->toContain('self::isRestrictedToCollaborators()')
        ->toContain('self::constrainToCollaboratorEmails($query)')
        ->toContain('LOWER(TRIM(rrhh_colaboradors.emailCorporativo)) = LOWER(TRIM(individual_quotes.email))')
        ->toContain('LOWER(TRIM(rrhh_colaboradors.emailAlternativo)) = LOWER(TRIM(individual_quotes.email))')
        ->toContain('LOWER(TRIM(rrhh_colaboradors.emailPersonal)) = LOWER(TRIM(individual_quotes.email))');
});

it('expone constrainToCollaboratorEmails como builder tipado', function (): void {
    $query = IndividualQuoteFollowUp::constrainToCollaboratorEmails(IndividualQuote::query());

    expect($query)->toBeInstanceOf(Builder::class);
});
