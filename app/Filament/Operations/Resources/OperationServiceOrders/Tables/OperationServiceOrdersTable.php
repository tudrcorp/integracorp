<?php

namespace App\Filament\Operations\Resources\OperationServiceOrders\Tables;

use App\Http\Controllers\ApiBcvController;
use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderItem;
use App\Models\OperationServiceOrderQuote;
use App\Models\Supplier;
use App\Services\OperationServiceOrderMedicationQuotePdfService;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\OperationServiceOrderCoordinationSync;
use App\Support\Operations\OperationServiceOrderValidity;
use App\Support\Telemedicine\TelemedicinePriorityFilamentBadge;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use ZipArchive;

class OperationServiceOrdersTable
{
    private const IOS_SECTION_CLASS = 'fi-helpdesk-ios-section';

    private const IOS_SUCCESS_BTN = 'aviso-btn-ios-success shrink-0 inline-flex min-w-[7.5rem] items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_GRAY_BTN = 'ticket-btn-ios-gray shrink-0 inline-flex min-w-[7.5rem] items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private static function patientNameForOrder(OperationServiceOrder $record): string
    {
        $coordination = $record->operationCoordinationService;

        if ($coordination === null) {
            return '—';
        }

        if (filled($coordination->patient)) {
            return (string) $coordination->patient;
        }

        if (filled($coordination->telemedicinePatient?->full_name)) {
            return (string) $coordination->telemedicinePatient->full_name;
        }

        if (filled($coordination->telemedicineCase?->patient_name)) {
            return (string) $coordination->telemedicineCase->patient_name;
        }

        return '—';
    }

    private static function supplierLabel(OperationServiceOrder $record): string
    {
        if (filled($record->supplier?->name)) {
            return (string) $record->supplier->name;
        }

        if (filled($record->supplier_external)) {
            return (string) $record->supplier_external;
        }

        if (filled($record->telemedicineSupplier?->name)) {
            return (string) $record->telemedicineSupplier->name;
        }

        return '—';
    }

    private static function statusIcon(?string $status): string
    {
        return match (mb_strtoupper(trim((string) $status))) {
            'EN GESTION', 'EN GESTIÓN' => 'heroicon-m-arrow-path',
            'FINALIZADO' => 'heroicon-m-check-circle',
            'CADUCADA' => 'heroicon-m-exclamation-triangle',
            'PENDIENTE' => 'heroicon-m-clock',
            'CANCELADO' => 'heroicon-m-x-circle',
            default => 'heroicon-m-information-circle',
        };
    }

    private static function serviceTypeIcon(?string $serviceType): string
    {
        $normalized = mb_strtoupper(trim((string) $serviceType));

        return match (true) {
            str_contains($normalized, 'MEDICAMENTO') => 'heroicon-m-beaker',
            str_contains($normalized, 'LABORATORIO') => 'heroicon-m-clipboard-document-list',
            str_contains($normalized, 'IMAGEN') => 'heroicon-m-photo',
            str_contains($normalized, 'ESPECIAL') => 'heroicon-m-user-group',
            default => 'heroicon-m-briefcase',
        };
    }

    /** Valor de referencia de la API BCV para el formulario (una petición por request). */
    private static function referenciaTasaBcvDesdeApi(): ?float
    {
        static $resolved = false;
        static $tasa = null;

        if (! $resolved) {
            $resolved = true;
            $tasa = ApiBcvController::getTasaBcv();
        }

        return $tasa;
    }

    /** @return array<string, string> */
    private static function paymentMethodOptions(): array
    {
        return [
            'ZELLE' => 'ZELLE',
            'TRANSFERENCIA US$' => 'TRANSFERENCIA(US$)',
            'EFECTIVO US$' => 'EFECTIVO US$',
            'MULTIPLE' => 'MULTIPLE',
            'PAGO MOVIL VES' => 'PAGO MOVIL(VES)',
            'TRANSFERENCIA VES' => 'TRANSFERENCIA(VES)',
        ];
    }

    /**
     * Misma lógica que exige el modal al guardar: método de pago, tasa BCV > 0 y al menos un monto.
     */
    private static function hasRegisteredPaymentData(OperationServiceOrder $record): bool
    {
        if (! filled($record->payment_method)) {
            return false;
        }

        $tasa = (float) ($record->tasa_bcv ?? 0);
        if ($tasa <= 0) {
            return false;
        }

        $usd = $record->total_amount_usd;
        $ves = $record->total_amount_ves;
        $hasUsd = $usd !== null && $usd !== '' && is_numeric($usd);
        $hasVes = $ves !== null && $ves !== '' && is_numeric($ves);

        return $hasUsd || $hasVes;
    }

