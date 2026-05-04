<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Widgets\CaseStats;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class);

it('no falla sin médico vinculado y usa tres columnas', function (): void {
    $user = User::factory()->create(['doctor_id' => null]);
    $this->actingAs($user);

    $widget = new CaseStats;
    expect($widget->getColumns())->toBe(3);
});

it('devuelve cero en contadores sin doctor vinculado', function (): void {
    $user = User::factory()->create(['doctor_id' => null]);
    $this->actingAs($user);

    $widget = new CaseStats;
    expect($widget->getTotalCases())->toBe(0)
        ->and($widget->getTotalCasesAssigned())->toBe(0)
        ->and($widget->getTotalCasesFollowUp())->toBe(0)
        ->and($widget->getTotalCasesAttended())->toBe(0)
        ->and($widget->getTotalCasesTransportAmbulance())->toBe(0);
});
