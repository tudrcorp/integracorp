<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationAccountsPayables\Widgets;

use App\Support\Operations\AccountsPayableServiceDisplay;
use Filament\Widgets\Widget;

/**
 * Encabezado de «Cuentas por pagar»: lo pendiente, lo pagado este mes, lo pagado
 * sin comprobante y lo pendiente por tipo de servicio. Cada tarjeta filtra la
 * tabla de la página (evento `accounts-payable-shortcut`).
 */
class AccountsPayableOverview extends Widget
{
    protected string $view = 'filament.operations.accounts-payables.overview';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['overview' => AccountsPayableServiceDisplay::overview()];
    }

    public function shortcut(string $key): void
    {
        $this->dispatch('accounts-payable-shortcut', key: $key);
    }
}
