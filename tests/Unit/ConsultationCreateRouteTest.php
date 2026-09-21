<?php

declare(strict_types=1);

use App\Support\Telemedicine\ConsultationCreateRoute;

it('arma la url de consulta con paciente, caso y consulta', function (): void {
    $params = ConsultationCreateRoute::parameters(12, 34, 56);

    expect($params)
        ->toBe([
            ConsultationCreateRoute::PATIENT_PARAM => 12,
            ConsultationCreateRoute::CASE_PARAM => 34,
            ConsultationCreateRoute::CONSULTATION_PARAM => 56,
        ]);
});

it('omite parametros nulos o invalidos', function (): void {
    expect(ConsultationCreateRoute::parameters(null, 0, null))->toBe([])
        ->and(ConsultationCreateRoute::parameters(9))->toBe([ConsultationCreateRoute::PATIENT_PARAM => 9]);
});
