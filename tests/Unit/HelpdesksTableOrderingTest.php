<?php

declare(strict_types=1);

it('replica el ordenamiento de tickets por status en todos los módulos', function () {
    $orderSql = \App\Support\HelpdeskTableConfigurator::statusOrderByCaseSql();

    expect($orderSql)
        ->toContain("WHEN 'PENDIENTE POR INICIAR' THEN 1")
        ->toContain("WHEN 'EN ANALISIS' THEN 3")
        ->toContain("WHEN 'CANCELADO' THEN 9");

    $panels = ['Administration', 'Marketing', 'Operations', 'Business'];

    foreach ($panels as $panel) {
        $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Tables/HelpdesksTable.php";
        $contents = file_get_contents($path);

        expect($contents)->toContain('HelpdeskTableConfigurator::configure');
    }
});
