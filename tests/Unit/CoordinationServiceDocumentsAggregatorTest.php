<?php

declare(strict_types=1);

use App\Support\Operations\CoordinationServiceDocumentsAggregator;

it('reúne documentos de la coordinación y de todas las órdenes de servicio asociadas', function (): void {
    $rows = CoordinationServiceDocumentsAggregator::buildRows(
        [
            [
                'document_name' => 'orden-medica',
                'file_path' => 'operation-coordination-services/1/documents/orden-medica.pdf',
                'document_types' => ['Orden médica'],
                'services' => ['Laboratorio: CREATININA'],
                'uploaded_at' => '2026-06-29 10:00:00',
            ],
        ],
        [
            [
                'label' => 'LABORATORIOS · ORD-0010',
                'documents' => [
                    [
                        'document_name' => 'resultado',
                        'file_path' => 'operation-service-orders/10/documents/resultado.pdf',
                        'document_types' => ['Resultado'],
                        'uploaded_at' => '2026-06-29 12:00:00',
                    ],
                ],
            ],
        ],
    );

    expect($rows)->toHaveCount(2);

    // Ordenado por fecha descendente: el de la orden (12:00) va primero.
    expect($rows[0]['document_name'])->toBe('resultado')
        ->and($rows[0]['source'])->toBe('Orden de servicio')
        ->and($rows[0]['services'])->toBe(['LABORATORIOS · ORD-0010'])
        ->and($rows[0]['service'])->toBe('LABORATORIOS · ORD-0010');

    expect($rows[1]['document_name'])->toBe('orden-medica')
        ->and($rows[1]['source'])->toBe('Coordinación')
        ->and($rows[1]['services'])->toBe(['Laboratorio: CREATININA']);
});

it('usa una etiqueta por defecto cuando la orden no tiene nombre y conserva los servicios ya asignados', function (): void {
    $rows = CoordinationServiceDocumentsAggregator::buildRows(
        [],
        [
            [
                'label' => '',
                'documents' => [
                    [
                        'document_name' => 'sin-etiqueta',
                        'file_path' => 'x/y.pdf',
                        'uploaded_at' => '2026-06-29 09:00:00',
                    ],
                    [
                        'document_name' => 'con-servicio',
                        'file_path' => 'x/z.pdf',
                        'services' => ['Estudio: RX'],
                        'uploaded_at' => '2026-06-29 08:00:00',
                    ],
                ],
            ],
        ],
    );

    expect($rows[0]['services'])->toBe(['Orden de servicio'])
        ->and($rows[1]['services'])->toBe(['Estudio: RX']);
});
