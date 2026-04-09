<?php

declare(strict_types=1);

use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\ConsultationEditSession;

uses(Tests\TestCase::class);

it('almacena en sesión caso, paciente, acción edit y estatus de la consulta', function (): void {
    $case = new TelemedicineCase(['id' => 10, 'code' => 'CASO-TEST']);
    $patient = new TelemedicinePatient(['id' => 20]);

    ConsultationEditSession::storeForEdit($case, $patient, 'CONSULTA INICIAL');

    expect(session('case'))->toBe($case)
        ->and(session('patient'))->toBe($patient)
        ->and(session('action'))->toBe('edit')
        ->and(session('status'))->toBe('CONSULTA INICIAL');
});
