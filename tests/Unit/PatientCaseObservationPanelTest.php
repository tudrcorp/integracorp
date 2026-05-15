<?php

declare(strict_types=1);

use App\Livewire\Operations\PatientCaseObservationPanel;

it('expone mount con telemedicineCaseId y método save', function (): void {
    $reflection = new ReflectionClass(PatientCaseObservationPanel::class);

    expect($reflection->hasMethod('mount'))->toBeTrue()
        ->and($reflection->hasMethod('save'))->toBeTrue()
        ->and($reflection->hasMethod('rules'))->toBeTrue();
});
