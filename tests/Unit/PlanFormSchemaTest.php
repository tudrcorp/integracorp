<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Plans\Schemas\PlanForm;
use App\Models\User;
use Filament\Schemas\Schema;
use Tests\TestCase;

uses(TestCase::class);

it('configura el schema del plan incluyendo coberturas generales', function (): void {
    $this->actingAs(User::factory()->create());

    $schema = Schema::make();
    $configured = PlanForm::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});
