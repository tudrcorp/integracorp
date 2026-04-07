<?php

namespace App\Filament\Administration\Resources\AnnualCollections\Tables;

use App\Http\Controllers\AnnualCollectionController;
use App\Http\Controllers\CollectionController;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\AnnualCollection;
use App\Models\Collection;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class AnnualCollectionsTable
{
    public static function configure(Table $table): Table
    {
        $monthColumns = [
            'month_1' => 'Enero',
            'month_2' => 'Febrero',
            'month_3' => 'Marzo',
            'month_4' => 'Abril',
            'month_5' => 'Mayo',
            'month_6' => 'Junio',
            'month_7' => 'Julio',
            'month_8' => 'Agosto',
            'month_9' => 'Septiembre',
            'month_10' => 'Octubre',
            'month_11' => 'Noviembre',
            'month_12' => 'Diciembre',
        ];

        return $table
            ->heading('Cobranza anual')
            ->description(new HtmlString(
                '<p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Registro de cobranza por meses del año</p>'.
                '<div class="inline-flex items-center gap-2 rounded-full bg-success-500/15 dark:bg-success-500/25 px-3 py-1.5 text-sm font-medium text-success-700 dark:text-success-400 border-b-2 border-success-600 dark:border-success-500 shadow-sm">'.
                '<svg class="size-4 shrink-0 text-success-600 dark:text-success-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>'.
                '<span>Las columnas con check en verde indican los meses en que el cliente debe pagar su afiliación.</span>'.
                '</div>'
            ))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('sale_id')
                    ->label('ID Venta')
                    ->sortable()
                    ->numeric()
                    ->searchable()
                    ->badge()
                    ->alignCenter()
                    ->color('primary')
                    ->weight('bold')
                    ->extraCellAttributes(fn (): array => ['class' => 'bg-primary-500/10 dark:bg-primary-500/20 border-b-2 border-primary-600 dark:border-primary-400']),
                TextColumn::make('include_date')
                    ->label('Fecha inclusión')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-s-calendar-days'),
                TextColumn::make('owner_code')
                    ->label('Cód. propietario')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('code_agency')
                    ->label('Cód. agencia')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-s-building-library'),
                TextColumn::make('agent.name')
                    ->label('Agente')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-m-user'),
                TextColumn::make('collection_invoice_number')
                    ->label('Nro. factura')
                    ->sortable()
                    ->alignCenter()
                    ->searchable()
                    ->icon('heroicon-s-document-text')
                    ->badge()
                    ->color('success')
                    ->weight('bold')
                    ->extraCellAttributes(fn (): array => ['class' => 'bg-success-500/10 dark:bg-success-500/20 border-b-2 border-success-600 dark:border-success-400']),
                TextColumn::make('quote_number')
                    ->label('Nro. cotización')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-m-tag'),
                TextColumn::make('affiliation_code')
                    ->label('Cód. afiliación')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('affiliate_full_name')
                    ->label('Afiliado')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('affiliate_contact')
                    ->label('Contacto')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('affiliate_ci_rif')
                    ->label('C.I./R.I.F.')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('affiliate_phone')
                    ->label('Teléfono')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-m-phone'),
                TextColumn::make('affiliate_email')
                    ->label('Correo')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-m-envelope')
                    ->limit(30),
                TextColumn::make('affiliate_status')
                    ->label('Est. afiliación')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ACTIVA' => 'success',
                        'INACTIVA' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('coverage.price')
                    ->label('Cobertura (US$)')
                    ->sortable()
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' US$'),
                TextColumn::make('service')
                    ->label('Servicio')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('persons')
                    ->label('Población')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'AFILIACION INDIVIDUAL' => 'primary',
                        'AFILIACION CORPORATIVA' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('expiration_date')
                    ->label('Vencimiento')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PAGADO' => 'success',
                        'POR PAGAR' => 'warning',
                        'CANCELADO' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('remaining_days')
                    ->label('Días restantes')
                    ->sortable()
                    ->searchable()
                    ->numeric()
                    ->badge()
                    ->color(fn (AnnualCollection $record): string => match ($record->remaining_days) {
                        $record->remaining_days <= 0 => 'danger',
                        $record->remaining_days <= 30 => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                ...array_map(
                    function (string $column, string $label): IconColumn {
                        return IconColumn::make($column)
                            ->label($label)
                            ->boolean()
                            ->trueIcon(Heroicon::OutlinedXCircle)
                            ->trueColor('danger')
                            ->falseIcon(Heroicon::OutlinedCheckCircle)
                            ->falseColor('success')
                            ->alignCenter()
                            ->extraCellAttributes(fn ($record): array => $record->{$column}
                                ? ['class' => 'bg-danger-500/15 dark:bg-danger-500/25 border-b-2 border-danger-600 dark:border-danger-400 cursor-pointer']
                                : ['class' => 'bg-success-500/15 dark:bg-success-500/25 border-b-2 border-success-600 dark:border-success-400'])
                            ->action(
                                Action::make('edit_next_payment_'.$column)
                                    ->visible(fn (AnnualCollection $record): bool => (bool) $record->{$column})
                                    ->modalHeading('Próxima fecha de pago')
                                    ->modalDescription('Edite la fecha y use los enlaces para el aviso de cobro.')
                                    ->modalIcon('heroicon-o-calendar-days')
                                    ->modalWidth(Width::SevenExtraLarge)
                                    ->modalFooterActionsAlignment('center')
                                    ->form([
                                        Hidden::make('_record_id'),
                                        Hidden::make('_source_month_column'),
                                        Section::make('Fecha de pago')
                                            ->description('La fecha se guarda automáticamente al salir del campo. No es necesario pulsar Guardar.')
                                            ->icon('heroicon-o-calendar')
                                            ->schema([
                                                DatePicker::make('next_payment_date')
                                                    ->label('Próxima fecha de pago')
                                                    ->required()
                                                    ->format('d/m/Y')
                                                    ->native(false)
                                                    ->displayFormat('d/m/Y')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function (?string $state, $get): void {
                                                        $recordId = $get('_record_id');
                                                        if (! $state || ! $recordId) {
                                                            return;
                                                        }
                                                        $record = AnnualCollection::find($recordId);
                                                        if (! $record) {
                                                            return;
                                                        }
                                                        $collection = $record->collections()->first();
                                                        if (! $collection) {
                                                            return;
                                                        }
                                                        $date = self::parseDateToDmy($state);
                                                        $collection->update([
                                                            'next_payment_date' => $date,
                                                            'filter_next_payment_date' => self::parseDateToYmd($state),
                                                        ]);
                                                        $sourceColumn = $get('_source_month_column');
                                                        if ($sourceColumn) {
                                                            $monthNumber = AnnualCollectionController::extractMonth($date);
                                                            $newColumn = 'month_'.$monthNumber;
                                                            if ($newColumn !== $sourceColumn) {
                                                                $record->update([
                                                                    $sourceColumn => false,
                                                                    $newColumn => true,
                                                                ]);
                                                            }
                                                        }
                                                        Notification::make()
                                                            ->title('Fecha actualizada')
                                                            ->body('Próxima fecha de pago guardada. Puede regenerar o descargar el PDF.')
                                                            ->success()
                                                            ->send();
                                                    }),
                                            ])
                                            ->columns(1),
                                        Section::make('Aviso de cobro')
                                            ->description('Generar o regenerar el PDF del aviso de cobro con la fecha actual.')
                                            ->icon('heroicon-o-document-arrow-down')
                                            ->schema([
                                                Placeholder::make('aviso_actions')
                                                    ->label('')
                                                    ->content(function ($get): HtmlString {
                                                        $record = AnnualCollection::find($get('_record_id'));
                                                        $collection = $record?->collections()->first();

                                                        return new HtmlString(view(
                                                            'filament.administration.annual-collections.aviso-cobro-actions',
                                                            ['collection' => $collection],
                                                        )->render());
                                                    }),
                                            ])
                                            ->columns(1)
                                            ->collapsible(),
                                    ])
                                    ->fillForm(fn (AnnualCollection $record): array => [
                                        '_record_id' => $record->getKey(),
                                        '_source_month_column' => $column,
                                        'next_payment_date' => $record->collections()->first()?->next_payment_date
                                            ? self::parseDateToYmd($record->collections()->first()->next_payment_date)
                                            : null,
                                    ])
                                    ->action(fn () => null)
                                    ->modalSubmitActionLabel('Cerrar'),
                            );
                    },
                    array_keys($monthColumns),
                    array_values($monthColumns)
                ),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function runRegeneratePdf(Collection $record): bool
    {
        try {
            if ($record->type === 'AFILIACION INDIVIDUAL') {
                $address = Affiliation::where('code', $record->affiliation_code)->first();
                $array_data = [
                    'invoice_number' => $record->collection_invoice_number,
                    'emission_date' => $record->next_payment_date,
                    'full_name_ti' => $record->affiliate_full_name,
                    'ci_rif_ti' => $record->affiliate_ci_rif,
                    'address_ti' => $address?->adress_ti ?? '',
                    'phone_ti' => $record->affiliate_phone,
                    'email_ti' => $record->affiliate_email,
                    'total_amount' => $record->total_amount,
                    'plan' => $record->plan?->description,
                    'coverage' => $record->coverage?->price ?? null,
                    'frequency' => $record->payment_frequency,
                ];

                return CollectionController::regenerateAvisoDeCobro($array_data);
            }
            if ($record->type === 'AFILIACION CORPORATIVA') {
                $address = AffiliationCorporate::where('code', $record->affiliation_code)->first();
                $planes = AffiliationCorporate::where('code', $record->affiliation_code)->with('affiliationCorporatePlans')->first()?->toArray();
                $array_data = [
                    'invoice_number' => $record->collection_invoice_number,
                    'emission_date' => $record->next_payment_date,
                    'full_name_ti' => $record->affiliate_full_name,
                    'ci_rif_ti' => $record->affiliate_ci_rif,
                    'address_ti' => $address?->adress_ti ?? '',
                    'phone_ti' => $record->affiliate_phone,
                    'email_ti' => $record->affiliate_email,
                    'total_amount' => $record->total_amount,
                    'plan' => $planes['affiliation_corporate_plans'] ?? [],
                    'coverage' => $record->coverage?->price ?? null,
                    'frequency' => $record->payment_frequency,
                ];

                return CollectionController::regenerateAvisoDeCobroCorporate($array_data);
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    private static function parseDateToYmd(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            if (str_contains($value, '/')) {
                return Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function parseDateToDmy(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            if (str_contains($value, '/')) {
                return Carbon::createFromFormat('d/m/Y', $value)->format('d/m/Y');
            }

            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return null;
        }
    }
}
