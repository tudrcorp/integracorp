<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\Suppliers\Pages;

use App\Filament\Operations\Resources\OperationServiceOrders\OperationServiceOrderResource;
use App\Filament\Operations\Resources\Suppliers\SupplierResource;
use App\Models\OperationServiceOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\OperationServiceOrderDocuments;
use App\Support\Operations\OperationServiceOrderFinalizationException;
use App\Support\Operations\OperationServiceOrderListDisplay;
use App\Support\Operations\OperationServiceOrderValidity;
use App\Support\Operations\OperationServiceOrderViewActions;
use App\Support\Operations\SupplierServiceOrderBuckets;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Órdenes de servicio de un proveedor (el que ejecuta), por grupo: en gestión,
 * pendientes de facturar, finalizadas y canceladas o caducadas.
 *
 * Las órdenes en gestión se finalizan aquí mismo cargando el documento, en un
 * panel lateral y sin recargar la página. La finalización va por
 * {@see OperationServiceOrderDocuments}: la misma vía que la vista de la orden,
 * con permiso, bloqueo de la fila, bitácora del caso y auditoría.
 */
class SupplierServiceOrders extends ManageRelatedRecords
{
    protected static string $resource = SupplierResource::class;

    protected static string $relationship = 'operationServiceOrders';

    protected static ?string $title = 'Órdenes de servicio';

    protected static ?string $breadcrumb = 'Órdenes de servicio';

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function getRoutePath(Panel $panel): string
    {
        return '/{record}/ordenes-de-servicio';
    }

    /**
     * Mismo acceso que proveedores y órdenes de servicio: la página muestra
     * órdenes, que sin el segundo permiso no se ven en ningún lado.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function canAccess(array $parameters = []): bool
    {
        return SupplierResource::canAccess() && OperationServiceOrderResource::canAccess();
    }

    public function mount(int|string $record): void
    {
        // Igual que la tabla de órdenes: las vencidas se marcan CADUCADA antes de
        // contar, para que «En gestión» no muestre órdenes que ya no se pueden
        // finalizar. Con freno: corre como mucho cada 10 minutos.
        OperationServiceOrderValidity::expireEligibleOrdersThrottled('system');

        parent::mount($record);
    }

    /**
     * La pestaña viaja en la URL: los contadores del encabezado de la ficha
     * abren directamente la que corresponde.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function queryString(): array
    {
        return [
            'activeTab' => ['as' => 'grupo', 'except' => SupplierServiceOrderBuckets::IN_PROGRESS],
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Órdenes de servicio';
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var Supplier $supplier */
        $supplier = $this->getRecord();

