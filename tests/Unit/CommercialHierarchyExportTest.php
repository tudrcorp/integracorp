<?php

declare(strict_types=1);

use App\Models\Agency;
use App\Services\CommercialHierarchyExportService;
use App\Support\CommercialStructure\CommercialHierarchyExportRows;

uses(Tests\TestCase::class);

/**
 * @return array<string, mixed>
 */
function arbolDeJerarquiaDePrueba(): array
{
    $node = fn (string $title, string $name, string $code, string $structure = ''): array => [
        'kind' => $title === 'Agente' || $title === 'Subagente' ? 'agent' : 'agency',
        'title' => $title,
        'name' => $name,
        'subtitle' => $code,
        'status' => 'ACTIVO',
        'tone' => 'emerald',
        'structure' => $structure,
        'is_highlighted' => false,
    ];

    return [
        'headquarters' => null,
        'master' => $node('Agencia master', 'YV SOLUTIONS', 'TDG-162'),
        'master_direct_agents' => [
            ['agent' => $node('Agente', 'ANA HIDALGO', 'AGT-000459'), 'subagents' => []],
        ],
        'generals' => [
            [
                'agency' => $node('Agencia general', 'BELKIS CARRION', 'TDG-174', '6 agente(s) · 0 subagente(s)'),
                'agents' => [
                    [
                        'agent' => $node('Agente', 'ALONSO RODRIGUEZ', 'AGT-000465'),
                        'subagents' => [$node('Subagente', 'NORELY RIVAS', 'AGT-000492')],
                    ],
                ],
            ],
        ],
    ];
}

it('aplana la jerarquía en recorrido de profundidad con su nivel y su superior', function () {
    $rows = CommercialHierarchyExportRows::fromTree(arbolDeJerarquiaDePrueba());

    expect(array_column($rows, 'code'))->toBe([
        'TDG-162',
        'AGT-000459',
        'TDG-174',
        'AGT-000465',
        'AGT-000492',
    ])
        ->and(array_column($rows, 'level'))->toBe([1, 2, 2, 3, 4])
        ->and(array_column($rows, 'type'))->toBe([
            'Agencia master',
            'Agente',
            'Agencia general',
            'Agente',
            'Subagente',
        ])
        ->and(array_column($rows, 'parent_code'))->toBe([
            '',
            'TDG-162',
            'TDG-162',
            'TDG-174',
            'AGT-000465',
        ]);
});

it('arma la ruta completa de códigos desde la cabecera hasta cada nodo', function () {
    $rows = CommercialHierarchyExportRows::fromTree(arbolDeJerarquiaDePrueba());
    $paths = array_combine(array_column($rows, 'code'), array_column($rows, 'path'));

    expect($paths['TDG-162'])->toBe('TDG-162')
        ->and($paths['AGT-000465'])->toBe('TDG-162 › TDG-174 › AGT-000465')
        ->and($paths['AGT-000492'])->toBe('TDG-162 › TDG-174 › AGT-000465 › AGT-000492');
});

it('baja un nivel toda la red cuando la casa matriz encabeza el árbol', function () {
    $tree = arbolDeJerarquiaDePrueba();
    $tree['headquarters'] = [
        'kind' => 'agency',
        'title' => 'Casa matriz',
        'name' => 'TUDRENCASA',
        'subtitle' => 'TDG-100',
        'status' => 'ACTIVO',
        'tone' => 'blue',
        'structure' => '',
        'is_highlighted' => false,
    ];

    $rows = CommercialHierarchyExportRows::fromTree($tree);

    expect($rows[0]['code'])->toBe('TDG-100')
        ->and($rows[0]['level'])->toBe(1)
        ->and($rows[1]['code'])->toBe('TDG-162')
        ->and($rows[1]['level'])->toBe(2)
        ->and($rows[1]['parent_code'])->toBe('TDG-100')
        ->and($rows[1]['path'])->toBe('TDG-100 › TDG-162');
});

it('cuenta los totales sobre las filas exportadas', function () {
    $totals = CommercialHierarchyExportRows::totals(
        CommercialHierarchyExportRows::fromTree(arbolDeJerarquiaDePrueba()),
    );

    expect($totals)->toBe([
        'generals' => 1,
        'agents' => 2,
        'subagents' => 1,
        'total' => 5,
    ]);
});

it('sangra el nombre en la hoja según la profundidad', function () {
    expect(CommercialHierarchyExportService::indentedName(1, 'YV SOLUTIONS'))->toBe('YV SOLUTIONS')
        ->and(CommercialHierarchyExportService::indentedName(2, 'ANA HIDALGO'))->toContain('└─ ANA HIDALGO')
        ->and(mb_strlen(CommercialHierarchyExportService::indentedName(4, 'X')))
        ->toBeGreaterThan(mb_strlen(CommercialHierarchyExportService::indentedName(2, 'X')));
});

it('la hoja lleva las tres pistas que permiten reconstruir la jerarquía', function () {
    expect(CommercialHierarchyExportService::headers())
        ->toContain('Nivel')
        ->toContain('Jerarquía')
        ->toContain('Depende de')
        ->toContain('Ruta jerárquica');
});

it('nombra los archivos con el código de la agencia y la fecha', function () {
    $agency = new Agency(['code' => 'TDG-162']);

    expect(CommercialHierarchyExportService::filenameFor($agency, 'pdf'))
        ->toStartWith('jerarquia-comercial-TDG-162-')
        ->toEndWith('.pdf');
});

it('el PDF imprime nivel, ruta y totales de cada nodo', function () {
    $rows = CommercialHierarchyExportRows::fromTree(arbolDeJerarquiaDePrueba());

    $html = view('documents.commercial-hierarchy', [
        'agency' => new Agency(['code' => 'TDG-162', 'name_corporative' => 'YV SOLUTIONS']),
        'rows' => $rows,
        'totals' => CommercialHierarchyExportRows::totals($rows),
        'generatedAt' => '16/09/2026 10:00',
    ])->render();

    expect($html)->toContain('TDG-162 › TDG-174 › AGT-000465 › AGT-000492')
        ->and($html)->toContain('Subagente')
        ->and($html)->toContain('Agencias generales')
        // La cabecera debe repetirse: la jerarquía de una master ocupa varias páginas.
        ->and($html)->toContain('display: table-header-group')
        ->and($html)->toContain('table-layout: fixed')
        // El conector y el separador de ruta solo existen en DejaVu Sans.
        ->and($html)->toContain('DejaVu Sans');
});

it('la página del panel master ofrece los dos botones de exportación', function () {
    $src = (string) file_get_contents(base_path('app/Filament/Master/Pages/ViewMyHierarchy.php'));

    expect($src)
        ->toContain("Action::make('export_hierarchy_pdf')")
        ->toContain("Action::make('export_hierarchy_excel')")
        ->toContain('Exportar PDF')
        ->toContain('Exportar Excel')
        // La agencia sale del usuario autenticado, nunca de la petición.
        ->toContain('Auth::user()?->code_agency')
        ->toContain('deleteFileAfterSend(true)');
});
