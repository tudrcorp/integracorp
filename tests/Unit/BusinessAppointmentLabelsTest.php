<?php

declare(strict_types=1);

use App\Filament\Business\Resources\BusinessAppointments\BusinessAppointmentLabels;

it('define catálogo de estados de cita', function (): void {
    expect(BusinessAppointmentLabels::statusOptions())->not->toBeEmpty();
});

it('resuelve etiquetas y colores para estados conocidos', function (): void {
    expect(BusinessAppointmentLabels::statusLabel('PENDIENTE'))->toBe('Pendiente')
        ->and(BusinessAppointmentLabels::statusColor('ATENDIDA'))->toBe('success')
        ->and(BusinessAppointmentLabels::statusColor('CANCELADA'))->toBe('danger');
});
