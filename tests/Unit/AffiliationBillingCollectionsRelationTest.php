<?php

declare(strict_types=1);

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use Illuminate\Database\Eloquent\Relations\HasMany;

uses(Tests\TestCase::class);

it('expone billingCollections como relación hasMany hacia Collection', function (): void {
    $reflection = new ReflectionMethod(Affiliation::class, 'billingCollections');

    expect($reflection->getReturnType()?->getName())->toBe(HasMany::class);
});

it('affiliación corporativa expone billingCollections como relación hasMany hacia Collection', function (): void {
    $reflection = new ReflectionMethod(AffiliationCorporate::class, 'billingCollections');

    expect($reflection->getReturnType()?->getName())->toBe(HasMany::class);
});
