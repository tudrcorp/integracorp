<?php

declare(strict_types=1);

namespace App\Support\IndicadoresDeDesempeno;

final class IndicadoresDeDesempenoCsvRows
{
    /**
     * @return list<list<string>>
     */
    public static function build(string $from, string $to): array
    {
        $tickets = ColaboradoresHelpdeskTicketsChartSeries::totalsByColaborador(from: $from, to: $to);
        $observations = SupplierObservationsChartSeries::groupedByCollaborator(from: $from, to: $to);
        $systemUpdates = SupplierProviderSystemUpdateChartSeries::groupedByCollaborator(from: $from, to: $to);
        $newProviders = SupplierNewProviderCreationChartSeries::groupedByCollaborator(from: $from, to: $to);
        $acceptanceLetters = SupplierAcceptanceLettersChartSeries::groupedByCollaborator(from: $from, to: $to);

        $collaborators = self::sortedCollaborators(
            $tickets,
            $observations,
            $systemUpdates,
            $newProviders,
            $acceptanceLetters,
        );

        $rows = [
            ['Indicadores de desempeño'],
            ['Período desde', $from],
            ['Período hasta', $to],
            ['Exportado', now()->format('Y-m-d H:i:s')],
            [],
            ['Resumen por colaborador'],
            [
                'Colaborador',
                'Tickets creados',
                'Observaciones jurídicos',
                'Observaciones naturales',
                'Actualizaciones sistema jurídicos',
                'Actualizaciones sistema naturales',
                'Nuevos proveedores jurídicos',
                'Nuevos proveedores naturales',
                'Cartas aceptación jurídicos',
                'Cartas aceptación naturales',
                'Total actividades',
            ],
        ];

        foreach ($collaborators as $collaborator) {
            $rows[] = self::summaryRow(
                $collaborator,
                $tickets,
                $observations,
                $systemUpdates,
                $newProviders,
                $acceptanceLetters,
            );
        }

        $rows[] = [];
        $rows[] = ['Detalle: tickets creados'];
        $rows[] = ['Colaborador', 'Total'];
        $rows = array_merge($rows, self::detailRows($tickets['labels'], $tickets['totals']));

        $rows[] = [];
        $rows[] = ['Detalle: observaciones'];
        $rows[] = [
            'Colaborador',
            SupplierObservationsChartSeries::LABEL_JURIDICOS,
            SupplierObservationsChartSeries::LABEL_NATURALES,
            'Total',
        ];
        $rows = array_merge($rows, self::dualDetailRows(
            $observations['labels'],
            $observations['juridicos'],
            $observations['naturales'],
        ));

        $rows[] = [];
        $rows[] = ['Detalle: actualizaciones en sistema'];
        $rows[] = [
            'Colaborador',
            SupplierProviderSystemUpdateChartSeries::LABEL_JURIDICOS,
            SupplierProviderSystemUpdateChartSeries::LABEL_NATURALES,
            'Total',
        ];
        $rows = array_merge($rows, self::dualDetailRows(
            $systemUpdates['labels'],
            $systemUpdates['juridicos'],
            $systemUpdates['naturales'],
        ));

        $rows[] = [];
        $rows[] = ['Detalle: nuevos proveedores'];
        $rows[] = [
            'Colaborador',
            SupplierNewProviderCreationChartSeries::LABEL_JURIDICOS,
            SupplierNewProviderCreationChartSeries::LABEL_NATURALES,
            'Total',
        ];
        $rows = array_merge($rows, self::dualDetailRows(
            $newProviders['labels'],
            $newProviders['juridicos'],
            $newProviders['naturales'],
        ));

        $rows[] = [];
        $rows[] = ['Detalle: cartas de aceptación'];
        $rows[] = [
            'Colaborador',
            SupplierAcceptanceLettersChartSeries::LABEL_JURIDICOS,
            SupplierAcceptanceLettersChartSeries::LABEL_NATURALES,
            'Total',
        ];
        $rows = array_merge($rows, self::dualDetailRows(
            $acceptanceLetters['labels'],
            $acceptanceLetters['juridicos'],
            $acceptanceLetters['naturales'],
        ));

        return $rows;
    }

