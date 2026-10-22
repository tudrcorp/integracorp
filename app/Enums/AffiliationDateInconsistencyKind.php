<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipos de inconsistencia de fechas que detecta el sistema en una afiliación.
 *
 * Cada tipo declara su gravedad y cómo se repara:
 * - `auto`: la fecha correcta sale de los datos (los avisos); el analista solo revisa y aprueba.
 * - `date`: el sistema sugiere, pero la fecha la elige el analista.
 * - `manual`: no se repara desde aquí (requiere ajustar un aviso o una decisión de negocio).
 */
enum AffiliationDateInconsistencyKind: string
{
    case VigenciaVacia = 'VIGENCIA_VACIA';
    case VigenciaAbsurda = 'VIGENCIA_ABSURDA';
    case VigenciaComoFin = 'VIGENCIA_COMO_FIN';
    case FechasNoCoinciden = 'FECHAS_NO_COINCIDEN';
    case RenovacionFueraDeFecha = 'RENOVACION_FUERA_DE_FECHA';
    case AvisoFueraDeCalendario = 'AVISO_FUERA_DE_CALENDARIO';
    case CobrandoSinRenovar = 'COBRANDO_SIN_RENOVAR';
    case EmisionLejosDelPrimerPago = 'EMISION_LEJOS_DEL_PRIMER_PAGO';

    public const REPAIR_AUTO = 'auto';

    public const REPAIR_DATE = 'date';

    public const REPAIR_MANUAL = 'manual';

    public const SEVERITY_HIGH = 'ALTA';

    public const SEVERITY_MEDIUM = 'MEDIA';

    public const SEVERITY_LOW = 'BAJA';

    public function label(): string
    {
        return match ($this) {
            self::VigenciaVacia => 'Vigencia vacía',
            self::VigenciaAbsurda => 'Vigencia imposible',
            self::VigenciaComoFin => 'Vigencia guardada como fin del año',
            self::FechasNoCoinciden => 'Vigencia y avisos no coinciden',
            self::RenovacionFueraDeFecha => 'Renovación propuesta fuera de fecha',
            self::AvisoFueraDeCalendario => 'Aviso fuera del calendario',
            self::CobrandoSinRenovar => 'Cobrando un año sin renovar',
            self::EmisionLejosDelPrimerPago => 'Emisión lejos del primer pago',
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::VigenciaVacia, self::VigenciaAbsurda, self::VigenciaComoFin, self::FechasNoCoinciden => self::SEVERITY_HIGH,
            self::RenovacionFueraDeFecha, self::AvisoFueraDeCalendario, self::CobrandoSinRenovar => self::SEVERITY_MEDIUM,
            self::EmisionLejosDelPrimerPago => self::SEVERITY_LOW,
        };
    }

    public function repairMode(): string
    {
        return match ($this) {
            self::VigenciaVacia, self::VigenciaComoFin, self::RenovacionFueraDeFecha => self::REPAIR_AUTO,
            self::VigenciaAbsurda, self::FechasNoCoinciden, self::EmisionLejosDelPrimerPago => self::REPAIR_DATE,
            self::AvisoFueraDeCalendario, self::CobrandoSinRenovar => self::REPAIR_MANUAL,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public static function severityColor(?string $severity): string
    {
        return match ($severity) {
            self::SEVERITY_HIGH => 'danger',
            self::SEVERITY_MEDIUM => 'warning',
            default => 'gray',
        };
    }
}
