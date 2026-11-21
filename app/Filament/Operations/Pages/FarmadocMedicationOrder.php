<?php

declare(strict_types=1);

namespace App\Filament\Operations\Pages;

use App\Filament\Operations\Resources\OperationCoordinationServices\OperationCoordinationServiceResource;
use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceItems;
use App\Models\OperationCoordinationService;
use App\Services\Farmadoc\FarmadocAllyClient;
use App\Support\Farmadoc\FarmadocException;
use App\Support\Farmadoc\FarmadocInventoryReceipt;
use App\Support\Farmadoc\FarmadocMedicationList;
use App\Support\Farmadoc\FarmadocOrderDraft;
use App\Support\Farmadoc\FarmadocOrderTracker;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\MedicationInvoiceWizard;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Locked;
use Throwable;

class FarmadocMedicationOrder extends Page implements HasTable
{
    use InteractsWithTable;

    public const CACHE_KEY = 'farmadoc.las-delicias.catalog';

    /**
     * Copia de respaldo del último catálogo que respondió bien: si la API de Farmadoc
     * falla, el analista sigue eligiendo con ella en lugar de quedarse sin tabla.
     */
    public const STALE_CACHE_KEY = 'farmadoc.las-delicias.catalog.stale';

    private const LAST_UBICATION_SESSION_KEY = 'farmadoc.order.last-ubication';

    private const CACHE_MINUTES = 10;

    private const STALE_CACHE_HOURS = 24;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Pedido a Farmadoc';

    protected static ?string $slug = 'pedido-farmadoc';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected string $view = 'filament.operations.pages.farmadoc-medication-order';

    #[Locked]
    public ?int $coordinationId = null;

    #[Locked]
    public ?int $branchId = null;

    public ?string $coordinationLabel = null;

    #[Locked]
    public ?string $coordinationReference = null;

    #[Locked]
    public ?string $patientName = null;

    #[Locked]
    public ?string $ubicationName = null;

    public ?string $branchName = null;

    public ?string $logoUrl = null;

    public ?string $catalogError = null;

    public ?string $catalogNotice = null;

    private bool $catalogAttempted = false;

    /**
     * @var list<array{product_id: int, name: string, presentation: string|null, quantity: int}>
     */
    public array $orderLines = [];

    public bool $reviewingOrder = false;

    /**
     * @var list<array{order_number: string, items_count: int, status_label: string}>
     */
    public array $placedOrders = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if ($user === null || in_array('ATENMEDI', $user->departament ?? [], true)) {
            return false;
        }

