<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Widgets\TelemedicineCaseTableDash;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineServiceList;
use App\Models\User;

uses(Tests\TestCase::class);

it('bloquea actualización para ATENMEDI cuando el derivado es traslado en ambulancia (id 3)', function (): void {
    $method = (new \ReflectionClass(TelemedicineCaseTableDash::class))->getMethod('atenmediUserBlockedFromUpdatingConsultation');
    $method->setAccessible(true);

    $atenmedi = new User;
    $atenmedi->departament = ['ATENMEDI'];
    $consultation = new TelemedicineConsultationPatient(['telemedicine_service_list_drift_id' => 3]);

    expect($method->invoke(null, $atenmedi, $consultation))->toBeTrue();
});

it('no bloquea a usuarios que no son ATENMEDI con drift 3', function (): void {
    $method = (new \ReflectionClass(TelemedicineCaseTableDash::class))->getMethod('atenmediUserBlockedFromUpdatingConsultation');
    $method->setAccessible(true);

    $other = new User;
    $other->departament = ['OPERACIONES'];
    $consultation = new TelemedicineConsultationPatient(['telemedicine_service_list_drift_id' => 3]);

    expect($method->invoke(null, $other, $consultation))->toBeFalse();
});

it('no bloquea ATENMEDI si el derivado no es id 3', function (): void {
    $method = (new \ReflectionClass(TelemedicineCaseTableDash::class))->getMethod('atenmediUserBlockedFromUpdatingConsultation');
    $method->setAccessible(true);

    $atenmedi = new User;
    $atenmedi->departament = ['ATENMEDI'];
    $consultation = new TelemedicineConsultationPatient(['telemedicine_service_list_drift_id' => 2]);
    $consultation->setRelation('telemedicineServiceListDrift', new TelemedicineServiceList(['name' => 'Otro servicio']));

    expect($method->invoke(null, $atenmedi, $consultation))->toBeFalse();
});

it('bloquea ATENMEDI cuando el nombre del derivado indica traslado en ambulancia aunque el id no sea 3', function (): void {
    $method = (new \ReflectionClass(TelemedicineCaseTableDash::class))->getMethod('atenmediUserBlockedFromUpdatingConsultation');
    $method->setAccessible(true);

    $atenmedi = new User;
    $atenmedi->departament = ['ATENMEDI'];
    $drift = new TelemedicineServiceList(['name' => 'Derivado · Traslado en Ambulancia']);
    $consultation = new TelemedicineConsultationPatient(['telemedicine_service_list_drift_id' => 99]);
    $consultation->setRelation('telemedicineServiceListDrift', $drift);

    expect($method->invoke(null, $atenmedi, $consultation))->toBeTrue();
});

it('reconoce ATENMEDI en departament almacenado como JSON en string', function (): void {
    $method = (new \ReflectionClass(TelemedicineCaseTableDash::class))->getMethod('atenmediUserBlockedFromUpdatingConsultation');
    $method->setAccessible(true);

    $actor = new stdClass;
    $actor->departament = '["ATENMEDI","TELEMEDICINA"]';
    $consultation = new TelemedicineConsultationPatient(['telemedicine_service_list_drift_id' => 3]);

    expect($method->invoke(null, $actor, $consultation))->toBeTrue();
});
