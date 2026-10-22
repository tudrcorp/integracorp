<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ciclo de vida de una versión del organigrama.
 *
 * Siempre hay a lo sumo una VIGENTE. Un borrador nace como copia de la vigente y
 * solo BORRADOR y DEVUELTA se pueden editar. Nada se borra: un borrador que no
 * sigue queda DESCARTADO y la vigente anterior pasa a ARCHIVADA.
 */
enum OrgStructureVersionStatus: string
{
    case Draft = 'BORRADOR';
    case InReview = 'EN_REVISION';
    case Current = 'VIGENTE';
    case Archived = 'ARCHIVADA';
    case Returned = 'DEVUELTA';
    case Discarded = 'DESCARTADA';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::InReview => 'En revisión',
            self::Current => 'Vigente',
            self::Archived => 'Archivada',
            self::Returned => 'Devuelta',
            self::Discarded => 'Descartada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::InReview => 'info',
            self::Current => 'success',
            self::Archived => 'gray',
            self::Returned => 'danger',
            self::Discarded => 'gray',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Returned;
    }

    /**
     * Versiones que se pueden consultar en el historial del organigrama.
     */
    public function isHistorical(): bool
    {
        return $this === self::Current || $this === self::Archived;
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Draft->value, self::Returned->value, self::InReview->value];
    }
}