        return trim(($supplier->name ?: 'Proveedor').(filled($supplier->rif) ? ' · RIF '.$supplier->rif : ''));
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToSupplier')
                ->label('Volver a la ficha')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => SupplierResource::getUrl('view', ['record' => $this->getRecord()])),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return SupplierServiceOrderBuckets::IN_PROGRESS;
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = SupplierServiceOrderBuckets::counts((int) $this->getRecord()->getKey());
        $tabs = [];

        foreach (SupplierServiceOrderBuckets::definitions() as $bucket => $definition) {
            $tabs[$bucket] = Tab::make($definition['label'])
                ->icon($definition['icon'])
                ->badge((string) $counts[$bucket])
                ->badgeColor($counts[$bucket] > 0 ? $definition['color'] : 'gray')
                ->modifyQueryUsing(fn (Builder $query): Builder => SupplierServiceOrderBuckets::apply($query, $bucket))
                // Al ejecutar una acción la orden se busca fuera del filtro: si otro
                // analista la cerró mientras tanto, el servicio lo explica.
                ->excludeQueryWhenResolvingRecord();
        }

        return $tabs;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order_number')
            ->modifyQueryUsing(fn (Builder $query): Builder => OperationsSupplierScope::applyServiceOrderListScope($query)
                ->with([
                    'operationCoordinationService.telemedicinePatient',
                    'telemedicinePriority',
                ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_number')
                    ->label('Nº orden')
                    ->weight('semibold')
                    ->color('primary')
                    ->icon(Heroicon::OutlinedHashtag)
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (?string $state): string => self::statusColor($state))
                    ->placeholder('—'),
                TextColumn::make('patient')
                    ->label('Paciente')
                    ->state(fn (OperationServiceOrder $record): string => OperationServiceOrderListDisplay::patientFullName($record))
                    ->description(fn (OperationServiceOrder $record): string => 'Caso '.OperationServiceOrderListDisplay::serviceReferenceNumber($record))
                    ->wrap(),
                TextColumn::make('service_type')
                    ->label('Tipo de servicio')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('telemedicinePriority.name')
                    ->label('Prioridad')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('vigencia')
                    ->label('Vigencia')
                    ->state(fn (OperationServiceOrder $record): ?string => OperationServiceOrderValidity::vigenciaShortLabel($record))
                    ->badge()
                    ->color(fn (OperationServiceOrder $record): string => OperationServiceOrderValidity::vigenciaTone($record) ?? 'gray')
                    ->placeholder('—')
                    ->visible(fn (): bool => $this->activeTab === SupplierServiceOrderBuckets::IN_PROGRESS),
                TextColumn::make('administrative_status')
                    ->label('Factura')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === OperationServiceOrderListDisplay::ADMINISTRATIVE_STATUS_INVOICED ? 'Facturada' : 'Sin factura')
                    ->color(fn (?string $state): string => $state === OperationServiceOrderListDisplay::ADMINISTRATIVE_STATUS_INVOICED ? 'success' : 'warning')
                    ->visible(fn (): bool => in_array($this->activeTab, [SupplierServiceOrderBuckets::PENDING_INVOICE, SupplierServiceOrderBuckets::FINALIZED], true)),
                TextColumn::make('total_amount_usd')
                    ->label('Total US$')
                    ->money('USD', locale: 'es')
                    ->alignEnd()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Registro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('service_type')
                    ->label('Tipo de servicio')
                    ->options([
                        'LABORATORIOS' => 'Laboratorios',
                        'IMAGENOLOGIA' => 'Imagenología',
                        'ESPECIALISTA' => 'Especialista',
                        'MEDICAMENTOS' => 'Medicamentos',
                    ]),
            ])
            ->recordActions([
                Action::make('openOrder')
                    ->label('Ver orden')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (OperationServiceOrder $record): string => OperationServiceOrderResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
                $this->finalizeAction(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentCheck)
            ->emptyStateHeading(fn (): string => match ($this->activeTab) {
                SupplierServiceOrderBuckets::PENDING_INVOICE => 'Sin órdenes pendientes de facturar',
                SupplierServiceOrderBuckets::FINALIZED => 'Sin órdenes finalizadas',
                SupplierServiceOrderBuckets::CLOSED_UNFINISHED => 'Sin órdenes canceladas o caducadas',
                default => 'Sin órdenes en gestión',
            })
            ->emptyStateDescription(fn (): string => SupplierServiceOrderBuckets::definitions()[$this->activeTab ?? SupplierServiceOrderBuckets::IN_PROGRESS]['description']
                ?? '')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    private function finalizeAction(): Action
    {
        return Action::make('finalizeOrder')
            ->label('Finalizar')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->button()
            ->visible(fn (OperationServiceOrder $record): bool => OperationServiceOrderViewActions::canFinalize($record)
                && OperationServiceOrderDocuments::userCanFinalize())
            ->slideOver()
            ->modalWidth(Width::TwoExtraLarge)
            ->modalIcon(Heroicon::OutlinedCheckBadge)
            ->modalIconColor('success')
            ->modalHeading(fn (OperationServiceOrder $record): string => 'Finalizar orden '.OperationServiceOrderDocuments::orderLabel($record))
            ->modalDescription(fn (OperationServiceOrder $record): string => mb_strtoupper(trim((string) $record->service_type)) === 'MEDICAMENTOS'
                ? 'Cargue el comprobante de entrega de los medicamentos. Al finalizar no se puede deshacer.'
                : 'Cargue el documento que respalda el servicio prestado. Al finalizar no se puede deshacer.')
            ->modalSubmitActionLabel('Finalizar orden')
            ->modalCancelActionLabel('Cancelar')
            ->schema(fn (OperationServiceOrder $record): array => [
                Placeholder::make('finalizeSummary')
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => self::finalizeSummary($record)),
                ...OperationServiceOrderDocuments::medicationDeliverySchema($record),
                ...OperationServiceOrderDocuments::formSchema(
                    fn (): string => OperationServiceOrderDocuments::directory($record),
                ),
            ])
            ->action(function (OperationServiceOrder $record, array $data): void {
                $user = Auth::user();

                if (! $user instanceof User) {
                    return;
                }

                try {
                    $result = OperationServiceOrderDocuments::finalize(
                        $record,
                        OperationServiceOrderDocuments::fromForm($data),
                        $user,
                        OperationServiceOrderDocuments::ORIGIN_SUPPLIER,
                        filled($data[OperationServiceOrderDocuments::INVENTORY_UBICATION_FIELD] ?? null) ? (int) $data[OperationServiceOrderDocuments::INVENTORY_UBICATION_FIELD] : null,
                    );
                } catch (OperationServiceOrderFinalizationException $exception) {
                    Notification::make()
                        ->warning()
                        ->title('No se finalizó la orden')
                        ->body($exception->getMessage())
                        ->persistent()
                        ->send();

                    return;
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->danger()
                        ->title('No se finalizó la orden')
                        ->body('Ocurrió un error y no se aplicó ningún cambio. Intente nuevamente; si persiste, avise a soporte.')
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Orden '.OperationServiceOrderDocuments::orderLabel($result['order']).' finalizada')
                    ->body(($result['logged_to_case']
                        ? 'Los documentos se guardaron y quedó registrado en la bitácora del caso.'
                        : 'Los documentos se guardaron.')
                        .' Ahora aparece en «Pendientes» hasta que se cargue la factura.'
                        .$result['follow_up_suffix'])
                    ->send();
            });
    }

    /**
     * Qué pasará al finalizar, para decidir con la orden a la vista.
     */
    private static function finalizeSummary(OperationServiceOrder $record): HtmlString
    {
        $rows = [
            'Paciente' => OperationServiceOrderListDisplay::patientFullName($record),
            'Caso' => OperationServiceOrderListDisplay::serviceReferenceNumber($record),
            'Tipo de servicio' => filled($record->service_type) ? (string) $record->service_type : '—',
            'Vigencia' => OperationServiceOrderValidity::vigenciaShortLabel($record) ?? '—',
        ];

        $items = collect($rows)
            ->map(fn (string $value, string $label): string => '<div><dt class="text-xs text-gray-500 dark:text-gray-400">'.e($label).'</dt><dd class="text-sm font-semibold text-gray-950 dark:text-white">'.e($value).'</dd></div>')
            ->implode('');

        return new HtmlString(
            '<div class="space-y-3">'
            .'<dl class="grid grid-cols-2 gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">'.$items.'</dl>'
            .'<p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">'
            .'Al finalizar, la orden pasa a <strong>FINALIZADO</strong>, se cierran sus ítems en la coordinación y queda registrado en la bitácora del caso con su nombre.'
            .'</p>'
            .'</div>'
        );
    }

    private static function statusColor(?string $state): string
    {
        return match (mb_strtoupper(trim((string) $state))) {
            'EN GESTION', 'EN GESTIÓN' => 'info',
            'FINALIZADO' => 'success',
            'CADUCADA', 'CANCELADA', 'CANCELADO' => 'danger',
            default => 'gray',
        };
    }
}