    /**
     * @return Collection<int, OperationServiceOrderItem>
     */
    private static function medicationItems(OperationServiceOrder $record): Collection
    {
        return $record->operationServiceOrderItems()
            ->where('category', 'MEDICAMENTOS')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    private static function medicationItemOptions(OperationServiceOrder $record): array
    {
        return self::medicationItems($record)
            ->mapWithKeys(fn (OperationServiceOrderItem $item): array => [
                $item->id => $item->item_name.' (x'.max(1, (int) $item->quantity).')',
            ])
            ->all();
    }

    private static function nextQuoteNumber(OperationServiceOrder $record): string
    {
        $next = ((int) ($record->operationServiceOrderQuotes()->count())) + 1;

        return 'COT-'.$record->order_number.'-'.str_pad((string) $next, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<int, mixed>  $itemIds
     * @return array{items: array<int, array<string, mixed>>, total_usd: float}
     */
    private static function quoteLinesFromItems(OperationServiceOrder $record, array $itemIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $itemIds)));

        $items = $record->operationServiceOrderItems()
            ->whereIn('id', $ids)
            ->where('category', 'MEDICAMENTOS')
            ->orderBy('id')
            ->get();

        $lines = [];
        $total = 0.0;

        foreach ($items as $item) {
            $quantity = max(1, (int) ($item->quantity ?? 1));
            $unitAmountUsd = (float) ($item->amount ?? 0);
            $lineTotalUsd = round($unitAmountUsd * $quantity, 4);
            $total += $lineTotalUsd;

            $lines[] = [
                'item_id' => $item->id,
                'item_name' => $item->item_name,
                'quantity' => $quantity,
                'unit_amount_usd' => $unitAmountUsd,
                'line_total_usd' => $lineTotalUsd,
            ];
        }

        return [
            'items' => $lines,
            'total_usd' => round($total, 4),
        ];
    }

    /**
     * @param  array<string, mixed>  $quoteData
     */
    private static function persistMedicationQuote(OperationServiceOrder $record, array $quoteData): void
    {
        $bcvRate = (float) ($quoteData['bcv_rate'] ?? 0);
        $supplierId = isset($quoteData['supplier_id']) && filled($quoteData['supplier_id'])
            ? (int) $quoteData['supplier_id']
            : null;
        $supplierExternal = filled($quoteData['supplier_external'] ?? null)
            ? (string) $quoteData['supplier_external']
            : null;

        $lines = self::quoteLinesFromItems($record, is_array($quoteData['item_ids'] ?? null) ? $quoteData['item_ids'] : []);
        if ($lines['items'] === []) {
            return;
        }

        $totalUsd = (float) $lines['total_usd'];
        $totalVes = round($totalUsd * $bcvRate, 4);
        $quoteNumber = self::nextQuoteNumber($record);

        $supplierName = $supplierId !== null
            ? Supplier::query()->whereKey($supplierId)->value('name')
            : null;
        $supplierName = $supplierName ?: ($supplierExternal ?: 'Proveedor no especificado');

        $quote = OperationServiceOrderQuote::query()->create([
            'operation_service_order_id' => $record->id,
            'quote_number' => $quoteNumber,
            'supplier_id' => $supplierId,
            'supplier_external' => $supplierExternal,
            'bcv_rate' => $bcvRate,
            'total_amount_usd' => $totalUsd,
            'total_amount_ves' => $totalVes,
            'items_payload' => $lines['items'],
            'created_by' => Auth::user()?->name,
            'updated_by' => Auth::user()?->name,
        ]);

        $pdfContent = OperationServiceOrderMedicationQuotePdfService::make(
            $record,
            [
                'quote_number' => $quoteNumber,
                'supplier_name' => $supplierName,
                'bcv_rate' => $bcvRate,
                'total_amount_usd' => $totalUsd,
                'total_amount_ves' => $totalVes,
            ],
            $lines['items']
        )->output();

        $safeOrder = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $record->order_number) ?: (string) $record->id;
        $safeQuote = preg_replace('/[^a-zA-Z0-9_-]/', '_', $quoteNumber) ?: (string) $quote->id;
        $relativePath = 'operation-service-orders/quotes/'.$safeOrder.'/'.$safeQuote.'.pdf';
        Storage::disk('public')->put($relativePath, $pdfContent);

