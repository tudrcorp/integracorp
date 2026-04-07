<?php

declare(strict_types=1);

it('los widgets de stats califican columnas de suppliers para evitar ambigüedad con joins', function () {
    $base = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Widgets/';
    foreach (['StatsOverviewPreferencialSupplier.php', 'StatsOverviewGeneralSupplier.php'] as $file) {
        $contents = file_get_contents($base.$file);
        expect($contents)->toContain('(new Supplier)->getTable()')
            ->toContain('->where("{$t}.status_convenio"')
            ->toContain('->whereBetween("{$t}.created_at"');
    }
});
