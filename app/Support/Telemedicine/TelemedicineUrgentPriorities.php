<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

/**
 * Prioridades de caso que los encabezados de listados destacan como «Urgentes».
 */
final class TelemedicineUrgentPriorities
{
    /**
     * Nombres en telemedicine_priorities (con y sin tilde, el catálogo guarda «Critico»).
     *
     * @var list<string>
     */
    public const NAMES = ['Urgencia', 'Emergencia', 'Critico', 'Crítico'];

    /**
     * Condición SQL para usar dentro de un agregado sobre telemedicine_cases.
     *
     * @return array{0: string, 1: list<string>}
     */
    public static function sqlCondition(): array
    {
        $placeholders = implode(', ', array_fill(0, count(self::NAMES), '?'));

        return [
            'EXISTS (SELECT 1 FROM telemedicine_priorities AS tp '
            ."WHERE tp.id = telemedicine_cases.telemedicine_priority_id AND tp.name IN ({$placeholders}))",
            self::NAMES,
        ];
    }
}
