<?php

declare(strict_types=1);

use App\Models\Agency;
use App\Models\Agent;

it('declara el crédito TDEV asignado en agencias y agentes con default cero', function (): void {
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_09_24_104440_add_amount_asign_credit_tdev_to_agencies_and_agents_tables.php');

    expect($migration)
        ->toContain("decimal('amount_asign_credit_tdev', 14, 2)->nullable()->default(0)")
        ->and((new Agency)->getFillable())->toContain('amount_asign_credit_tdev')
        ->and((new Agent)->getFillable())->toContain('amount_asign_credit_tdev')
        ->and((new Agency)->getCasts())->toMatchArray(['amount_asign_credit_tdev' => 'decimal:2'])
        ->and((new Agent)->getCasts())->toMatchArray(['amount_asign_credit_tdev' => 'decimal:2']);
});
