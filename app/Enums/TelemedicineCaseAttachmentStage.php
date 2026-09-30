<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Momento del caso en que el médico cargó un documento propio.
 */
enum TelemedicineCaseAttachmentStage: string
{
    case InitialConsultation = 'CONSULTA_INICIAL';
    case FollowUp = 'SEGUIMIENTO';

    public function label(): string
    {
        return match ($this) {
            self::InitialConsultation => 'Consulta inicial',
            self::FollowUp => 'Seguimiento',
        };
    }

    /**
     * Etiqueta con el número de seguimiento cuando se conoce: «Seguimiento 2».
     */
    public function labelWithNumber(?int $followUpNumber): string
    {
        if ($this === self::FollowUp && $followUpNumber !== null && $followUpNumber > 0) {
            return $this->label().' '.$followUpNumber;
        }

        return $this->label();
    }

    /**
     * Tono de la etiqueta en la sección de documentos de la bitácora.
     */
    public function tone(): string
    {
        return match ($this) {
            self::InitialConsultation => 'primary',
            self::FollowUp => 'success',
        };
    }
}