        return OperationCoordinationServiceResource::canAccess()
            && MedicationInvoiceWizard::userCanSeeInventoryAvailability();
    }

    public function mount(): void
    {
        $this->resolveCoordination();
        $this->loadCatalog();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->reviewingOrder ? 'Validar pedido a Farmadoc' : 'Pedido a Farmadoc';
    }

    public function getSubheading(): string|Htmlable|null
    {
        if ($this->reviewingOrder) {
            return 'Revise los medicamentos y las cantidades. Farmadoc no recibe nada hasta que envíe el pedido.';
        }

        $text = 'Elija los medicamentos y las cantidades. Cuando termine, valide el pedido.';

        if (is_string($this->branchName) && $this->branchName !== '') {
            $text = $this->branchName.'. '.$text;
        }

        if (is_string($this->coordinationLabel) && $this->coordinationLabel !== '') {
            $text .= ' '.$this->coordinationLabel;
        }

        return $text;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $search, int|string $page, int|string $recordsPerPage, ?string $sortColumn, ?string $sortDirection, ?array $filters): LengthAwarePaginator {
                $paginator = FarmadocMedicationList::paginate(
                    FarmadocMedicationList::applyFilters($this->medications(), $filters, $this->orderedProductIds()),
                    $search,
                    (int) $page,
                    is_numeric($recordsPerPage) ? (int) $recordsPerPage : 25,
                    $sortColumn,
                    $sortDirection,
                );

                $paginator->setCollection(
                    collect($paginator->items())->map(function (array $medication): array {
                        $productId = (int) ($medication['product_id'] ?? 0);
                        $medication['order_quantity'] = $this->lineQuantity($productId) ?? 0;

                        return $medication;
                    })
                );

                return $paginator;
            })
            ->heading('Medicamentos Farmadoc')
            ->description('Catálogo de Las Delicias. No se muestran precios ni existencias.')
            ->searchable()
            ->searchDebounce('400ms')
            ->searchPlaceholder('Buscar por nombre, código de barras, principio activo o SKU')
            ->emptyStateHeading(fn (): string => match (true) {
                $this->catalogError !== null => 'No se pudo cargar el catálogo',
                $this->isTableFilterActive('in_order') && $this->orderLines === [] => 'Aún no hay medicamentos en el pedido',
                default => 'Sin medicamentos',
            })
            ->emptyStateDescription(fn (): ?string => match (true) {
                $this->catalogError !== null => $this->catalogError,
                $this->isTableFilterActive('in_order') && $this->orderLines === [] => 'Quite el filtro «Solo los del pedido» y pulse «Agregar» en los medicamentos que necesite.',
                filled($this->getTableSearch()) || $this->hasActiveTableFilters() => 'Ningún medicamento coincide con la búsqueda o los filtros. Pruebe con otro término o quite los filtros.',
                default => 'Farmadoc no devolvió medicamentos activos para Las Delicias.',
            })
            ->emptyStateIcon(Heroicon::OutlinedBeaker)
            ->defaultPaginationPageOption(25)
            ->paginated([10, 25, 50])
            ->defaultSort('name')
            ->recordClasses(fn (array $record): ?string => ((int) ($record['order_quantity'] ?? 0)) > 0 ? 'fi-farmadoc-row-in-order' : null)
            ->filters([
                Filter::make('requires_prescription')
                    ->label(FarmadocMedicationList::LABEL_PRESCRIPTION)
                    ->toggle(),
                Filter::make('controlled')
                    ->label(FarmadocMedicationList::LABEL_CONTROLLED)
                    ->toggle(),
                Filter::make('in_order')
                    ->label('Solo los del pedido')
                    ->toggle(),
            ], layout: FiltersLayout::Dropdown)
            ->deferFilters(false)
            ->columns([
                TextColumn::make('name')
                    ->label('Medicamento')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (array $record): ?string => FarmadocMedicationList::detailsLine($record))
                    ->wrap()
                    ->sortable(),
                TextColumn::make('barcode')
                    ->label('Código de barras')
                    ->placeholder('—')
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Medium)
                    ->copyable()
                    ->copyMessage('Código de barras copiado')
                    ->copyMessageDuration(1500)
                    ->sortable(),
                TextColumn::make('active_ingredient')
                    ->label('Principio activo')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('conditions')
                    ->label('Condiciones')
                    ->state(fn (array $record): array => FarmadocMedicationList::conditionLabels($record))
                    ->badge()
                    ->color(fn (string $state): string => $state === FarmadocMedicationList::LABEL_CONTROLLED ? 'danger' : 'warning')
                    ->placeholder(''),
                ViewColumn::make('order_quantity')
                    ->label('Cantidad')
                    ->view('filament.operations.columns.farmadoc-quantity-stepper')
                    // Centrada y con ancho fijo: el encabezado «Cantidad» y el control quedan
                    // en el mismo eje. La celda de un ViewColumn es un flex que Filament alinea
                    // al inicio; alignEnd() solo movía el texto del encabezado.
                    ->alignCenter()
                    ->width('11rem'),
            ]);
    }

    /**
     * @return list<int>
     */
    private function orderedProductIds(): array
    {
        return array_map(static fn (array $line): int => (int) $line['product_id'], $this->orderLines);
    }

    private function isTableFilterActive(string $name): bool
    {
        return (bool) ($this->tableFilters[$name]['isActive'] ?? false);
    }

    private function hasActiveTableFilters(): bool
    {
        foreach (['requires_prescription', 'controlled', 'in_order'] as $name) {
            if ($this->isTableFilterActive($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToCoordination')
                ->label('Volver a la gestión')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => ManageCoordinationServiceItems::getUrl(['record' => $this->coordinationId]))
                ->visible(fn (): bool => $this->coordinationId !== null),
            Action::make('refreshCatalog')
                ->label('Actualizar catálogo')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (): bool => ! $this->reviewingOrder)
                ->action(function (): void {
                    $this->refreshCatalog();
                }),
            Action::make('continueOrdering')
                ->label('Seguir eligiendo')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->visible(fn (): bool => $this->reviewingOrder)
                ->action(function (): void {
                    $this->continueOrdering();
                }),
            Action::make('reviewOrder')
                ->label(fn (): string => 'Validar pedido ('.count($this->orderLines).')')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('warning')
                ->visible(fn (): bool => ! $this->reviewingOrder && $this->orderLines !== [])
                ->action(function (): void {
                    $this->reviewOrder();
                }),
            Action::make('placeOrder')
                ->label(fn (): string => 'Enviar pedido ('.count($this->orderLines).')')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('warning')
                ->visible(fn (): bool => $this->reviewingOrder && $this->orderLines !== [])
                ->requiresConfirmation()
                ->modalHeading('Enviar pedido a Farmadoc')
                ->modalDescription('Farmadoc recibirá los medicamentos y las cantidades. Puede armar otro pedido enseguida.')
                ->modalContent(fn (): HtmlString => $this->orderPreview())
                ->fillForm(fn (): array => ['ubication_id' => $this->defaultUbicationId()])
                ->schema([
                    ToggleButtons::make('ubication_id')
                        ->label('¿Para qué ubicación es el pedido?')
                        ->options(fn (): array => FarmadocInventoryReceipt::ubicationOptions())
                        ->helperText('Al llegar, el pedido entra al inventario de esta ubicación.')
                        ->inline()
                        ->required()
                        ->validationMessages(['required' => 'Elija la ubicación que hace el pedido.']),
                ])
                ->modalSubmitActionLabel('Enviar pedido')
                ->action(function (array $data): void {
                    $this->placeOrder(is_numeric($data['ubication_id'] ?? null) ? (int) $data['ubication_id'] : null);
                }),
        ];
    }

    public function reviewOrder(): void
    {
        $this->reviewingOrder = $this->orderLines !== [];
    }

    public function continueOrdering(): void
    {
        $this->reviewingOrder = false;
    }

    public function setReviewedQuantity(int $productId, mixed $quantity): void
    {
        if (! is_numeric($quantity)) {
            return;
        }

        $this->changeLineQuantity($productId, (int) $quantity);
    }

    public function changeLineQuantity(int $productId, int $quantity): void
    {
        if ($quantity < 1) {
            $this->removeLine($productId);

            return;
        }

        $this->addLine($productId, min($quantity, FarmadocOrderDraft::MAX_QUANTITY), notify: false);
    }

    public function removeLine(int $productId): void
    {
        $this->orderLines = array_values(array_filter(
            $this->orderLines,
            fn (array $line): bool => (int) $line['product_id'] !== $productId,
        ));
    }

    public function addLine(int $productId, int $quantity, bool $notify = true): void
    {
        $medication = $this->findMedication($productId);

        if ($medication === null) {
            Notification::make()
                ->title('Ese medicamento ya no está en el catálogo')
                ->body('Actualice el listado e intente de nuevo.')
                ->danger()
                ->send();

            return;
        }

        if ($quantity < 1 || $quantity > FarmadocOrderDraft::MAX_QUANTITY) {
            Notification::make()
                ->title('Cantidad no válida')
                ->body('Indique una cantidad entre 1 y '.FarmadocOrderDraft::MAX_QUANTITY.'.')
                ->danger()
                ->send();

            return;
        }

        $exists = $this->lineQuantity($productId) !== null;

        if (! $exists && count($this->orderLines) >= FarmadocOrderDraft::MAX_LINES) {
            Notification::make()
                ->title('El pedido ya tiene '.FarmadocOrderDraft::MAX_LINES.' medicamentos')
                ->body('Envíe este pedido y arme otro.')
                ->warning()
                ->send();

            return;
        }

        $line = [
            'product_id' => $productId,
            'name' => (string) $medication['name'],
            'presentation' => is_string($medication['presentation'] ?? null) ? $medication['presentation'] : null,
            'quantity' => $quantity,
        ];

        if ($exists) {
            foreach ($this->orderLines as $index => $current) {
                if ((int) $current['product_id'] === $productId) {
                    $this->orderLines[$index] = $line;
                    break;
                }
            }
        } else {
            $this->orderLines[] = $line;
        }

        if (! $notify) {
            return;
        }

        Notification::make()
            ->title($exists ? 'Cantidad actualizada' : 'Agregado al pedido')
            ->body($line['name'].' · '.$quantity)
            ->success()
            ->send();
    }

    public function placeOrder(?int $ubicationId = null): void
    {
        $ubications = FarmadocInventoryReceipt::ubicationOptions();

        if ($ubications !== [] && ($ubicationId === null || ! isset($ubications[$ubicationId]))) {
            Notification::make()
                ->title('No se envió el pedido')
                ->body('Elija la ubicación (DIAGNO-MOVIL o DIAGNO-CENTER) que hace el pedido.')
                ->warning()
                ->send();

            return;
        }

        $this->ubicationName = $ubicationId !== null ? ($ubications[$ubicationId] ?? null) : null;

        try {
            $catalog = $this->medications();
            $items = FarmadocOrderDraft::items($this->orderLines, $catalog);
            $orderNumber = 'IC-'.($this->coordinationId ?? 0).'-'.now()->format('YmdHis').'-'.random_int(1000, 9999);

            $placed = app(FarmadocAllyClient::class)->placeOrder(
                $items,
                $this->branchId,
                $this->orderNotes(),
                $orderNumber,
                $this->serviceOrderContext(),
            );
        } catch (FarmadocException $exception) {
            Notification::make()
                ->title('No se envió el pedido')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('No se envió el pedido')
                ->body('Ocurrió un error al enviarlo. Intente de nuevo.')
                ->danger()
                ->send();

            return;
        }

        try {
            FarmadocOrderTracker::record(
                $placed,
                $items,
                $this->coordinationId,
                $this->coordinationReference,
                $this->patientName,
                Auth::user(),
                $ubicationId,
            );

            if ($ubicationId !== null) {
                session()->put(self::LAST_UBICATION_SESSION_KEY, $ubicationId);
            }
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Pedido enviado, pero no quedó en el historial')
                ->body('Farmadoc recibió el pedido '.$placed['order_number'].'. Anote el número: no aparecerá en «Pedidos a Farmadoc».')
                ->warning()
                ->persistent()
                ->send();
        }

        $this->placedOrders[] = [
            'order_number' => $placed['order_number'],
            'items_count' => $placed['items_count'],
            'status_label' => $placed['status_label'],
        ];
        $this->orderLines = [];
        $this->reviewingOrder = false;

        $serviceOrderNumber = is_string($placed['service_order_number'] ?? null) ? $placed['service_order_number'] : null;

        if ($serviceOrderNumber === null) {
            Notification::make()
                ->title('Pedido enviado sin orden de servicio')
                ->body('Farmadoc recibió '.$placed['order_number'].', pero no confirmó la orden de servicio. Avise a Farmadoc antes de enviarlo otra vez.')
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('Pedido enviado a Farmadoc')
            ->body($placed['order_number'].' quedó '.$placed['status_label'].'. Orden de servicio '.$serviceOrderNumber.'.')
            ->success()
            ->send();
    }

    public function refreshCatalog(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->loadCatalog(refresh: true);
        $this->flushCachedTableRecords();

        if ($this->catalogError !== null) {
            Notification::make()
                ->title('No se pudo actualizar el catálogo')
                ->body($this->catalogError)
                ->danger()
                ->send();

            return;
        }

        if ($this->catalogNotice !== null) {
            Notification::make()
                ->title('Farmadoc no respondió')
                ->body('Sigue disponible el último catálogo consultado. Intente actualizar de nuevo en unos minutos.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Catálogo actualizado')
            ->success()
            ->send();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function medications(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && is_array($cached['medications'] ?? null)) {
            return $cached['medications'];
        }

        if ($this->catalogAttempted) {
            return [];
        }

        $this->loadCatalog(refresh: true);
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) && is_array($cached['medications'] ?? null) ? $cached['medications'] : [];
    }

    private function loadCatalog(bool $refresh = false): void
    {
        $this->catalogAttempted = true;

        if (! $refresh) {
            $cached = Cache::get(self::CACHE_KEY);

            if (is_array($cached) && is_array($cached['medications'] ?? null)) {
                $this->applyCatalog($cached);

                return;
            }
        }

        try {
            $catalog = app(FarmadocAllyClient::class)->catalog();
            $catalog['fetched_at'] = now()->toIso8601String();
            Cache::put(self::CACHE_KEY, $catalog, now()->addMinutes(self::CACHE_MINUTES));
            Cache::put(self::STALE_CACHE_KEY, $catalog, now()->addHours(self::STALE_CACHE_HOURS));
            $this->applyCatalog($catalog);
            $this->catalogError = null;
            $this->catalogNotice = null;
        } catch (FarmadocException $exception) {
            $stale = Cache::get(self::STALE_CACHE_KEY);

            if (! is_array($stale) || ! is_array($stale['medications'] ?? null) || $stale['medications'] === []) {
                $this->catalogError = $exception->getMessage();

                return;
            }

            // La copia vuelve a la caché corta: así cada clic no reintenta la API caída.
            Cache::put(self::CACHE_KEY, $stale, now()->addMinutes(2));
            $this->applyCatalog($stale);
            $this->catalogError = null;
            $this->catalogNotice = 'No fue posible conectar con Farmadoc ('.$exception->getMessage().'). Se muestra el catálogo consultado '.$this->fetchedAtLabel($stale).'; pulse «Actualizar catálogo» para reintentar.';
        }
    }

    /**
     * @param  array<string, mixed>  $catalog
     */
    private function applyCatalog(array $catalog): void
    {
        $this->branchId = is_numeric($catalog['branch_id'] ?? null) ? (int) $catalog['branch_id'] : null;
        $this->branchName = is_string($catalog['branch_name'] ?? null) ? $catalog['branch_name'] : null;
        $this->logoUrl = is_string($catalog['logo_url'] ?? null) ? $catalog['logo_url'] : null;
        $this->catalogError = null;
    }

    /**
     * @param  array<string, mixed>  $catalog
     */
    private function fetchedAtLabel(array $catalog): string
    {
        try {
            return is_string($catalog['fetched_at'] ?? null)
                ? 'el '.\Illuminate\Support\Carbon::parse($catalog['fetched_at'])->timezone((string) config('app.timezone'))->format('d/m/Y \a \l\a\s h:i a')
                : 'anteriormente';
        } catch (Throwable) {
            return 'anteriormente';
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findMedication(int $productId): ?array
    {
        foreach ($this->medications() as $medication) {
            if ((int) ($medication['product_id'] ?? 0) === $productId) {
                return $medication;
            }
        }

        return null;
    }

    private function lineQuantity(int $productId): ?int
    {
        foreach ($this->orderLines as $line) {
            if ((int) $line['product_id'] === $productId) {
                return (int) $line['quantity'];
            }
        }

        return null;
    }

    private function resolveCoordination(): void
    {
        $raw = request()->query('coordinacion');

        if ($raw === null || $raw === '') {
            return;
        }

        if (! ctype_digit((string) $raw)) {
            abort(404);
        }

        $record = OperationsSupplierScope::applyCoordinationListScope(OperationCoordinationService::query())
            ->whereKey((int) $raw)
            ->first();

        abort_unless($record instanceof OperationCoordinationService, 404);

        $this->coordinationId = (int) $record->getKey();
        $reference = is_string($record->reference_number) && trim($record->reference_number) !== ''
            ? trim($record->reference_number)
            : (string) $record->getKey();
        $patient = is_string($record->patient) ? trim($record->patient) : '';
        $this->coordinationReference = $reference;
        $this->patientName = $patient !== '' ? $patient : null;
        $this->coordinationLabel = 'Coordinación '.$reference.($patient !== '' ? ' · '.$patient : '').'.';
    }

    /**
     * Datos clínicos que Farmadoc necesita para abrir la orden de servicio del pedido.
     * Los campos que la coordinación no tiene viajan vacíos: Farmadoc igual abre la orden.
     *
     * @return array<string, mixed>
     */
    private function serviceOrderContext(): array
    {
        $coordination = $this->coordinationId === null
            ? null
            : OperationCoordinationService::query()
                ->with('telemedicinePatient')
                ->find($this->coordinationId);

        $patient = $coordination?->telemedicinePatient;
        $email = is_string($patient?->email) ? trim($patient->email) : '';

        return [
            'patient_name' => $this->patientName ?? (is_string($coordination?->patient) ? trim($coordination->patient) : null),
            'patient_document' => $this->textOrNull($coordination?->ci_patient) ?? $this->textOrNull($patient?->nro_identificacion),
            'patient_phone' => $this->textOrNull($coordination?->phone_holder) ?? $this->textOrNull($patient?->phone),
            'patient_email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            'diagnosis' => $this->textOrNull($coordination?->symptoms_diagnosis),
            'external_reference' => $this->coordinationReference,
            'service_type' => 'medicamentos',
            'status' => 'en-proceso',
            'priority' => 'media',
        ];
    }

    /**
     * La última ubicación que usó el analista, o la única si solo hay una.
     */
    private function defaultUbicationId(): ?int
    {
        $options = FarmadocInventoryReceipt::ubicationOptions();
        $last = session()->get(self::LAST_UBICATION_SESSION_KEY);

        if (is_numeric($last) && isset($options[(int) $last])) {
            return (int) $last;
        }

        return count($options) === 1 ? (int) array_key_first($options) : null;
    }

    private function textOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function orderNotes(): string
    {
        $analyst = Auth::user()?->name;
        $notes = 'Pedido de medicamentos desde IntegraCorp.';

        if (is_string($this->coordinationLabel) && $this->coordinationLabel !== '') {
            $notes .= ' '.$this->coordinationLabel;
        }

        if (is_string($this->ubicationName) && $this->ubicationName !== '') {
            $notes .= ' Para: '.$this->ubicationName.'.';
        }

        if (is_string($analyst) && trim($analyst) !== '') {
            $notes .= ' Analista: '.trim($analyst).'.';
        }

        return $notes;
    }

    private function orderPreview(): HtmlString
    {
        $items = '';

        foreach ($this->orderLines as $line) {
            $presentation = is_string($line['presentation'] ?? null) && $line['presentation'] !== ''
                ? ' · '.e($line['presentation'])
                : '';
            $items .= '<li class="flex items-baseline justify-between gap-4 border-b border-gray-100 py-2 last:border-0 dark:border-white/10">'
                .'<span>'.e((string) $line['name']).$presentation.'</span>'
                .'<span class="shrink-0 font-semibold">'.(int) $line['quantity'].'</span>'
                .'</li>';
        }

        return new HtmlString('<ul class="text-sm">'.$items.'</ul>');
    }
}
