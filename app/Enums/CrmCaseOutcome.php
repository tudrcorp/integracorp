<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cómo terminó un caso de Atención WhatsApp. Se pide al cerrar o devolver al bot:
 * sin esto no se sabe por qué se pierden las ventas ni qué resuelve cada equipo.
 */
enum CrmCaseOutcome: string
{
    case Sold = 'vendido';
    case Quoted = 'cotizado';
    case Solved = 'resuelto';
    case Price = 'precio';
    case HasPlan = 'ya_tiene';
    case NotEligible = 'no_califica';
    case InfoOnly = 'informacion';
    case NoAnswer = 'no_responde';
    case Referred = 'derivado';
    case Spam = 'spam';
    case Other = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Sold => 'Vendido o afiliado',
            self::Quoted => 'Cotizado, decide luego',
            self::Solved => 'Gestión resuelta',
            self::Price => 'Le pareció caro',
            self::HasPlan => 'Ya tiene un plan',
            self::NotEligible => 'No califica (edad o condición)',
            self::InfoOnly => 'Solo pedía información',
            self::NoAnswer => 'Dejó de responder',
            self::Referred => 'Lo atiende otra área',
            self::Spam => 'Número equivocado o spam',
            self::Other => 'Otro motivo',
        };
    }

    /**
     * Cuenta como venta en el tablero del supervisor.
     */
    public function isWon(): bool
    {
        return $this === self::Sold;
    }

    public function needsNote(): bool
    {
        return $this === self::Other;
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

    public static function labelFor(?string $value): ?string
    {
        return $value !== null ? self::tryFrom($value)?->label() : null;
    }
}
