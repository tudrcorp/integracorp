<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Plan;
use App\Support\PlanGenerators\PlanGeneratorStructureImporter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Arma la página del plan de una propuesta económica a partir de la estructura
 * real del plan, para los planes que no son Inicial, Ideal ni Especial.
 *
 * Esos tres conservan su imagen de página completa, que trae horneados el
 * título, la matriz de beneficios y las condiciones. Cualquier otro plan
 * caía antes en la imagen del Ideal y salía con beneficios y columnas que no
 * eran los suyos; acá se compone con los datos del catálogo.
 *
 * La matriz se toma del mismo constructor que alimenta el generador de
 * cotizaciones, así que un plan se ve igual en la ficha, en el generador y en
 * la propuesta: una sola definición de qué cubre y hasta cuánto.
 */
final class QuotePdfPlanStructure
{
    /**
     * A partir de acá la tabla se aprieta para seguir entrando en A4 vertical.
     * No se recorta: ocultar una cobertura de una propuesta comercial sería
     * peor que imprimirla apretada.
     */
    public const DENSE_COLUMN_THRESHOLD = 5;

    /**
     * Beneficios y coberturas del plan, en el formato que consume el partial
     * `livewire.partials.quote-pdf-benefits-table`.
     *
     * @return array{columns: list<array{column_key: string, header_label: string}>, rows: array<string, mixed>, isDense: bool}
     */
    public static function benefitsMatrix(int|string|null $planId): array
    {
        $empty = ['columns' => [], 'rows' => [], 'isDense' => false];

        if (blank($planId)) {
            return $empty;
        }

        $plan = Plan::query()->find($planId);

        if ($plan === null) {
            return $empty;
        }

        $structure = PlanGeneratorStructureImporter::build($plan);
        $columnCount = count($structure['columns']);

        if ($columnCount > self::DENSE_COLUMN_THRESHOLD) {
            Log::info('Propuesta económica con matriz de beneficios ancha', [
                'plan_id' => (int) $plan->getKey(),
                'plan' => (string) $plan->description,
                'columnas' => $columnCount,
            ]);
        }

        return [
            'columns' => $structure['columns'],
            'rows' => $structure['rows'],
            'isDense' => $columnCount > self::DENSE_COLUMN_THRESHOLD,
        ];
    }

    /**
     * Precio por rango de edad cuando el plan no tiene coberturas. El desglose
     * por columnas no aplica: hay una sola tarifa por rango.
     *
     * @return list<array{age_range: string, total_persons: int, annual: float, biannual: float, quarterly: float}>
     */
    public static function flatRateRows(mixed $groupedByAgeRange): array
    {
        $rows = [];

        // Se recorre el iterable tal cual: castear una Collection a array
        // devuelve sus propiedades internas, no sus elementos.
        if (! is_iterable($groupedByAgeRange)) {
            return $rows;
        }

        foreach ($groupedByAgeRange as $ageRange => $items) {
            $items = $items instanceof Collection ? $items : collect($items);
            $first = $items->first();

            if ($first === null) {
                continue;
            }

            $first = is_object($first) ? $first : (object) $first;

            $rows[] = [
                'age_range' => (string) $ageRange,
                'total_persons' => (int) ($first->total_persons ?? 0),
                'annual' => (float) ($first->subtotal_anual ?? 0),
                'biannual' => (float) ($first->subtotal_biannual ?? 0),
                'quarterly' => (float) ($first->subtotal_quarterly ?? 0),
            ];
        }

        return $rows;
    }

    public static function planTitle(int|string|null $planId): string
    {
        if (blank($planId)) {
            return 'Propuesta Económica';
        }

        $description = Plan::query()->whereKey($planId)->value('description');

        return blank($description)
            ? 'Propuesta Económica'
            : 'Propuesta Económica - '.$description;
    }

    /**
     * Nota al pie de la matriz, tal como aparece hoy dentro de la imagen de los
     * planes históricos.
     *
     * @return array{title: string, body: string}
     */
    public static function acuteIllnessNote(): array
    {
        return [
            'title' => 'Beneficios para enfermedades agudas',
            'body' => 'Aquellas patologías de origen viral, infeccioso o bacteriana que tienen un comienzo súbito y una evolución rápida, incluyendo su resolución.',
        ];
    }

    /**
     * Condiciones del plan. Hoy viven dentro del PNG de cada plan histórico; acá
     * se transcriben para que la página compuesta diga lo mismo que el cliente
     * ya lee en las propuestas de Inicial, Ideal y Especial.
     *
     * @return list<string>
     */
    public static function conditions(): array
    {
        return [
            'Beneficio de Orientación médica telefónica con cobertura inmediata.',
            'Beneficios domiciliarios tienen período de espera ocho (08) días continuos.',
            'Asistencia médica por accidente derivado de nuestro equipo médico a través de OMT o AMD (plazo de espera 30 días continuos).',
            'Beneficio de asistencia médica por accidentes hasta agotar el plan contratado.',
            'Se excluyen patologías preexistentes.',
            'Atención médica a domicilio o in situ.',
            'Zona de cobertura Venezuela – 24 Horas.',
            'Opciones de pago disponibles: trimestral, semestral y anual.',
        ];
    }

    public static function validityNote(): string
    {
        return 'Propuesta válida por 30 días a partir de la fecha de emisión.';
    }
}
