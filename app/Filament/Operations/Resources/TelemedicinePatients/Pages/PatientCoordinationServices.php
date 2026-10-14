<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicinePatients\Pages;

use App\Filament\Operations\Resources\OperationCoordinationServices\OperationCoordinationServiceResource;
use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTableFilters;
use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Models\TelemedicinePatient;
use App\Services\OperationQuoteGeneratorPdfService;
use App\Services\OperationServiceOrderPdfService;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Filament\TelemedicinePatientPageHeader;
use App\Support\Operations\CoordinationServiceDocumentsAggregator;
use App\Support\Operations\CoordinationServiceItemsManager;
use App\Support\Operations\CoordinationServiceQuoteManager;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Panel;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

/**
 * Consulta de todas las coordinaciones de servicio de un paciente
 * (telemedicina, RETAIL y registro directo), de cualquier estatus.
 *
 * Es estrictamente de solo lectura: la única acción de la tabla abre el
 * detalle en un panel lateral sin salir de la página. No enlaza a la ficha de
 * gestión de la coordinación ni a órdenes o cotizaciones; gestionar sigue
 * siendo trabajo del cuadro de control de Servicios Médicos.
 */
class PatientCoordinationServices extends ManageRelatedRecords
{
    protected static string $resource = TelemedicinePatientResource::class;

    protected static string $relationship = 'operationCoordinationServices';

    protected static ?string $title = 'Servicios del paciente';

    protected static ?string $breadcrumb = 'Servicios del paciente';

    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * Valores de `servicie` y `managed_by` del paciente, una consulta por
     * petición para las opciones de ambos filtros.
     *
     * @var Collection<int, OperationCoordinationService>|null
     */
    private ?Collection $filterSource = null;

    public static function getRoutePath(Panel $panel): string
    {
        return '/{record}/servicios';
    }

    /**
     * Mismo acceso que pacientes y Servicios Médicos: la página muestra datos
     * de coordinaciones, que sin el segundo permiso no se ven en ningún lado.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function canAccess(array $parameters = []): bool
    {
        return TelemedicinePatientResource::canAccess()
            && OperationCoordinationServiceResource::canAccess();
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless(
            OperationsSupplierScope::applyToQuery(TelemedicinePatient::query(), 'supplier_id')->whereKey($this->getRecord()->getKey())->exists(),
            404,
        );
    }

    public function getTitle(): string|Htmlable
    {
        return 'Servicios del paciente';
    }

    public function getHeading(): string|Htmlable
    {
        /** @var TelemedicinePatient $patient */
        $patient = $this->getRecord();

