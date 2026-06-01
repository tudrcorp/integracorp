<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\HelpDesk;

final class HelpdeskTaskStatusOptions
{
    public const STATUS_PENDING = 'PENDIENTE POR INICIAR';

    public const STATUS_IN_PROGRESS = 'EN PROCESO';

    public const STATUS_IN_ANALYSIS = 'EN ANALISIS';

    public const STATUS_PLANNED = 'PLANIFICADO';

    public const STATUS_IN_DEVELOPMENT = 'EN DESARROLLO';

    public const STATUS_QA = 'PRUEBAS / QA';

    public const STATUS_WAITING = 'ESPERANDO TERCEROS / EN PAUSA';

    public const STATUS_DONE = 'TERMINADO';

    public const STATUS_CANCELLED = 'CANCELADO';

    /**
     * @return list<string>
     */
    public static function terminalStatuses(): array
    {
        return [
            self::STATUS_DONE,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente por iniciar',
            self::STATUS_IN_PROGRESS => 'En proceso',
            self::STATUS_IN_ANALYSIS => 'En Análisis',
            self::STATUS_PLANNED => 'Planificado',
            self::STATUS_IN_DEVELOPMENT => 'En Desarrollo',
            self::STATUS_QA => 'Pruebas / QA',
            self::STATUS_WAITING => 'Esperando Terceros / En Pausa',
            self::STATUS_DONE => 'Terminado',
            self::STATUS_CANCELLED => 'Cancelado',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function nonTerminal(): array
    {
        return array_diff_key(self::all(), array_flip(self::terminalStatuses()));
    }

    /**
     * Opciones del modal «Actualizar estado»:
     * - Creador del ticket: solo Terminado y Cancelado.
     * - Usuario asignado (ejecutor): todos los operativos, sin Terminado ni Cancelado.
     * - Si es creador y asignado: ve todos los estados.
     *
     * @return array<string, string>
     */
    public static function forSelect(?HelpDesk $record, ?string $currentUserName, bool $isAssignee = false): array
    {
        if ($record === null) {
            return self::nonTerminal();
        }

        $isCreator = self::userIsTicketCreator($record, $currentUserName);

        if ($isCreator && $isAssignee) {
            return self::all();
        }

        if ($isCreator) {
            return self::terminalOnlyOptions();
        }

        if ($isAssignee) {
            return self::executorOptions($record);
        }

        return self::executorOptions($record);
    }

    public static function statusModalDescription(HelpDesk $record, ?string $currentUserName, bool $isAssignee): string
    {
        $isCreator = self::userIsTicketCreator($record, $currentUserName);

        if ($isCreator && ! $isAssignee) {
            return 'Como creador del ticket, solo puede marcarlo como Terminado o Cancelado.';
        }

        if ($isAssignee && ! $isCreator) {
            return 'Como analista asignado, seleccione el nuevo estado y registre una nota con el motivo o la explicación del cambio.';
        }

        if ($isCreator && $isAssignee) {
            return 'Usted es creador y asignado: puede usar cualquier estado del flujo. Debe documentar el motivo del cambio en la nota obligatoria.';
        }

        return 'Seleccione el nuevo estado operativo del ticket.';
    }

    /**
     * Valida el estado según las mismas reglas del modal de actualización.
     */
    public static function sanitizeStatusForSave(
        HelpDesk $record,
        string $newStatus,
        ?string $currentUserName,
        bool $isAssignee = false
    ): string {
        $allowed = array_keys(self::forSelect($record, $currentUserName, $isAssignee));

        return in_array($newStatus, $allowed, true) ? $newStatus : $record->status;
    }

    public static function userIsTicketCreator(HelpDesk $record, ?string $currentUserName): bool
    {
        if ($currentUserName === null || trim($currentUserName) === '') {
            return false;
        }

        return trim((string) $record->created_by) === trim($currentUserName);
    }

    /**
     * @return array<string, string>
     */
    private static function terminalOnlyOptions(): array
    {
        return array_intersect_key(self::all(), array_flip(self::terminalStatuses()));
    }

    /**
     * @return array<string, string>
     */
    private static function executorOptions(HelpDesk $record): array
    {
        $options = self::nonTerminal();
        $status = $record->status;

        if (in_array($status, self::terminalStatuses(), true) && isset(self::all()[$status])) {
            $options[$status] = self::all()[$status];
        }

        return $options;
    }

    public static function badgeColor(?string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_IN_PROGRESS => 'primary',
            self::STATUS_IN_ANALYSIS => 'info',
            self::STATUS_PLANNED => 'gray',
            self::STATUS_IN_DEVELOPMENT => 'primary',
            self::STATUS_QA => 'warning',
            self::STATUS_WAITING => 'gray',
            self::STATUS_DONE => 'success',
            self::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }

    public static function icon(?string $status): ?string
    {
        return match ($status) {
            self::STATUS_PENDING => 'heroicon-m-clock',
            self::STATUS_IN_PROGRESS => 'heroicon-m-arrow-path',
            self::STATUS_IN_ANALYSIS => 'heroicon-m-magnifying-glass',
            self::STATUS_PLANNED => 'heroicon-m-calendar-days',
            self::STATUS_IN_DEVELOPMENT => 'heroicon-m-code-bracket',
            self::STATUS_QA => 'heroicon-m-beaker',
            self::STATUS_WAITING => 'heroicon-m-pause-circle',
            self::STATUS_DONE => 'heroicon-m-check-circle',
            self::STATUS_CANCELLED => 'heroicon-m-x-circle',
            default => 'heroicon-m-flag',
        };
    }

    public static function iconColor(?string $status): string
    {
        return self::badgeColor($status);
    }
}
