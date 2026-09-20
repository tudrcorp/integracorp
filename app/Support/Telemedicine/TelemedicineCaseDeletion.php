<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineCase;

/**
 * Reglas de la eliminación lógica de un caso de telemedicina.
 *
 * Eliminar un caso nunca borra una fila: el caso pasa a `status = ELIMINADO`,
 * guarda el estatus que tenía y desaparece —junto con sus trazas— de todas las
 * consultas del sistema por scope global. La data queda intacta en base de
 * datos, de modo que reportes históricos, auditoría y claves foráneas siguen
 * cuadrando y el caso puede restaurarse.
 */
final class TelemedicineCaseDeletion
{
    public const STATUS = 'ELIMINADO';

    /**
     * Estatus que no impiden eliminar, pero merecen una advertencia explícita:
     * el caso ya cerró su ciclo clínico y sus documentos salieron al paciente.
     *
     * @var list<string>
     */
    public const SENSITIVE_STATUSES = [
        'ALTA MEDICA',
    ];

    public static function isDeleted(?TelemedicineCase $case): bool
    {
        if ($case === null) {
            return false;
        }

        return self::statusIsDeleted((string) $case->status);
    }

    public static function statusIsDeleted(?string $status): bool
    {
        return mb_strtoupper(trim((string) $status)) === self::STATUS;
    }

    /**
     * Un caso solo deja de poder eliminarse cuando ya está eliminado: el control
     * de quién puede hacerlo es el permiso granular, no el estatus clínico.
     */
    public static function canBeDeleted(?TelemedicineCase $case): bool
    {
        return $case !== null && ! self::isDeleted($case);
    }

    public static function isSensitive(?TelemedicineCase $case): bool
    {
        if ($case === null) {
            return false;
        }

        return in_array(mb_strtoupper(trim((string) $case->status)), self::SENSITIVE_STATUSES, true);
    }

    /**
     * Motivo por el que un caso no puede eliminarse, para mostrarlo al usuario.
     */
    public static function blockedReason(?TelemedicineCase $case): ?string
    {
        if ($case === null) {
            return 'El servicio no tiene un caso de telemedicina vinculado.';
        }

        if (self::isDeleted($case)) {
            return 'El caso '.$case->code.' ya está eliminado.';
        }

        return null;
    }
}
