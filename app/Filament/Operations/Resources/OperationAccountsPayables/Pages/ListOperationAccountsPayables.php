<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationAccountsPayables\Pages;

use App\Enums\StatusCuentaPorPagar;
use App\Filament\Operations\Resources\OperationAccountsPayables\OperationAccountsPayableResource;
use App\Filament\Operations\Resources\OperationAccountsPayables\Widgets\AccountsPayableOverview;
use App\Support\Operations\AccountsPayableServiceDisplay;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListOperationAccountsPayables extends ListRecords
{
    protected static string $resource = OperationAccountsPayableResource::class;

    protected static ?string $title = 'Cuentas por pagar';

    public function getSubheading(): ?string
    {
        return 'Facturas de los proveedores por los servicios de las órdenes: laboratorios, imagenología, medicamentos y consultas con especialistas. Pulse una tarjeta para filtrar.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AccountsPayableOverview::class,
        ];
    }

    /**
     * Aplica el filtro de la tarjeta pulsada en el encabezado. Solo acepta las
     * claves conocidas: el resto se ignora.
     */
    #[On('accounts-payable-shortcut')]
    public function applyAccountsPayableShortcut(string $key): void
    {
        $pending = [StatusCuentaPorPagar::PendientePorPagar->value, StatusCuentaPorPagar::EnGestion->value];
        $filters = [
            'payment_status' => ['values' => []],
            'service_type' => ['values' => []],
            'paid_this_month' => ['isActive' => false],
            'paid_without_receipt' => ['isActive' => false],
        ];

        if ($key === 'pending') {
            $filters['payment_status']['values'] = $pending;
        } elseif ($key === 'paid_month') {
            $filters['paid_this_month']['isActive'] = true;
        } elseif ($key === 'paid_without_receipt') {
            $filters['paid_without_receipt']['isActive'] = true;
        } elseif (str_starts_with($key, 'type:') && isset(AccountsPayableServiceDisplay::TYPES[substr($key, 5)])) {
            $filters['payment_status']['values'] = $pending;
            $filters['service_type']['values'] = [substr($key, 5)];
        } elseif ($key !== 'all') {
            return;
        }

        $this->tableFilters = array_replace((array) ($this->tableFilters ?? []), $filters);
        $this->getTableFiltersForm()->fill($this->tableFilters);
        $this->resetPage();
        $this->flushCachedTableRecords();
    }
}
