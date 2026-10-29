<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationAccountsPayables\Widgets;

use App\Support\Operations\AccountsPayableMonthlyLoad;
use App\Support\Operations\AccountsPayableServiceDisplay;
use Filament\Widgets\Widget;

/**
 * Encabezado de «Cuentas por pagar»: lo pendiente, lo pagado este mes, lo pagado
 * sin comprobante, las facturas cargadas por mes (con comparación) y lo pendiente
 * por tipo de servicio. Cada tarjeta filtra la tabla de la página (evento
 * `accounts-payable-shortcut`).
 */
class AccountsPayableOverview extends Widget
{
    protected string $view = 'filament.operations.accounts-payables.overview';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /** Mes de la tarjeta «Facturas cargadas», «Y-m». */
    public string $loadedMonth = '';

    public bool $compareLoadedMonth = true;

    public function mount(): void
    {
        $this->loadedMonth = AccountsPayableMonthlyLoad::resolveMonth(null)->format('Y-m');
    }

    /**
     * El valor llega del navegador: un mes fuera de las opciones vuelve al mes en curso.
     */
    public function updatedLoadedMonth(mixed $value): void
    {
        $month = AccountsPayableMonthlyLoad::resolveMonth($value)->format('Y-m');

        $this->loadedMonth = array_key_exists($month, AccountsPayableMonthlyLoad::monthOptions())
            ? $month
            : AccountsPayableMonthlyLoad::resolveMonth(null)->format('Y-m');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $monthOptions = AccountsPayableMonthlyLoad::monthOptions();

        if (! array_key_exists($this->loadedMonth, $monthOptions)) {
            $this->loadedMonth = (string) array_key_first($monthOptions);
        }

        return [
            'overview' => AccountsPayableServiceDisplay::overview(),
            'monthOptions' => $monthOptions,
            'loaded' => AccountsPayableMonthlyLoad::summary(AccountsPayableMonthlyLoad::resolveMonth($this->loadedMonth)),
        ];
    }

    public function shortcut(string $key): void
    {
        $this->dispatch('accounts-payable-shortcut', key: $key);
    }
}
