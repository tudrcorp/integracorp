<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\BusinessAppointments;

final class BusinessAppointmentLabels
{
    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'PENDIENTE' => 'Pendiente',
            'ATENDIDA' => 'Atendida',
            'CANCELADA' => 'Cancelada',
            'REAGENDADA' => 'Reagendada',
        ];
    }

    public static function statusLabel(?string $key): string
    {
        if ($key === null || $key === '') {
            return '—';
        }

        return self::statusOptions()[$key] ?? $key;
    }

    public static function statusColor(?string $state): string
    {
        return match ($state) {
            'PENDIENTE' => 'warning',
            'ATENDIDA' => 'success',
            'CANCELADA' => 'danger',
            'REAGENDADA' => 'info',
            default => 'gray',
        };
    }
}
