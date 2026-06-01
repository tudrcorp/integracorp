<?php

declare(strict_types=1);

namespace App\Support;

final class HelpdeskWorkGroupValidator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function validate(array $data, bool $requireTicketQuota = true): HelpdeskWorkGroupValidationResult
    {
        $name = trim((string) ($data['name'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'ACTIVO'));
        $ticketQuota = max(0, (int) ($data['total_tickets_assigned'] ?? 0));
        $teamColaboradorIds = $data['team_colaborador_ids'] ?? [];

        if ($name === '') {
            return HelpdeskWorkGroupValidationResult::failure(
                'Nombre requerido',
                'Indique un nombre para el grupo de trabajo.',
            );
        }

        if (! is_array($teamColaboradorIds)) {
            $teamColaboradorIds = [];
        }

        $teamColaboradorIds = array_values(array_unique(array_map(
            static fn (mixed $value): int => (int) $value,
            array_filter($teamColaboradorIds, static fn (mixed $value): bool => filled($value))
        )));

        if (count($teamColaboradorIds) < 2) {
            return HelpdeskWorkGroupValidationResult::failure(
                'Integrantes insuficientes',
                'Seleccione al menos dos colaboradores para el grupo.',
            );
        }

        $members = HelpdeskTeamMembersPayload::fromColaboradorIds($teamColaboradorIds);

        if (count($members) < 2) {
            return HelpdeskWorkGroupValidationResult::failure(
                'Colaboradores no válidos',
                'Debe seleccionar al menos dos colaboradores activos en el directorio.',
            );
        }

        $normalizedStatus = in_array($status, ['ACTIVO', 'INACTIVO'], true) ? $status : 'ACTIVO';

        if ($requireTicketQuota && $ticketQuota < 1) {
            return HelpdeskWorkGroupValidationResult::failure(
                'Cuota requerida',
                'Indique una cuota de tickets mayor a cero para el grupo.',
            );
        }

        return HelpdeskWorkGroupValidationResult::success(
            name: $name,
            status: $normalizedStatus,
            ticketQuota: $ticketQuota,
            colaboradorIds: $teamColaboradorIds,
            members: $members,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function validateForUpdate(array $data): HelpdeskWorkGroupValidationResult
    {
        return self::validate($data, requireTicketQuota: false);
    }
}
