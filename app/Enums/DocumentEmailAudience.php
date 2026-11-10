<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A quién va dirigido un copy de correo de un documento (recibo de pago, aviso de cobro).
 *
 * El responsable de la afiliación es el agente y la agencia juntos: reciben un mismo correo.
 */
enum DocumentEmailAudience: string
{
    case Affiliate = 'AFILIADO';
    case Responsible = 'RESPONSABLE';
    case Both = 'AMBOS';

    public function label(): string
    {
        return match ($this) {
            self::Affiliate => 'Afiliado',
            self::Responsible => 'Agente / agencia',
            self::Both => 'Afiliado y agente / agencia',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Affiliate => 'info',
            self::Responsible => 'warning',
            self::Both => 'gray',
        };
    }

    /**
     * ¿Un copy con esta audiencia puede usarse para el destinatario indicado?
     */
    public function servesRecipient(self $recipient): bool
    {
        return $this === self::Both || $this === $recipient;
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
}
