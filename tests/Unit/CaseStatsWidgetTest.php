<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Widgets\CaseStats;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class);

it('no falla sin médico vinculado y usa cinco columnas en escritorio (una por tarjeta)', function (): void {
    // make(): el usuario no necesita existir en la base.
    $this->actingAs(User::factory()->make(['id' => 999_999_401, 'doctor_id' => null]));

    $widget = new CaseStats;
    expect($widget->getColumns())->toBe(['default' => 1, 'sm' => 2, 'lg' => 5])
        ->and($widget->userDoctorBelongsToSupplier())->toBeFalse();
});

it('devuelve cero en contadores sin doctor vinculado', function (): void {
    $this->actingAs(User::factory()->make(['id' => 999_999_402, 'doctor_id' => null]));

    $widget = new CaseStats;
    expect($widget->countAssigned())->toBe(0)
        ->and($widget->countFollowUp())->toBe(0)
        ->and($widget->countOverdue())->toBe(0)
        ->and($widget->countDischarged())->toBe(0)
        ->and($widget->countAmbulanceTransfers())->toBe(0);
});