        return TelemedicinePatientPageHeader::forPatient($patient, 'services');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back_to_patient')
                ->label('Volver a la ficha')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => TelemedicinePatientResource::getUrl('view', ['record' => $this->getRecord()])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Coordinaciones de servicio')
            ->description('Consulta de todos los servicios del paciente, de cualquier estatus. Pulse una fila para ver su detalle. Para gestionar un servicio use el cuadro de control de Servicios Médicos.')
            ->searchPlaceholder('Buscar por caso, referencia, servicio, orden, cotización o factura')
            ->searchDebounce('350ms')
            ->deferLoading()
            ->deferFilters(false)
            ->striped()
            ->modifyQueryUsing(fn (Builder $query): Builder => OperationsSupplierScope::applyCoordinationListScope($query)
                ->with(self::tableEagerLoads()))
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->recordUrl(null)
            ->recordAction('detail')
            ->columns([
                TextColumn::make('telemedicineCase.code')
                    ->label('Caso')
                    ->badge()
                    ->color('primary')
                    ->icon(Heroicon::OutlinedFolder)
                    ->weight(FontWeight::SemiBold)
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? mb_strtoupper($state) : '—')
                    ->placeholder('Sin caso')
                    ->description(fn (OperationCoordinationService $record): ?string => ($reference = self::meaningful($record->reference_number)) !== null
                        ? 'Ref. '.$reference
                        : null)
                    // La orden, la cotización y la factura no tienen columna, pero se encuentran por la búsqueda.
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchCaseAndReferences($query, $search)),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(fn (mixed $state): string => OperationCoordinationServicesTable::statusBadgeColor($state))
                    ->icon(fn (mixed $state): Heroicon => self::statusIcon($state))
                    ->sortable(),
                TextColumn::make('servicie')
                    ->label('Servicio')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (OperationCoordinationService $record): ?string => self::serviceDescription($record))
                    ->wrap()
                    ->searchable(['servicie', 'specific_service', 'general_service']),
                TextColumn::make('clinical_items')
                    ->label('Ítems clínicos')
                    ->state(fn (OperationCoordinationService $record): array => self::clinicalItemLines($record))
                    ->placeholder('Sin ítems')
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->size('sm')
                    ->color('gray')
                    ->wrap()
                    ->extraCellAttributes(['style' => 'min-width: 16rem; max-width: 26rem;']),
                TextColumn::make('date_solicitud')
                    ->label('Fechas')
                    ->date('d/m/Y')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->placeholder('—')
                    ->description(fn (OperationCoordinationService $record): ?string => ($date = self::formatDate($record->date_service)) !== null
                        ? 'Prestado '.$date
                        : 'Sin fecha de prestación')
                    ->tooltip('Fecha de solicitud y, debajo, de prestación del servicio')
                    ->sortable(),
                TextColumn::make('managed_by')
                    ->label('Gestionado por')
                    ->badge()
                    ->color(fn (?string $state): string => OperationCoordinationServicesTable::managedByBadgeColor($state))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? mb_strtoupper($state) : '—')
                    ->description(fn (OperationCoordinationService $record): ?string => self::userSeesNegotiation()
                        ? self::meaningful($record->supplier_service)
                        : null)
                    ->wrap()
                    ->visibleFrom('md'),
                TextColumn::make('created_at')
                    ->label('Registrado')
                    ->since()
                    ->dateTimeTooltip('d/m/Y h:i a')
                    ->description(fn (OperationCoordinationService $record): ?string => ($by = self::meaningful($record->created_by)) !== null
                        ? 'Por '.$by
                        : null)
                    ->color('gray')
                    ->sortable()
                    ->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus del servicio')
                    ->multiple()
                    ->options(array_combine(
                        array_keys(OperationCoordinationServicesTableFilters::STATUS_GROUPS),
                        array_keys(OperationCoordinationServicesTableFilters::STATUS_GROUPS),
                    ))
                    ->placeholder('Todos los estatus')
                    ->query(fn (Builder $query, array $data): Builder => self::whereStatusIn($query, $data['values'] ?? [])),
                SelectFilter::make('servicie')
                    ->label('Tipo de servicio')
                    ->multiple()
                    ->options(fn (): array => $this->distinctPatientValues('servicie'))
                    ->placeholder('Todos los servicios'),
                SelectFilter::make('managed_by')
                    ->label('Proveedor')
                    ->multiple()
                    ->options(fn (): array => $this->distinctPatientValues('managed_by'))
                    ->placeholder('Todos los proveedores')
                    ->visible(fn (): bool => OperationCoordinationServicesTableFilters::userCanFilterBySupplier()),
                Filter::make('created_at')
                    ->label('Fecha de registro')
                    ->schema([
                        DatePicker::make('desde')
                            ->label('Registrado desde')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now()),
                        DatePicker::make('hasta')
                            ->label('Registrado hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now())
                            ->minDate(fn (Get $get): mixed => $get('desde')),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->query(fn (Builder $query, array $data): Builder => OperationCoordinationServicesTableFilters::applyRegistrationDateFilter($query, $data)),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                self::detailAction(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Sin servicios para mostrar')
            ->emptyStateDescription('Aquí aparecen las coordinaciones de sus casos de telemedicina, de los registros RETAIL y del registro directo. Si filtró o buscó algo, límpielo para ver todos.')
            ->emptyStateIcon(Heroicon::OutlinedSquare3Stack3d);
    }

    private static function statusIcon(mixed $status): Heroicon
    {
        return match (mb_strtoupper(trim((string) $status))) {
            'PENDIENTE' => Heroicon::OutlinedClock,
            'PENDIENTE POR RESULTADOS' => Heroicon::OutlinedBeaker,
            'EN GESTION' => Heroicon::OutlinedArrowPath,
            'FINALIZADO' => Heroicon::OutlinedCheckCircle,
            'CANCELADA', 'CANCELADO' => Heroicon::OutlinedXCircle,
            'NOVEDAD ADMON', 'NOVEDAD ADMON ESTUDIO' => Heroicon::OutlinedExclamationTriangle,
            default => Heroicon::OutlinedQuestionMarkCircle,
        };
    }

    /**
     * Busca por código de caso y por los números del servicio que no tienen
     * columna propia: referencia, orden, cotización y factura.
     */
    public static function searchCaseAndReferences(Builder $query, string $search): Builder
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($search)).'%';
        $model = $query->getModel();

        return $query->where(function (Builder $match) use ($like, $model): void {
            $match->whereHas('telemedicineCase', fn (Builder $case): Builder => $case->where('code', 'like', $like));

            foreach (['reference_number', 'service_order_number', 'quote_number', 'bill_number'] as $column) {
                $match->orWhere($model->qualifyColumn($column), 'like', $like);
            }
        });
    }

    /**
     * Detalle de solo lectura en un panel lateral: sin botón de guardar y sin
     * acciones dentro. Lo pesado (ítems, órdenes, documentos) se arma solo al
     * abrirlo, nunca al pintar la tabla.
     */
    public static function detailAction(): Action
    {
        return Action::make('detail')
            ->label('Ver detalle')
            ->icon(Heroicon::OutlinedEye)
            ->iconButton()
            ->color('gray')
            ->tooltip('Ver detalle del servicio')
            ->slideOver()
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalIcon(Heroicon::OutlinedSquare3Stack3d)
            ->modalIconColor(fn (OperationCoordinationService $record): string => OperationCoordinationServicesTable::statusBadgeColor($record->status))
            ->modalHeading(fn (OperationCoordinationService $record): string => 'Servicio '.(filled($record->telemedicineCase?->code)
                ? mb_strtoupper((string) $record->telemedicineCase->code)
                : '#'.$record->getKey()))
            ->modalDescription(fn (OperationCoordinationService $record): ?string => self::meaningful($record->servicie))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->schema(fn (OperationCoordinationService $record): array => self::detailSchema($record));
    }

    /**
     * Resumen arriba y el resto en pestañas con contador: el analista ve lo
     * esencial sin desplazarse y abre solo lo que necesita. Las pestañas sin
     * datos no se muestran.
     *
     * @return list<\Filament\Schemas\Components\Component>
     */
    public static function detailSchema(OperationCoordinationService $record): array
    {
        $seesNegotiation = self::userSeesNegotiation();
        $items = self::clinicalItems($record);
        $orders = $seesNegotiation ? self::serviceOrders($record) : [];
        $quotes = $seesNegotiation ? self::quotes($record) : [];
        $documents = self::documents($record);

        return [
            Section::make()
                ->compact()
                ->columnSpanFull()
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2])->schema([
                        TextEntry::make('detail_status')
                            ->label('Estatus')
                            ->state((string) ($record->status ?: '—'))
                            ->badge()
                            ->color(OperationCoordinationServicesTable::statusBadgeColor($record->status)),
                        self::entry('Servicio derivado', self::serviceDescription($record), Heroicon::OutlinedArrowTurnDownRight),
                        self::entry('Solicitado', self::formatDate($record->date_solicitud), Heroicon::OutlinedCalendarDays),
                        self::entry('Prestado', self::formatDate($record->date_service), Heroicon::OutlinedCalendar),
                        self::entry('Gestionado por', $record->managed_by, Heroicon::OutlinedBuildingOffice2),
                        self::entry('Proveedor', $seesNegotiation ? $record->supplier_service : null, Heroicon::OutlinedBuildingStorefront),
                        self::entry('Médico', $record->telemedicineDoctor?->full_name, Heroicon::OutlinedUser),
                        self::entry('Prioridad', $record->telemedicinePriority?->name, Heroicon::OutlinedFlag),
                        self::entry('Referencia', $record->reference_number, Heroicon::OutlinedHashtag),
                    ]),
                    self::entry('Diagnóstico', $record->symptoms_diagnosis, Heroicon::OutlinedHeart)->columnSpanFull(),
                ]),
            Tabs::make('detail_tabs')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Ítems clínicos')
                        ->icon(Heroicon::OutlinedBeaker)
                        ->badge(count($items))
                        ->visible($items !== [])
                        ->schema([
                            RepeatableEntry::make('detail_items')
                                ->hiddenLabel()
                                ->state($items)
                                ->table([
                                    TableColumn::make('Ítem')->width('50%'),
                                    TableColumn::make('Cobertura')->width('25%'),
                                    TableColumn::make('Estatus')->width('25%'),
                                ])
                                ->schema([
                                    TextEntry::make('label')
                                        ->weight(FontWeight::SemiBold)
                                        ->belowContent(fn (TextEntry $component): ?string => self::rowValue($component, 'category')),
                                    TextEntry::make('coverage_label')
                                        ->badge()
                                        ->color(fn (TextEntry $component): string => match (self::rowValue($component, 'coverage')) {
                                            '1' => 'success',
                                            '0' => 'warning',
                                            default => 'gray',
                                        }),
                                    TextEntry::make('status')
                                        ->badge()
                                        ->color(fn (?string $state): string => self::itemStatusColor($state)),
                                ]),
                        ]),
                    Tab::make('Órdenes')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->badge(count($orders))
                        ->visible($orders !== [])
                        ->schema([
                            RepeatableEntry::make('detail_orders')
                                ->hiddenLabel()
                                ->state($orders)
                                ->table([
                                    TableColumn::make('Orden')->width('24%'),
                                    TableColumn::make('Estatus')->width('18%'),
                                    TableColumn::make('Ítems')->width('34%'),
                                    TableColumn::make('PDF')->width('24%'),
                                ])
                                ->schema([
                                    TextEntry::make('order_number')->weight(FontWeight::SemiBold),
                                    TextEntry::make('status')->badge()->color('gray'),
                                    TextEntry::make('items'),
                                    self::fileLinksEntry(),
                                ]),
                        ]),
                    Tab::make('Cotizaciones')
                        ->icon(Heroicon::OutlinedCalculator)
                        ->badge(count($quotes))
                        ->visible($quotes !== [])
                        ->schema([
                            RepeatableEntry::make('detail_quotes')
                                ->hiddenLabel()
                                ->state($quotes)
                                ->table([
                                    TableColumn::make('Cotización')->width('24%'),
                                    TableColumn::make('Estatus')->width('18%'),
                                    TableColumn::make('Total')->width('18%'),
                                    TableColumn::make('Fecha')->width('16%'),
                                    TableColumn::make('PDF')->width('24%'),
                                ])
                                ->schema([
                                    TextEntry::make('number')
                                        ->weight(FontWeight::SemiBold)
                                        ->belowContent(fn (TextEntry $component): ?string => self::rowValue($component, 'type')),
                                    TextEntry::make('status')->badge()->color('gray'),
                                    TextEntry::make('total'),
                                    TextEntry::make('created_at'),
                                    self::fileLinksEntry(),
                                ]),
                        ]),
                    Tab::make('Documentos')
                        ->icon(Heroicon::OutlinedPaperClip)
                        ->badge(count($documents))
                        ->visible($documents !== [])
                        ->schema([
                            RepeatableEntry::make('detail_documents')
                                ->hiddenLabel()
                                ->state($documents)
                                ->table([
                                    TableColumn::make('Documento')->width('36%'),
                                    TableColumn::make('Tipo(s)')->width('24%'),
                                    TableColumn::make('Fecha')->width('16%'),
                                    TableColumn::make('Archivo')->width('24%'),
                                ])
                                ->schema([
                                    TextEntry::make('document_name')
                                        ->weight(FontWeight::SemiBold)
                                        ->belowContent(fn (TextEntry $component): ?string => self::rowValue($component, 'service')),
                                    TextEntry::make('types'),
                                    TextEntry::make('uploaded_at'),
                                    self::fileLinksEntry(),
                                ]),
                        ]),
                    Tab::make('Auditoría')
                        ->icon(Heroicon::OutlinedShieldCheck)
                        ->schema([
                            Grid::make(['default' => 1, 'sm' => 2])->schema([
                                self::entry('Registrado', self::auditLine($record->created_at, $record->created_by), Heroicon::OutlinedPlusCircle),
                                self::entry('Última actualización', self::auditLine($record->updated_at, $record->updated_by), Heroicon::OutlinedArrowPath),
                            ]),
                        ]),
                ]),
        ];
    }

    /**
     * Valor de la fila actual de una RepeatableEntry; un vacío o «—» cuenta
     * como ausente para no pintar líneas de relleno.
     */
    private static function rowValue(TextEntry $component, string $key): ?string
    {
        $row = $component->getContainer()->getConstantState();
        $value = is_array($row) ? ($row[$key] ?? null) : null;

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return self::meaningful($value);
    }

    private static function auditLine(mixed $at, mixed $by): ?string
    {
        $when = $at instanceof \DateTimeInterface ? $at->format('d/m/Y h:i a') : null;
        $who = self::meaningful($by);

        return self::meaningful(implode(' · ', array_filter([$when, $who !== null ? 'por '.$who : null])));
    }

    /**
     * Solo lo que pinta esta tabla: el código del caso y lo que necesita el
     * cálculo de ítems clínicos (sus cuatro relaciones y las órdenes con sus
     * ítems, para el estatus efectivo). Más ligero que el cuadro de control,
     * que además precarga médico, proveedor, unidades y cotizaciones. Médico y
     * prioridad del detalle se cargan solo para el registro que se abre.
     *
     * @return array<int|string, mixed>
     */
    public static function tableEagerLoads(): array
    {
        return [
            'telemedicineCase:id,code',
            'telemedicinePatientMedications.operationInventory:id,is_covered',
            'telemedicinePatientLabs',
            'telemedicinePatientStudies',
            'telemedicinePatientSpecialties',
            'operationServiceOrders' => fn (HasMany $orders): HasMany => $orders->select(['id', 'order_number', 'status', 'operation_coordination_service_id']),
            'operationServiceOrders.operationServiceOrderItems:id,operation_service_order_id,item_name,category',
        ];
    }

    /**
     * El filtro va directo contra la columna: aquí se busca un servicio
     * concreto, no se separa lo que falta por gestionar como en el cuadro de
     * control.
     */
    public static function whereStatusIn(Builder $query, mixed $selected): Builder
    {
        $statuses = OperationCoordinationServicesTableFilters::statusValues(
            array_values(array_filter(is_array($selected) ? $selected : [], 'is_string')),
        );

        return $statuses === []
            ? $query
            : $query->whereIn($query->getModel()->qualifyColumn('status'), $statuses);
    }

    /**
     * Ítems en texto plano para la tabla: sin enlaces a órdenes, cotizaciones
     * ni a la gestión de ítems, a diferencia de la lista del cuadro de control.
     *
     * @return list<string>
     */
    public static function clinicalItemLines(OperationCoordinationService $record): array
    {
        return array_map(
            static fn (array $item): string => $item['category'].' · '.$item['label'].' — '.$item['status'],
            self::clinicalItems($record),
        );
    }

    /**
     * @return list<array{category: string, label: string, coverage_label: string, coverage: bool|null, status: string}>
     */
    public static function clinicalItems(OperationCoordinationService $record): array
    {
        return CoordinationServiceItemsManager::clinicalItemsWithEffectiveDisplayStatus($record)
            ->map(static fn (array $item): array => [
                'category' => mb_strtoupper(trim((string) ($item['category'] ?? ''))),
                'label' => trim((string) ($item['label'] ?? '')) ?: 'Ítem sin nombre',
                'coverage_label' => trim((string) ($item['coverage_label'] ?? '')) ?: '—',
                'coverage' => $item['coverage'] ?? null,
                'status' => mb_strtoupper(trim((string) ($item['status'] ?? ''))) ?: '—',
            ])
            ->values()
            ->all();
    }

    private static function itemStatusColor(?string $status): string
    {
        return match (mb_strtoupper(trim((string) $status))) {
            'PENDIENTE' => 'warning',
            'EN GESTION' => 'primary',
            'FINALIZADO' => 'success',
            'CANCELADA', 'CANCELADO', 'CADUCADA' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Órdenes del registro abierto, con su PDF. Si el PDF ya está guardado se
     * enlaza el archivo (inmediato); si no, la ruta existente lo genera.
     *
     * @return list<array{order_number: string, status: string, items: string, view_url: string, download_url: string, download_name: string|null}>
     */
    private static function serviceOrders(OperationCoordinationService $record): array
    {
        return $record->operationServiceOrders()
            ->with('operationServiceOrderItems:id,operation_service_order_id,item_name')
            ->orderByDesc('id')
            ->get(['id', 'order_number', 'status', 'service_order_pdf_path', 'operation_coordination_service_id'])
            ->map(static function (OperationServiceOrder $order): array {
                $stored = self::publicFileUrl($order->service_order_pdf_path);

                return [
                    'order_number' => (string) ($order->order_number ?: '#'.$order->getKey()),
                    'status' => (string) ($order->status ?: '—'),
                    'items' => $order->operationServiceOrderItems->pluck('item_name')->filter()->implode(', ') ?: '—',
                    'view_url' => $stored ?? route('operations.operation-service-orders.pdf.preview', ['operationServiceOrder' => $order]),
                    'download_url' => $stored ?? route('operations.operation-service-orders.pdf', ['operationServiceOrder' => $order]),
                    'download_name' => $stored !== null ? OperationServiceOrderPdfService::filename($order) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{number: string, type: string, status: string, total: string, created_at: string, view_url: string|null, download_url: string|null, download_name: string|null}>
     */
    private static function quotes(OperationCoordinationService $record): array
    {
        return $record->operationQuoteGenerators()
            ->orderByDesc('id')
            ->get(['id', 'operation_coordination_service_id', 'type_service', 'status', 'total', 'quote_pdf_path', 'created_at'])
            ->map(static function (OperationQuoteGenerator $quote): array {
                $url = self::publicFileUrl($quote->quote_pdf_path);

                return [
                    'number' => CoordinationServiceQuoteManager::formatCoordinationQuoteNumber((int) $quote->getKey()),
                    'type' => (string) ($quote->type_service ?? ''),
                    'status' => (string) ($quote->status ?: '—'),
                    'total' => is_numeric($quote->total) ? 'US$ '.number_format((float) $quote->total, 2, ',', '.') : '—',
                    'created_at' => $quote->created_at?->format('d/m/Y') ?? '—',
                    'view_url' => $url,
                    'download_url' => $url,
                    'download_name' => $url !== null ? OperationQuoteGeneratorPdfService::filename($quote) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{document_name: string, service: string, types: string, uploaded_at: string, view_url: string|null, download_url: string|null, download_name: string|null}>
     */
    private static function documents(OperationCoordinationService $record): array
    {
        return array_map(static function (array $row): array {
            $url = self::publicFileUrl($row['file_path']);
            $name = trim($row['document_name']) ?: 'Documento sin nombre';

            return [
                'document_name' => $name,
                'service' => trim($row['service']) ?: '—',
                'types' => implode(', ', $row['document_types']) ?: '—',
                'uploaded_at' => self::formatDate($row['uploaded_at']) ?? '—',
                'view_url' => $url,
                'download_url' => $url,
                'download_name' => $url !== null ? basename((string) $row['file_path']) : null,
            ];
        }, CoordinationServiceDocumentsAggregator::forCoordination($record));
    }

    /**
     * URL pública del archivo solo si existe en el disco: un enlace roto es
     * peor que decir que no hay archivo.
     */
    private static function publicFileUrl(mixed $path): ?string
    {
        $path = ltrim(trim((string) $path), '/');

        if ($path === '' || str_contains($path, '..') || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return URL::to(Storage::disk('public')->url($path));
    }

    /**
     * «Ver» abre en otra pestaña y «Descargar» baja el archivo. Son enlaces
     * simples: no pasan por Livewire, así que no recargan el panel ni la tabla.
     */
    private static function fileLinksEntry(): TextEntry
    {
        return TextEntry::make('file_links')
            ->state(fn (TextEntry $component): HtmlString => self::fileLinksHtml(
                self::rowValue($component, 'view_url'),
                self::rowValue($component, 'download_url'),
                self::rowValue($component, 'download_name'),
            ))
            ->html();
    }

    public static function fileLinksHtml(?string $viewUrl, ?string $downloadUrl, ?string $downloadName): HtmlString
    {
        if ($viewUrl === null && $downloadUrl === null) {
            return new HtmlString('<span class="text-xs text-gray-500 dark:text-gray-400">Sin archivo</span>');
        }

        $class = 'inline-flex items-center gap-1 rounded-full bg-primary-500/15 px-2.5 py-1 text-xs font-semibold text-primary-700 no-underline hover:bg-primary-500/25 dark:bg-primary-500/25 dark:text-primary-300';
        $links = [];

        if ($viewUrl !== null) {
            $links[] = '<a href="'.e($viewUrl).'" target="_blank" rel="noopener noreferrer" class="'.$class.'">Ver</a>';
        }

        if ($downloadUrl !== null) {
            $links[] = '<a href="'.e($downloadUrl).'"'
                .($downloadName !== null ? ' download="'.e($downloadName).'"' : '')
                .' target="_blank" rel="noopener noreferrer" class="'.$class.'">Descargar</a>';
        }

        return new HtmlString('<span class="inline-flex flex-wrap gap-1.5">'.implode('', $links).'</span>');
    }

    /**
     * Campo de resumen: si no trae un dato real no se muestra, para que el
     * panel enseñe solo lo que el analista necesita leer.
     */
    private static function entry(string $label, mixed $value, ?Heroicon $icon = null): TextEntry
    {
        $text = self::meaningful($value);

        return TextEntry::make('detail_'.md5($label))
            ->label($label)
            ->state($text)
            ->icon($icon)
            ->iconColor('gray')
            ->visible($text !== null);
    }

    /**
     * Texto con contenido real; los rellenos que dejan los formularios
     * («...», «—», «-», «N/A») cuentan como vacío.
     */
    private static function meaningful(mixed $value): ?string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';

        return in_array(mb_strtoupper($text), ['', '...', '…', '—', '-', 'N/A', 'NA'], true) ? null : $text;
    }

    private static function formatDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return is_scalar($value) ? (string) $value : null;
        }
    }

    private static function serviceDescription(OperationCoordinationService $record): ?string
    {
        $main = self::meaningful($record->servicie);
        $parts = array_values(array_unique(array_filter([
            self::meaningful($record->general_service),
            self::meaningful($record->specific_service),
        ], fn (?string $part): bool => $part !== null && $part !== $main)));

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * Mismo criterio que el cuadro de control: ATENMEDI no ve proveedor,
     * orden, cotización ni montos.
     */
    private static function userSeesNegotiation(): bool
    {
        $departments = Auth::user()?->departament;

        return ! in_array('ATENMEDI', is_array($departments) ? $departments : [], true);
    }

    /**
     * Opciones de filtro sacadas solo de los servicios visibles de este paciente.
     *
     * @return array<string, string>
     */
    private function distinctPatientValues(string $column): array
    {
        $this->filterSource ??= OperationsSupplierScope::applyCoordinationListScope(
            OperationCoordinationService::query()->where('telemedicine_patient_id', $this->getRecord()->getKey()),
        )->get(['id', 'servicie', 'managed_by']);

        return $this->filterSource
            ->pluck($column)
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(static fn (string $value): array => [$value => $value])
            ->all();
    }
}
