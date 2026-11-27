<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Momento del caso en que se cargó un documento propio: el médico en la consulta inicial o en un
 * seguimiento, o el analista de Atención WhatsApp con lo que envió el cliente.
 */
enum TelemedicineCaseAttachmentStage: string
{
    case InitialConsultation = 'CONSULTA_INICIAL';
    case FollowUp = 'SEGUIMIENTO';
    case WhatsApp = 'WHATSAPP';

    public function label(): string
    {
        return match ($this) {
            self::InitialConsultation => 'Consulta inicial',
            self::FollowUp => 'Seguimiento',
            self::WhatsApp => 'WhatsApp del cliente',
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
            self::WhatsApp => 'info',
        };
    }

    /**
     * Quién lo cargó, para la lista de documentos del caso.
     */
    public function uploaderLabel(): string
    {
        return match ($this) {
            self::WhatsApp => 'Enviado por el cliente por WhatsApp',
            default => 'Cargado por el médico',
        };
    }
}