    /**
     * @param  array{labels: list<string>, totals: list<int>}  $tickets
     * @param  array{labels: list<string>, juridicos: list<int>, naturales: list<int>}  $observations
     * @param  array{labels: list<string>, juridicos: list<int>, naturales: list<int>}  $systemUpdates
     * @param  array{labels: list<string>, juridicos: list<int>, naturales: list<int>}  $newProviders
     * @param  array{labels: list<string>, juridicos: list<int>, naturales: list<int>}  $acceptanceLetters
     * @return list<string>
     */
    private static function sortedCollaborators(
        array $tickets,
        array $observations,
        array $systemUpdates,
        array $newProviders,
        array $acceptanceLetters,
    ): array {
        $collaborators = collect($tickets['labels'])
            ->merge($observations['labels'])
            ->merge($systemUpdates['labels'])
            ->merge($newProviders['labels'])
            ->merge($acceptanceLetters['labels'])
            ->unique()
            ->values()
            ->all();

        /** @var array<string, int> $totals */
        $totals = [];

        foreach ($collaborators as $collaborator) {
            $summary = self::summaryRow(
                $collaborator,
                $tickets,
                $observations,
                $systemUpdates,
                $newProviders,
                $acceptanceLetters,
            );

            $totals[$collaborator] = (int) ($summary[10] ?? 0);
        }

        uksort($totals, function (string $left, string $right) use ($totals): int {
            if ($totals[$left] !== $totals[$right]) {
                return $totals[$right] <=> $totals[$left];
            }

            return strcmp($left, $right);
        });

        return array_keys($totals);
    }

    /**
     * @param  array{labels: list<string>, totals: list<int>}  $tickets
     * @param  array{labels: list<string>, juridicos: list<int>, naturales: list<int>}  $observations
     * @param  array{labels: list<string>, juridicos: list<int>, naturales: list<int>}  $systemUpdates
     * @param  array{labels: list<string>, juridicos: list<int>, naturales: list<int>}  $newProviders
     * @param  array{labels: list<string>, juridicos: list<int>, naturales: list<int>}  $acceptanceLetters
     * @return list<string>
     */
    private static function summaryRow(
        string $collaborator,
        array $tickets,
        array $observations,
        array $systemUpdates,
        array $newProviders,
        array $acceptanceLetters,
    ): array {
        $ticketTotal = self::valueForCollaborator($collaborator, $tickets['labels'], $tickets['totals']);
        $obsJuridicos = self::valueForCollaborator($collaborator, $observations['labels'], $observations['juridicos']);
        $obsNaturales = self::valueForCollaborator($collaborator, $observations['labels'], $observations['naturales']);
        $sysJuridicos = self::valueForCollaborator($collaborator, $systemUpdates['labels'], $systemUpdates['juridicos']);
        $sysNaturales = self::valueForCollaborator($collaborator, $systemUpdates['labels'], $systemUpdates['naturales']);
        $newJuridicos = self::valueForCollaborator($collaborator, $newProviders['labels'], $newProviders['juridicos']);
        $newNaturales = self::valueForCollaborator($collaborator, $newProviders['labels'], $newProviders['naturales']);
        $cartaJuridicos = self::valueForCollaborator($collaborator, $acceptanceLetters['labels'], $acceptanceLetters['juridicos']);
        $cartaNaturales = self::valueForCollaborator($collaborator, $acceptanceLetters['labels'], $acceptanceLetters['naturales']);

        $grandTotal = $ticketTotal
            + $obsJuridicos
            + $obsNaturales
            + $sysJuridicos
            + $sysNaturales
            + $newJuridicos
            + $newNaturales
            + $cartaJuridicos
            + $cartaNaturales;

        return [
            $collaborator,
            (string) $ticketTotal,
            (string) $obsJuridicos,
            (string) $obsNaturales,
            (string) $sysJuridicos,
            (string) $sysNaturales,
            (string) $newJuridicos,
            (string) $newNaturales,
            (string) $cartaJuridicos,
            (string) $cartaNaturales,
            (string) $grandTotal,
        ];
    }

    /**
     * @param  list<string>  $labels
     * @param  list<int>  $values
     * @return list<list<string>>
     */
    private static function detailRows(array $labels, array $values): array
    {
        $rows = [];

        foreach ($labels as $index => $label) {
            $rows[] = [
                $label,
                (string) (int) ($values[$index] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $labels
     * @param  list<int>  $juridicos
     * @param  list<int>  $naturales
     * @return list<list<string>>
     */
    private static function dualDetailRows(array $labels, array $juridicos, array $naturales): array
    {
        $rows = [];

        foreach ($labels as $index => $label) {
            $juridicoTotal = (int) ($juridicos[$index] ?? 0);
            $naturalTotal = (int) ($naturales[$index] ?? 0);

            $rows[] = [
                $label,
                (string) $juridicoTotal,
                (string) $naturalTotal,
                (string) ($juridicoTotal + $naturalTotal),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $labels
     * @param  list<int>  $values
     */
    private static function valueForCollaborator(string $collaborator, array $labels, array $values): int
    {
        $index = array_search($collaborator, $labels, true);

        if ($index === false) {
            return 0;
        }

        return (int) ($values[$index] ?? 0);
    }
}