        $quote->quote_pdf_path = $relativePath;
        $quote->save();
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Órdenes de servicio')
            ->description('Órdenes generadas desde coordinación. Vigencia de 10 días desde la aprobación; vencidas pasan a CADUCADA. La franja lateral refleja la prioridad salvo en órdenes cerradas.')
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder('Buscar por orden, paciente, caso, proveedor o descripción…')
            ->persistSearchInSession()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Sin órdenes de servicio')
            ->emptyStateDescription('Cuando se genere una orden desde coordinación aparecerá aquí. Use la búsqueda y los filtros para localizar registros.')
            ->modifyQueryUsing(function (Builder $query): Builder {
                OperationServiceOrderValidity::expireEligibleOrders('system');

                OperationsSupplierScope::applyServiceOrderListScope($query);

                return $query
                    ->with([
                        'telemedicinePriority',
                        'supplier',
                        'telemedicineSupplier',
                        'approvedOperationQuote',
                        'operationCoordinationService.telemedicineCase',
                        'operationCoordinationService.telemedicinePatient',
                    ])
                    ->withCount('operationServiceOrderQuotes');
            })
            ->columns([
                TextColumn::make('order_number')
                    ->label('Nº orden')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->icon('heroicon-m-hashtag')
                    ->copyable()
                    ->copyMessage('Número copiado')
                    ->description(fn (OperationServiceOrder $record): string => self::patientNameForOrder($record))
                    ->tooltip(fn (OperationServiceOrder $record): string => self::patientNameForOrder($record)),
                TextColumn::make('operationCoordinationService.telemedicineCase.code')
                    ->label('Nº caso')
                    ->badge()
                    ->color('primary')
                    ->icon('healthicons-f-health-literacy')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? mb_strtoupper((string) $state) : '—'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->icon(fn (?string $state): string => self::statusIcon($state))
                    ->color(fn (?string $state): string => match (mb_strtoupper(trim((string) $state))) {
                        'EN GESTION', 'EN GESTIÓN' => 'primary',
                        'FINALIZADO' => 'success',
                        'CADUCADA' => 'danger',
                        'PENDIENTE' => 'warning',
                        'CANCELADA' => 'danger',
                        'CANCELADO' => 'gray',
                        default => 'gray',
                    })
                    ->description(fn (OperationServiceOrder $record): ?string => OperationServiceOrderValidity::vigenciaLabel($record) !== '—'
                        ? OperationServiceOrderValidity::vigenciaLabel($record)
                        : null),
                TextColumn::make('telemedicinePriority.name')
                    ->label('Prioridad')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->color(fn (?string $state): string => TelemedicinePriorityFilamentBadge::color($state ?? ''))
                    ->icon(fn (?string $state): string => TelemedicinePriorityFilamentBadge::icon($state ?? '')),
                TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->limit(28)
                    ->formatStateUsing(fn (?string $state, OperationServiceOrder $record): string => self::supplierLabel($record))
                    ->description(fn (OperationServiceOrder $record): ?string => filled($record->supplier?->name) && filled($record->supplier_external)
                        ? 'No convenido: '.$record->supplier_external
                        : null)
                    ->tooltip(fn (OperationServiceOrder $record): string => self::supplierLabel($record)),
                TextColumn::make('service_type')
                    ->label('Tipo de servicio')
                    ->badge()
                    ->color('gray')
                    ->icon(fn (?string $state): string => self::serviceTypeIcon($state))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->wrap()
                    ->lineClamp(2)
                    ->placeholder('—')
                    ->tooltip(fn (OperationServiceOrder $record): ?string => filled($record->description) ? (string) $record->description : null)
                    ->toggleable(),
                TextColumn::make('approvedOperationQuote.id')
                    ->label('Código cotización')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(
                        fn (mixed $state): string => filled($state)
                            ? 'COT-'.str_pad((string) ((int) $state), 6, '0', STR_PAD_LEFT)
                            : '—'
                    )
                    ->tooltip('Se completa cuando la orden nace desde una cotización aprobada.')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('managed_by')
                    ->label('Gestionado por')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->searchable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament ?? [], true))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('supplier_external')
                    ->label('Proveedor No Convenido')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('currency')
                    ->label('Moneda')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tasa_bcv')
                    ->label('Tasa BCV')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state): string => $state !== null && $state !== '' ? (string) $state : '—'),
                TextColumn::make('total_amount_usd')
                    ->label('Total US$')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state): string => $state !== null && $state !== ''
                        ? 'US$ '.number_format((float) $state, 2, ',', '.')
                        : '—'),
                TextColumn::make('total_amount_ves')
                    ->label('Total Bs.')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state): string => $state !== null && $state !== ''
                        ? 'Bs. '.number_format((float) $state, 2, ',', '.')
                        : '—'),
                TextColumn::make('payment_method')
                    ->label('Método de pago')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn (?string $state): string => $state ? (self::paymentMethodOptions()[$state] ?? $state) : '—'),
                TextColumn::make('status_payment')
                    ->label('Estado de pago')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'PAGADO' => 'success',
                        'PENDIENTE' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('service_order_pdf_path')
                    ->label('PDF orden')
                    ->badge()
                    ->color(fn (?string $state): string => filled($state) ? 'success' : 'gray')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'Generado' : 'Pendiente')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('associated_quote_pdf_path')
                    ->label('PDF cotización')
                    ->badge()
                    ->color(fn (?string $state): string => filled($state) ? 'success' : 'gray')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'Generado' : 'No aplica')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('operation_service_order_quotes_count')
                    ->label('Cotizaciones')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-document-currency-dollar')
                    ->sortable()
                    ->formatStateUsing(fn (int $state): string => $state === 1 ? '1 cotización' : $state.' cotizaciones')
                    ->toggleable(),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->description(fn (OperationServiceOrder $record): string => $record->created_at->diffForHumans())
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days'),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordClasses(fn (OperationServiceOrder $record): array => self::recordRowClasses($record))
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'PENDIENTE' => 'Pendiente',
                        'EN GESTION' => 'En gestión',
                        'FINALIZADO' => 'Finalizado',
                        'CADUCADA' => 'Caducada',
                        'CANCELADO' => 'Cancelado',
                    ])
                    ->multiple(),
                SelectFilter::make('service_type')
                    ->label('Tipo de servicio')
                    ->options(fn (): array => OperationServiceOrder::query()
                        ->whereNotNull('service_type')
                        ->where('service_type', '!=', '')
                        ->distinct()
                        ->orderBy('service_type')
                        ->pluck('service_type', 'service_type')
                        ->all())
                    ->searchable()
                    ->multiple(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('downloadServiceOrderPdf')
                        ->label('PDF orden')
                        ->icon('heroicon-m-document-arrow-down')
                        ->color('success')
                        ->url(
                            fn (OperationServiceOrder $record): ?string => filled($record->service_order_pdf_path)
                                ? URL::to(Storage::url((string) $record->service_order_pdf_path))
                                : null,
                            shouldOpenInNewTab: true
                        )
                        ->hidden(fn (OperationServiceOrder $record): bool => ! filled($record->service_order_pdf_path)),
                    Action::make('downloadAssociatedQuotePdf')
                        ->label('PDF cotización')
                        ->icon('heroicon-m-document-text')
                        ->color('info')
                        ->url(
                            fn (OperationServiceOrder $record): ?string => filled($record->associated_quote_pdf_path)
                                ? URL::to(Storage::url((string) $record->associated_quote_pdf_path))
                                : null,
                            shouldOpenInNewTab: true
                        )
                        ->hidden(fn (OperationServiceOrder $record): bool => ! filled($record->associated_quote_pdf_path)),
                    Action::make('manageMedicationQuotes')
                        ->label('Cotizar medicamentos')
                        ->icon('heroicon-m-document-currency-dollar')
                        ->color('warning')
                        ->slideOver()
                        ->modalWidth(Width::FourExtraLarge)
                        ->modalHeading('Cotizaciones de medicamentos por proveedor')
                        ->modalDescription('Puedes generar una sola cotización con todos los medicamentos o dividirlos en varias cotizaciones por proveedor. Cada cotización se guarda con su PDF para consulta del analista.')
                        ->modalSubmitActionLabel('Generar cotización(es)')
                        ->fillForm(fn (OperationServiceOrder $record): array => [
                            'bcv_rate' => self::referenciaTasaBcvDesdeApi(),
                            'single_quote_item_ids' => array_keys(self::medicationItemOptions($record)),
                            'split_by_supplier' => false,
                            'quote_groups' => [],
                        ])
                        ->form([
                            Section::make('Configuración')
                                ->schema([
                                    TextInput::make('bcv_rate')
                                        ->label('Tasa BCV para cotizaciones')
                                        ->prefix('Bs.')
                                        ->numeric()
                                        ->required()
                                        ->minValue(0.0001)
                                        ->helperText('Se aplicará esta tasa para calcular bolívares en cada cotización.'),
                                    Toggle::make('split_by_supplier')
                                        ->label('Dividir cotizaciones por proveedor')
                                        ->helperText('Activo: crea 2 o más cotizaciones. Inactivo: crea una sola cotización con todos los ítems seleccionados.')
                                        ->live(),
                                ])
                                ->columns(2),
                            Section::make('Cotización única')
                                ->visible(fn (Get $get): bool => ! ((bool) $get('split_by_supplier')))
                                ->schema([
                                    Select::make('single_supplier_id')
                                        ->label('Proveedor TDG')
                                        ->options(Supplier::query()->orderBy('name', 'asc')->pluck('name', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->native(false),
                                    TextInput::make('single_supplier_external')
                                        ->label('Proveedor No Convenido')
                                        ->maxLength(255),
                                    CheckboxList::make('single_quote_item_ids')
                                        ->label('Medicamentos a cotizar')
                                        ->options(fn (OperationServiceOrder $record): array => self::medicationItemOptions($record))
                                        ->searchable()
                                        ->columns(1)
                                        ->bulkToggleable()
                                        ->required()
                                        ->columnSpanFull(),
                                ])
                                ->columns(2),
                            Section::make('Cotizaciones múltiples por proveedor')
                                ->visible(fn (Get $get): bool => (bool) $get('split_by_supplier'))
                                ->schema([
                                    Repeater::make('quote_groups')
                                        ->label('Grupos de cotización')
                                        ->defaultItems(2)
                                        ->minItems(2)
                                        ->schema([
                                            Select::make('supplier_id')
                                                ->label('Proveedor TDG')
                                                ->options(Supplier::query()->orderBy('name', 'asc')->pluck('name', 'id'))
                                                ->searchable()
                                                ->preload()
                                                ->native(false),
                                            TextInput::make('supplier_external')
                                                ->label('Proveedor No Convenido')
                                                ->maxLength(255),
                                            CheckboxList::make('item_ids')
                                                ->label('Medicamentos para este proveedor')
                                                ->options(fn (OperationServiceOrder $record): array => self::medicationItemOptions($record))
                                                ->searchable()
                                                ->columns(1)
                                                ->required()
                                                ->columnSpanFull(),
                                        ])
                                        ->columns(2)
                                        ->columnSpanFull(),
                                ]),
                        ])
                        ->action(function (OperationServiceOrder $record, array $data): void {
                            $medicationOptions = self::medicationItemOptions($record);
                            if ($medicationOptions === []) {
                                Notification::make()
                                    ->title('Sin medicamentos')
                                    ->body('Esta orden no tiene ítems de categoría MEDICAMENTOS para cotizar.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $bcvRate = (float) ($data['bcv_rate'] ?? 0);
                            if ($bcvRate <= 0) {
                                Notification::make()
                                    ->title('Tasa inválida')
                                    ->body('Debe indicar una tasa BCV mayor que cero.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            if (! ((bool) ($data['split_by_supplier'] ?? false))) {
                                $itemIds = array_values(array_unique(array_map('intval', (array) ($data['single_quote_item_ids'] ?? []))));
                                if ($itemIds === []) {
                                    Notification::make()
                                        ->title('Ítems requeridos')
                                        ->body('Selecciona al menos un medicamento para la cotización.')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                self::persistMedicationQuote($record, [
                                    'supplier_id' => $data['single_supplier_id'] ?? null,
                                    'supplier_external' => $data['single_supplier_external'] ?? null,
                                    'item_ids' => $itemIds,
                                    'bcv_rate' => $bcvRate,
                                ]);

                                Notification::make()
                                    ->title('Cotización generada')
                                    ->body('Se generó y almacenó la cotización con su PDF.')
                                    ->success()
                                    ->send();

                                return;
                            }

                            $groups = is_array($data['quote_groups'] ?? null) ? $data['quote_groups'] : [];
                            if ($groups === []) {
                                Notification::make()
                                    ->title('Grupos requeridos')
                                    ->body('Agrega al menos dos grupos para cotización por proveedor.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $availableItemIds = array_map('intval', array_keys($medicationOptions));
                            $assignmentCounter = [];
                            $preparedGroups = [];

                            foreach ($groups as $group) {
                                $itemIds = array_values(array_unique(array_map('intval', (array) ($group['item_ids'] ?? []))));
                                if ($itemIds === []) {
                                    continue;
                                }

                                foreach ($itemIds as $itemId) {
                                    if (! in_array($itemId, $availableItemIds, true)) {
                                        continue;
                                    }

                                    $assignmentCounter[$itemId] = ($assignmentCounter[$itemId] ?? 0) + 1;
                                }

                                $preparedGroups[] = [
                                    'supplier_id' => $group['supplier_id'] ?? null,
                                    'supplier_external' => $group['supplier_external'] ?? null,
                                    'item_ids' => $itemIds,
                                    'bcv_rate' => $bcvRate,
                                ];
                            }

                            $assignedItems = array_keys($assignmentCounter);
                            if ($assignedItems === []) {
                                Notification::make()
                                    ->title('Ítems requeridos')
                                    ->body('Cada cotización debe tener al menos un medicamento asignado.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $duplicatedItems = collect($assignmentCounter)
                                ->filter(fn (int $times): bool => $times > 1)
                                ->keys()
                                ->all();

                            if ($duplicatedItems !== []) {
                                Notification::make()
                                    ->title('Ítems duplicados')
                                    ->body('Un mismo medicamento no puede estar en dos cotizaciones distintas. Ajusta la distribución por proveedor.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $missingItems = array_values(array_diff($availableItemIds, array_map('intval', $assignedItems)));
                            if ($missingItems !== []) {
                                Notification::make()
                                    ->title('Distribución incompleta')
                                    ->body('Debes asignar todos los medicamentos a algún proveedor para cerrar el proceso rápido y sin pendientes.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            foreach ($preparedGroups as $groupData) {
                                self::persistMedicationQuote($record, $groupData);
                            }

                            Notification::make()
                                ->title('Cotizaciones generadas')
                                ->body('Se generaron y almacenaron las cotizaciones por proveedor con sus PDFs.')
                                ->success()
                                ->send();
                        })
                        ->hidden(fn (): bool => true),
                    Action::make('viewMedicationQuotes')
                        ->label('Ver cotizaciones')
                        ->icon('heroicon-m-folder-open')
                        ->color('info')
                        ->modalHeading('Cotizaciones registradas')
                        ->modalDescription('Historial de cotizaciones por proveedor con acceso directo a cada PDF.')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')
                        ->form(fn (OperationServiceOrder $record): array => [
                            Section::make('Documentación disponible')
                                ->schema([
                                    Placeholder::make('quote_documents_preview')
                                        ->label('')
                                        ->content(fn (): HtmlString => self::renderMedicationQuotesPreview($record)),
                                ]),
                        ])
                        ->hidden(fn (): bool => true),
                    Action::make('registerPayment')
                        ->label('Datos de pago')
                        ->icon('heroicon-m-banknotes')
                        ->color('primary')
                        ->slideOver()
                        ->modalWidth(Width::ThreeExtraLarge)
                        ->modalIcon('heroicon-m-banknotes')
                        ->modalHeading('Registrar datos de pago')
                        ->modalDescription('Completa la tasa BCV, los montos y el método de pago para actualizar la orden. Usa el botón «Guardar» al finalizar: los totales en dólares y bolívares se sincronizan según la tasa (si indicas ambos montos, prevalece el total en US$).')
                        ->modalSubmitActionLabel('Guardar datos de pago')
                        ->modalSubmitAction(
                            fn (Action $action): Action => $action
                                ->extraAttributes([
                                    'class' => self::IOS_SUCCESS_BTN,
                                ])
                        )
                        ->modalCancelAction(
                            fn (Action $action): Action => $action
                                ->label('Cancelar')
                                ->extraAttributes([
                                    'class' => self::IOS_GRAY_BTN,
                                ])
                        )
                        ->fillForm(fn (OperationServiceOrder $record): array => [
                            'tasa_bcv' => filled($record->tasa_bcv)
                                ? $record->tasa_bcv
                                : self::referenciaTasaBcvDesdeApi(),
                            'total_amount_usd' => $record->total_amount_usd,
                            'total_amount_ves' => $record->total_amount_ves,
                            'payment_method' => $record->payment_method,
                        ])
                        ->form([
                            Section::make('Información de pago')
                                ->description('Indica la tasa del día y al menos un monto (US$ o Bs.); el otro se calcula al guardar. El método de pago es obligatorio.')
                                ->icon('heroicon-m-currency-dollar')
                                ->schema([
                                    Grid::make(['default' => 1, 'lg' => 2])
                                        ->schema([
                                            TextInput::make('tasa_bcv')
                                                ->label('Tasa BCV')
                                                ->prefix('VES')
                                                ->placeholder('Ej. 36,50')
                                                ->numeric()
                                                ->required()
                                                ->minValue(0.000001)
                                                ->helperText(function (): string {
                                                    $tasa = self::referenciaTasaBcvDesdeApi();

                                                    return $tasa !== null
                                                        ? 'Tipo de cambio oficial o acordado para esta orden. Tasa referencial: '.number_format((float) $tasa, 2, ',', '.').' Bs./US$.'
                                                        : 'La API BCV no está disponible; ingresa la tasa manualmente.';
                                                }),
                                            Select::make('payment_method')
                                                ->label('Método de pago')
                                                ->prefixIcon('heroicon-m-credit-card')
                                                ->options(self::paymentMethodOptions())
                                                ->required()
                                                ->native(false)
                                                ->searchable(),
                                            TextInput::make('total_amount_usd')
                                                ->label('Total en US$')
                                                ->prefix('US$')
                                                ->placeholder('0,00')
                                                ->numeric()
                                                ->helperText('Opcional si ya ingresaste el total en bolívares.'),
                                            TextInput::make('total_amount_ves')
                                                ->label('Total en bolívares')
                                                ->prefix('Bs.')
                                                ->placeholder('0,00')
                                                ->numeric()
                                                ->helperText('Opcional si ya ingresaste el total en US$.'),
                                        ]),
                                ])
                                ->columns(1)
                                ->columnSpanFull()
                                ->extraAttributes([
                                    'class' => self::IOS_SECTION_CLASS,
                                ]),
                        ])
                        ->successNotification(null)
                        ->action(function (OperationServiceOrder $record, array $data): void {
                            $tasa = (float) ($data['tasa_bcv'] ?? 0);
                            if ($tasa <= 0) {
                                Notification::make()
                                    ->title('Tasa inválida')
                                    ->body('La tasa BCV debe ser mayor que cero.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $usdRaw = $data['total_amount_usd'] ?? null;
                            $vesRaw = $data['total_amount_ves'] ?? null;
                            $usd = ($usdRaw !== null && $usdRaw !== '') ? (float) $usdRaw : null;
                            $ves = ($vesRaw !== null && $vesRaw !== '') ? (float) $vesRaw : null;

                            if ($usd === null && $ves === null) {
                                Notification::make()
                                    ->title('Montos requeridos')
                                    ->body('Indica al menos un monto en US$ o en bolívares.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            if ($usd !== null && $ves !== null) {
                                $ves = $usd * $tasa;
                            } elseif ($usd !== null) {
                                $ves = $usd * $tasa;
                            } else {
                                $usd = $ves / $tasa;
                            }

                            $record->update([
                                'tasa_bcv' => $tasa,
                                'total_amount_usd' => round($usd, 4),
                                'total_amount_ves' => round($ves, 4),
                                'payment_method' => (string) $data['payment_method'],
                                'updated_by' => Auth::user()?->name ?? 'sistema',
                                'status_payment' => 'PAGADO',
                            ]);

                            Notification::make()
                                ->title('Datos de pago guardados')
                                ->body('La orden #'.($record->order_number ?: $record->getKey()).' se actualizó correctamente.')
                                ->success()
                                ->send();
                        })
                        ->hidden(fn (OperationServiceOrder $record): bool => $record->status_payment === 'PAGADO'),
                    Action::make('upload_files')
                        ->label('Cargar Soportes')
                        ->icon('heroicon-m-cloud-arrow-up')
                        ->color('warning')
                        // ->button()
                        // ->extraAttributes([
                        //     'x-on:click.stop' => '',
                        //     'class' => 'rounded-full border-b-2 border-warning-600 dark:border-warning-500 bg-warning-500/15 dark:bg-warning-500/25 text-warning-700 dark:text-warning-300 font-semibold shadow-sm hover:bg-warning-500/25 dark:hover:bg-warning-500/35',
                        // ])
                        ->modalHeading('Cargar Soportes')
                        ->modalDescription('Cargue los soportes de la orden de servicio')
                        ->modalSubmitActionLabel('Cargar')
                        ->modalCancelActionLabel('Cancelar')
                        ->modalIcon('heroicon-m-cloud-arrow-up')
                        ->form([
                            FileUpload::make('files')
                                ->label('Soportes')
                                ->disk('public')
                                ->directory('operation-service-orders-files')
                                ->visibility('public')
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                                ->maxSize(2048)
                                ->helperText('Formatos: JPG, PNG, WebP o PDF. Máximo 2 MB.')
                                ->multiple()
                                ->required()
                                ->validationMessages([
                                    'required' => 'El campo es requerido',
                                ]),
                        ])
                        ->action(function (OperationServiceOrder $record, array $data): void {
                            $record->update([
                                'files' => $data['files'],
                                'updated_by' => Auth::user()->name,
                            ]);

                            OperationServiceOrderCoordinationSync::finalizeOrder($record);

                            Notification::make()
                                ->title('¡TAREA COMPLETADA!')
                                ->body('Los soportes se cargaron, la orden quedó finalizada y los ítems de la coordinación se actualizaron.')
                                ->success()
                                ->send();
                        })
                        ->hidden(fn (OperationServiceOrder $record): bool => $record->status === 'FINALIZADO'),
                    Action::make('preview_files')
                        ->label('Vista previa')
                        ->icon('heroicon-m-eye')
                        ->color('success')
                        // ->button()
                        // ->extraAttributes([
                        //     'x-on:click.stop' => '',
                        //     'class' => 'rounded-full border-b-2 border-success-600 dark:border-success-500 bg-success-500/15 dark:bg-success-500/25 text-success-700 dark:text-success-300 font-semibold shadow-sm hover:bg-success-500/25 dark:hover:bg-success-500/35',
                        // ])
                        ->modalHeading('Vista previa de soportes')
                        ->modalDescription('Previsualiza los archivos cargados y descárgalos individualmente.')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')
                        ->modalIcon('heroicon-m-eye')
                        ->form(fn ($record): array => [
                            Section::make('Soportes cargados')
                                ->schema([
                                    Placeholder::make('files_preview')
                                        ->label('')
                                        ->content(fn () => self::renderFilesPreview($record, self::buildDownloadAllUrl($record))),
                                ]),
                        ])
                        ->hidden(fn ($record) => empty($record->files)),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return list<string>
     */
    private static function recordRowClasses(OperationServiceOrder $record): array
    {
        $status = mb_strtoupper(trim((string) ($record->status ?? '')));

        if ($status === OperationServiceOrderValidity::STATUS_EXPIRED) {
            return ['border-l-4 border-red-500 bg-red-50/90 dark:border-red-500 dark:bg-red-950/40'];
        }

        if (in_array($status, ['FINALIZADO', 'CANCELADA', 'CANCELADO'], true)) {
            return ['border-l-4 border-gray-400 bg-gray-100/90 dark:border-gray-500 dark:bg-gray-900/50'];
        }

        return [TelemedicinePriorityFilamentBadge::recordRowClasses($record->telemedicinePriority?->name)];
    }

    private static function renderMedicationQuotesPreview(OperationServiceOrder $record): HtmlString
    {
        $quotes = $record->operationServiceOrderQuotes()
            ->with('supplier:id,name')
            ->latest('id')
            ->get();

        if ($quotes->isEmpty()) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">No hay cotizaciones registradas para esta orden.</p>');
        }

        $rows = $quotes->map(function (OperationServiceOrderQuote $quote): string {
            $supplier = $quote->supplier?->name ?: ($quote->supplier_external ?: 'Proveedor no especificado');
            $pdfUrl = filled($quote->quote_pdf_path) ? URL::to(Storage::url((string) $quote->quote_pdf_path)) : null;

            return '<tr class="border-b border-gray-100 dark:border-white/10">'
                .'<td class="px-3 py-2 font-medium">'.e($quote->quote_number).'</td>'
                .'<td class="px-3 py-2">'.e($supplier).'</td>'
                .'<td class="px-3 py-2 text-right">US$ '.e(number_format((float) $quote->total_amount_usd, 2, ',', '.')).'</td>'
                .'<td class="px-3 py-2 text-right">Bs. '.e(number_format((float) $quote->total_amount_ves, 2, ',', '.')).'</td>'
                .'<td class="px-3 py-2 text-center">'
                .($pdfUrl
                    ? '<a href="'.e($pdfUrl).'" target="_blank" class="inline-flex items-center rounded-full border-b-2 border-primary-600 bg-primary-500/15 px-3 py-1 text-xs font-semibold text-primary-700 dark:border-primary-500 dark:bg-primary-500/25 dark:text-primary-300">Abrir PDF</a>'
                    : '<span class="text-xs text-gray-500 dark:text-gray-400">Sin PDF</span>')
                .'</td>'
                .'</tr>';
        })->implode('');

        return new HtmlString(
            '<div class="overflow-x-auto rounded-xl border border-gray-200/90 dark:border-white/10">'
            .'<table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-white/10">'
            .'<thead class="bg-gray-50/90 dark:bg-white/5"><tr>'
            .'<th class="px-3 py-2 text-left font-semibold">N° cotización</th>'
            .'<th class="px-3 py-2 text-left font-semibold">Proveedor</th>'
            .'<th class="px-3 py-2 text-right font-semibold">Total US$</th>'
            .'<th class="px-3 py-2 text-right font-semibold">Total Bs.</th>'
            .'<th class="px-3 py-2 text-center font-semibold">Documento</th>'
            .'</tr></thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'</table>'
            .'</div>'
        );
    }

    private static function buildDownloadAllUrl($record): ?string
    {
        $files = is_array($record->files) ? $record->files : [];

        if ($files === []) {
            return null;
        }

        $disk = Storage::disk('public');
        $zipFileName = 'os-'.($record->order_number ?: $record->id).'-soportes-'.now()->format('YmdHis').'.zip';
        $zipRelativePath = 'operation-service-orders-files/zips/'.$zipFileName;
        $zipAbsolutePath = $disk->path($zipRelativePath);
        $zipDirectory = dirname($zipAbsolutePath);

        if (! is_dir($zipDirectory)) {
            mkdir($zipDirectory, 0755, true);
        }

        if (file_exists($zipAbsolutePath)) {
            @unlink($zipAbsolutePath);
        }

        $zip = new ZipArchive;

        if ($zip->open($zipAbsolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        foreach ($files as $file) {
            if (! is_string($file) || $file === '' || ! $disk->exists($file)) {
                continue;
            }

            $zip->addFile($disk->path($file), basename($file));
        }

        $zip->close();

        return URL::to(Storage::url($zipRelativePath));
    }

    private static function renderFilesPreview($record, ?string $downloadAllUrl = null): HtmlString
    {
        $files = is_array($record->files) ? $record->files : [];

        if ($files === []) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">No hay soportes cargados.</p>');
        }

        $cards = array_map(static function (string $file): string {
            $url = URL::to(Storage::url($file));
            $name = basename($file);
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            $preview = '<div class="rounded-2xl border border-gray-200/80 dark:border-gray-700 bg-white/70 dark:bg-gray-900/50 p-3 text-sm text-gray-500 dark:text-gray-400">Sin previsualización disponible</div>';

            if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                $preview = '<img src="'.e($url).'" alt="'.e($name).'" class="w-full rounded-2xl border border-gray-200/80 dark:border-gray-700 object-cover max-h-72" loading="lazy">';
            } elseif ($extension === 'pdf') {
                $preview = '<iframe src="'.e($url).'#toolbar=0&navpanes=0" class="w-full h-72 rounded-2xl border border-gray-200/80 dark:border-gray-700 bg-white" title="'.e($name).'"></iframe>';
            }

            return '<div class="rounded-3xl border border-gray-200/70 dark:border-gray-700/70 bg-white/80 dark:bg-gray-900/60 p-4 shadow-sm">'.
                '<div class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200 truncate">'.e($name).'</div>'.
                '<div class="mb-3">'.$preview.'</div>'.
                '<div class="flex justify-end">'.
                '<a href="'.e($url).'" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-full border-b-2 border-primary-600 dark:border-primary-500 bg-primary-500/15 dark:bg-primary-500/25 text-primary-700 dark:text-primary-300 no-underline">'.
                '<svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>'.
                'Descargar</a>'.
                '</div>'.
                '</div>';
        }, $files);

        $downloadAllButton = filled($downloadAllUrl)
            ? '<div class="mb-4 flex justify-end">'.
                '<a href="'.e($downloadAllUrl).'" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-full border-b-2 border-info-600 dark:border-info-500 bg-info-500/15 dark:bg-info-500/25 text-info-700 dark:text-info-300 no-underline">'.
                '<svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5v9A2.25 2.25 0 0 1 18.75 18.75H5.25A2.25 2.25 0 0 1 3 16.5v-9m18 0-2.25-2.25M21 7.5l-2.25 2.25M3 7.5 5.25 5.25M3 7.5l2.25 2.25M9 11.25h6m-6 3h6" /></svg>'.
                'Descargar todos</a>'.
                '</div>'
            : '';

        return new HtmlString($downloadAllButton.'<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">'.implode('', $cards).'</div>');
    }
}
