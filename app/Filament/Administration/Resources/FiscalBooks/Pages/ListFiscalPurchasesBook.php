<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\FiscalBooks\Pages;

use App\Filament\Administration\Resources\FiscalBooks\FiscalPurchasesBookResource;
use App\Support\Administration\FiscalBooks\FiscalAmount;
use App\Support\Administration\FiscalBooks\FiscalPurchasesPayableImport;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ListFiscalPurchasesBook extends ListFiscalBookEntries
{
    protected static string $resource = FiscalPurchasesBookResource::class;

    /**
     * @var list<array<string, mixed>>|null
     */
    private ?array $pendingPayables = null;

    protected static function bookResource(): string
    {
        return FiscalPurchasesBookResource::class;
    }

    protected function sourceActions(): array
    {
        return [
            Action::make('importPayables')
                ->label('Importar de cuentas por pagar')
                ->icon(Heroicon::OutlinedArrowDownOnSquareStack)
                ->color('gray')
                ->visible(fn (): bool => ! $this->isClosed())
                ->modalHeading('Importar facturas de cuentas por pagar')
                ->modalDescription('Facturas de proveedores registradas en Operaciones que aún no están en el libro. Están en dólares: indique la tasa BCV de cada fecha de factura para pasarlas a bolívares. Entran como exentas; si alguna trae IVA, edítela después.')
                ->modalWidth('4xl')
                ->modalSubmitActionLabel('Importar al libro')
                ->fillForm(fn (): array => [
                    'payables' => [],
                    'rates' => array_map(fn (string $date): array => ['date' => $date, 'rate' => null], $this->pendingDates()),
                ])
                ->schema(fn (): array => $this->importSchema())
                ->action(function (array $data): void {
                    $rates = [];

                    foreach ((array) ($data['rates'] ?? []) as $row) {
                        if (filled($row['date'] ?? null) && filled($row['rate'] ?? null)) {
                            $rates[(string) $row['date']] = $row['rate'];
                        }
                    }

                    try {
                        $count = FiscalPurchasesPayableImport::import($this->periodDate(), (array) ($data['payables'] ?? []), $rates, Auth::user());
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()->title('No se importó')->body($exception->getMessage())->danger()->send();

                        throw new Halt;
                    }

                    $this->pendingPayables = null;
                    Notification::make()->title($count === 1 ? '1 factura importada' : $count.' facturas importadas')->success()->send();
                }),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function importSchema(): array
    {
        $pending = $this->pendingPayables();

        if ($pending === []) {
            return [
                Placeholder::make('nothing')
                    ->hiddenLabel()
                    ->content('No hay facturas de cuentas por pagar pendientes de pasar al libro hasta '.mb_strtolower(\App\Support\Administration\FiscalBooks\FiscalBookPeriods::label($this->periodDate())).'.'),
            ];
        }

        $options = [];
        $descriptions = [];

        foreach ($pending as $row) {
            $options[$row['id']] = 'N° '.$row['number'].' · '.$row['supplier'];
            $descriptions[$row['id']] = CarbonImmutable::parse($row['date'])->format('d/m/Y')
                .' · '.($row['rif'] ?? 'sin RIF')
                .' · '.($row['currency'] === 'USD' ? 'US$ ' : 'Bs. ').FiscalAmount::format($row['amount']);
        }

        return [
            CheckboxList::make('payables')
                ->label('Facturas ('.count($pending).' pendientes)')
                ->options($options)
                ->descriptions($descriptions)
                ->searchable()
                ->bulkToggleable()
                ->required()
                ->columns(2)
                ->validationMessages(['required' => 'Marque al menos una factura.']),
            Repeater::make('rates')
                ->label('Tasa BCV por fecha de factura')
                ->helperText('Solo hace falta la tasa de las fechas de las facturas marcadas.')
                ->addable(false)
                ->deletable(false)
                ->reorderable(false)
                ->grid(3)
                ->schema([
                    TextInput::make('date')
                        ->label('Fecha')
                        ->formatStateUsing(fn (?string $state): string => filled($state) ? CarbonImmutable::parse($state)->format('d/m/Y') : '')
                        ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $state) === 1 ? CarbonImmutable::createFromFormat('d/m/Y', $state)->toDateString() : $state)
                        ->readOnly(),
                    TextInput::make('rate')
                        ->label('Tasa (Bs. por US$)')
                        ->numeric()
                        ->minValue(0.0001)
                        ->step('0.0001'),
                ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingPayables(): array
    {
        return $this->pendingPayables ??= FiscalPurchasesPayableImport::pending($this->periodDate());
    }

    /**
     * @return list<string>
     */
    private function pendingDates(): array
    {
        return array_values(array_unique(array_map(
            fn (array $row): string => $row['date'],
            array_filter($this->pendingPayables(), fn (array $row): bool => $row['currency'] === 'USD'),
        )));
    }
}
