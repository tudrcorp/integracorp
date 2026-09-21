<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

use App\Models\ProspectAgent;
use App\Models\ProspectAgentContact;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

it('expone dirección, web, redes y contactos en el prospecto', function (): void {
    $fillable = (new ProspectAgent)->getFillable();

    expect($fillable)
        ->toContain('address')
        ->toContain('website')
        ->toContain('social_networks');

    $relation = (new ProspectAgent)->prospectAgentContacts();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(ProspectAgentContact::class);
});

it('define el contacto de prospecto con sus campos y relación', function (): void {
    $contact = new ProspectAgentContact;
    $fillable = $contact->getFillable();

    expect($contact->getTable())->toBe('prospect_agent_contacts')
        ->and($fillable)->toContain('prospect_agent_id')
        ->and($fillable)->toContain('name')
        ->and($fillable)->toContain('position')
        ->and($fillable)->toContain('phone')
        ->and($fillable)->toContain('email')
        ->and($fillable)->toContain('sort_order');

    $relation = $contact->prospectAgent();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(ProspectAgent::class);
});
