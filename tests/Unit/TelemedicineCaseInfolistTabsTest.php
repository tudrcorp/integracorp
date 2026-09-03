<?php

declare(strict_types=1);

it('infolist del caso de telemedicina usa pestañas para paciente y caso', function (): void {
    $c = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/Schemas/TelemedicineCaseInfolist.php');

    expect($c)
        ->toContain('Tabs::make')
        ->toContain('Tab::make')
        ->toContain('Paciente en el caso')
        ->toContain('Caso de telemedicina')
        ->toContain("Tab::make('Expediente documental')")
        ->toContain("Tab::make('Bitácoras AMD')")
        ->toContain('TelemedicineAmdBitacoraCatalog::viewContext')
        ->toContain("Tab::make('Bitácora Observaciones')")
        ->toContain("Tab::make('Bitácora Mensajería Interna')")
        ->toContain("RepeatableEntry::make('observations')")
        ->toContain('Bitácora de observaciones')
        ->toContain('Bitácora de mensajería');
});

it('evita que el correo del autor invada la columna de actualización en la bitácora', function (): void {
    $root = dirname(__DIR__, 2);
    $theme = file_get_contents($root.'/resources/css/filament/admin/theme.css');

    $panels = [
        $root.'/app/Filament/Operations/Resources/TelemedicineCases/Schemas/TelemedicineCaseInfolist.php',
        $root.'/app/Filament/Telemedicina/Resources/TelemedicineCases/Schemas/TelemedicineCaseInfolist.php',
    ];

    foreach ($panels as $panel) {
        $contents = file_get_contents($panel);

        expect($contents)
            ->toContain("'class' => 'fi-bitacora-author',")
            ->toContain("TableColumn::make('Registrado por')->width('20%')")
            ->toContain("TableColumn::make('Observación')->width('44%')");

        $widths = [];
        preg_match_all("/TableColumn::make\('[^']+'\)->width\('(\d+)%'\)/u", $contents, $widths);

        expect(array_sum(array_map('intval', $widths[1])))->toBe(100);
    }

    expect($theme)
        ->toContain('.fi-bitacora-author {')
        ->toContain('overflow-wrap: anywhere;');
});
