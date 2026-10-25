<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineConsultationFormErrors;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

it('lleva al campo del formulario las claves que Livewire no podía mostrar', function (): void {
    $visible = TelemedicineConsultationFormErrors::visible(ValidationException::withMessages([
        'telemedicine_service_list_id' => 'Ese servicio no está incluido en el uso clínico de este plan.',
        'complements' => ['Límite cubierto: 4 de 4 usos.', 'Solo no cubiertos.'],
        'feedbackOne' => 'El caso tiene servicios pendientes.',
    ]));

    expect($visible->errors())->toBe([
        'data.telemedicine_service_list_id' => ['Ese servicio no está incluido en el uso clínico de este plan.'],
        'data.complements' => ['Límite cubierto: 4 de 4 usos.', 'Solo no cubiertos.'],
        'data.feedbackOne' => ['El caso tiene servicios pendientes.'],
    ]);
});

it('no toca claves que ya apuntan a un lugar visible', function (string $key): void {
    expect(TelemedicineConsultationFormErrors::fieldKey($key))->toBe($key);
})->with([
    'campo del formulario' => 'data.telemedicine_patient_id',
    'campo anidado' => 'data.medications.0.medicines',
    'formulario de una acción' => 'mountedActions.0.data.reason',
]);

it('arma el aviso con todos los mensajes, sin repetir', function (): void {
    $exception = ValidationException::withMessages([
        'complements' => ['Cupo cubierto.', 'Cupo cubierto.'],
        'telemedicine_service_list_id' => 'Servicio no incluido.',
    ]);

    expect(TelemedicineConsultationFormErrors::summary($exception))->toBe('Cupo cubierto. Servicio no incluido.');
});

it('la página de crear consulta pasa por aquí la regla de cupo que perdía el mensaje', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');

    // El alta ya no se bloquea (24/10/2026): solo queda la regla de cupo clínico.
    expect($page)
        ->toContain('$this->runBusinessCheckVisibly(static fn () => ClinicalConsultationConsumption::assertCanSave(')
        ->toContain('throw TelemedicineConsultationFormErrors::visible($exception);')
        ->toContain("->title('No se pudo registrar la consulta')")
        ->not->toMatch('/^\s+ClinicalConsultationConsumption::assertCanSave\(/m');
});
