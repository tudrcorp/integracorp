<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\TelemedicineListLaboratory;
use App\Models\TelemedicineListSpecialist;
use App\Models\TelemedicineListStudy;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Edición de un ítem clínico asociado a una coordinación.
 *
 * Solo aplica a ítems en estatus PENDIENTE y sin orden de servicio emitida, con
 * nota restrictiva obligatoria que queda en las observaciones de la coordinación
 * y en la bitácora del caso de telemedicina.
 */
final class CoordinationServiceItemEdit
{
    public const OBSERVATION_PREFIX = 'Edición de ítem asociado';

    /**
     * Configuración por tipo de ítem: modelo, columna del nombre, categoría usada
     * para cruzar contra órdenes de servicio y catálogo de origen del nombre.
     *
     * Los medicamentos no tienen catálogo porque su nombre proviene del inventario
     * y repuntarlo alteraría cobertura y deducción de existencias.
     *
     * @var array<string, array{model: class-string<Model>, name_column: string, name_label: string, category: string, catalog: class-string<Model>|null}>
     */
    private const ITEM_TYPES = [
        'medication' => [
            'model' => TelemedicinePatientMedications::class,
            'name_column' => 'medicine',
            'name_label' => 'Medicamento',
            'category' => 'Medicamento',
            'catalog' => null,
        ],
        'lab' => [
            'model' => TelemedicinePatientLab::class,
            'name_column' => 'laboratory',
            'name_label' => 'Examen de laboratorio',
            'category' => 'Laboratorio',
            'catalog' => TelemedicineListLaboratory::class,
        ],
        'study' => [
            'model' => TelemedicinePatientStudy::class,
            'name_column' => 'study',
            'name_label' => 'Estudio de imagenología',
            'category' => 'Estudio',
            'catalog' => TelemedicineListStudy::class,
        ],
        'specialty' => [
            'model' => TelemedicinePatientSpecialty::class,
            'name_column' => 'specialty',
            'name_label' => 'Especialidad',
            'category' => 'Especialista',
            'catalog' => TelemedicineListSpecialist::class,
        ],
    ];

    /**
     * Solo se edita lo que todavía no arrancó. En PENDIENTE el estatus efectivo
     * coincide con el de base de datos (el efectivo solo difiere en EN GESTION),
     * así que no hay ambigüedad entre lo que ve el analista y lo almacenado.
     */
    public static function statusIsEditable(string $status): bool
    {
        return mb_strtoupper(trim($status)) === 'PENDIENTE';
    }

    /**
     * El vínculo ítem ↔ orden de servicio se resuelve por nombre, así que
     * renombrar un ítem con orden emitida lo dejaría huérfano.
     *
     * @param  array<string, array{id: int, order_number: string, status: string, url: string}>  $orderLinks
     */
    public static function itemHasServiceOrder(array $orderLinks, string $category, string $label): bool
    {
        return isset($orderLinks[CoordinationServiceItemsManager::clinicalItemServiceOrderKey($category, $label)]);
    }

    public static function itemIsEditable(string $status, bool $hasServiceOrder): bool
    {
        return self::statusIsEditable($status) && ! $hasServiceOrder;
    }

    /**
     * @return class-string<Model>|null
     */
    public static function clinicalItemModelClass(string $type): ?string
    {
        return self::ITEM_TYPES[$type]['model'] ?? null;
    }

    public static function nameColumn(string $type): ?string
    {
        return self::ITEM_TYPES[$type]['name_column'] ?? null;
    }

    public static function categoryForType(string $type): ?string
    {
        return self::ITEM_TYPES[$type]['category'] ?? null;
    }

    /**
     * @return class-string<Model>|null
     */
    public static function catalogModelClass(string $type): ?string
    {
        return self::ITEM_TYPES[$type]['catalog'] ?? null;
    }

    public static function usesCatalog(string $type): bool
    {
        return self::catalogModelClass($type) !== null;
    }

    /**
     * @param  array{id?: int|string, item_type?: string, title?: string, status?: string, can_edit?: bool}  $row
     */
    public static function makeEditAction(array $row): ?Action
    {
        if (! ($row['can_edit'] ?? false)) {
            return null;
        }

        $itemId = (int) ($row['id'] ?? 0);
        $itemType = (string) ($row['item_type'] ?? '');
        $title = (string) ($row['title'] ?? 'Ítem');

        if ($itemId <= 0 || self::clinicalItemModelClass($itemType) === null) {
            return null;
        }

        return Action::make('editAssociatedItem_'.$itemType.'_'.$itemId)
            ->label('Editar ítem')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('warning')
            ->iconButton()
            ->tooltip('Editar ítem')
            ->modalHeading('Editar ítem asociado')
            ->modalDescription(fn (): HtmlString => new HtmlString(
                '<p class="text-sm text-gray-600 dark:text-gray-300">'
                .'Va a editar <span class="font-semibold text-gray-900 dark:text-white">'.e($title).'</span>. '
                .'Debe indicar el motivo; quedará registrado en las observaciones de la coordinación y en la bitácora del caso vinculado.'
                .'</p>'
            ))
            ->modalIcon(Heroicon::OutlinedPencilSquare)
            ->modalIconColor('warning')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Guardar cambios')
            ->modalCancelActionLabel('Volver')
            ->closeModalByClickingAway(false)
            ->fillForm(fn (OperationCoordinationService $record): array => self::formDefaults($record, $itemType, $itemId))
            ->form(self::formSchema($itemType))
            ->action(function (array $data, OperationCoordinationService $record) use ($itemId, $itemType, $title): void {
                self::edit($record, $itemType, $itemId, $title, $data);
            });
    }

    /**
     * @return array<int, \Filament\Forms\Components\Field>
     */
    public static function formSchema(string $itemType): array
    {
        $config = self::ITEM_TYPES[$itemType] ?? null;

        if ($config === null) {
            return [];
        }

        return [
            ...self::itemFields($itemType, $config),
            Textarea::make('edit_observation')
                ->label('Motivo de la edición')
                ->placeholder('Ej.: El médico corrigió la indicación, el examen se cargó con el nombre equivocado, cambio solicitado por el paciente…')
                ->helperText('Campo obligatorio. Mínimo 10 caracteres. Se guarda en observaciones de la coordinación y en la bitácora del caso.')
                ->required()
                ->minLength(10)
                ->maxLength(5000)
                ->rows(4)
                ->columnSpanFull()
                ->validationMessages([
                    'required' => 'Debes indicar el motivo de la edición.',
                    'minLength' => 'El motivo debe tener al menos 10 caracteres.',
                ]),
        ];
    }

    /**
     * @param  array{model: class-string<Model>, name_column: string, name_label: string, category: string, catalog: class-string<Model>|null}  $config
     * @return array<int, \Filament\Forms\Components\Field>
     */
    private static function itemFields(string $itemType, array $config): array
    {
        if ($itemType === 'medication') {
            return [
                TextInput::make('item_name')
                    ->label($config['name_label'])
                    ->helperText('El medicamento proviene del inventario y no se puede cambiar aquí: cancele el ítem y registre uno nuevo si debe sustituirlo.')
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),
                Textarea::make('indications')
                    ->label('Indicaciones')
                    ->placeholder('Ej.: 1 tableta cada 8 horas por 5 días.')
                    ->required()
                    ->minLength(3)
                    ->maxLength(5000)
                    ->rows(3)
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Debes indicar la posología o indicación del medicamento.',
                    ]),
            ];
        }

        return [
            Select::make('catalog_id')
                ->label($config['name_label'])
                ->helperText('La cobertura (CUBIERTO / NO CUBIERTO) se toma del catálogo, no se edita por separado.')
                ->options(fn (): array => self::catalogOptions($itemType))
                ->searchable()
                ->preload()
                ->required()
                ->columnSpanFull()
                ->validationMessages([
                    'required' => 'Debes seleccionar una opción del catálogo.',
                ]),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function catalogOptions(string $itemType): array
    {
        $catalog = self::catalogModelClass($itemType);

        if ($catalog === null) {
            return [];
        }

        return $catalog::query()
            ->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->mapWithKeys(fn (Model $option): array => [
                (int) $option->getKey() => self::catalogOptionLabel(
                    (string) ($option->getAttribute('name') ?? ''),
                    (string) ($option->getAttribute('type') ?? ''),
                ),
            ])
            ->all();
    }

    public static function catalogOptionLabel(string $name, string $type): string
    {
        $name = trim($name);
        $type = mb_strtoupper(trim($type));

        return $type !== '' ? $name.' ('.$type.')' : $name;
    }

    /**
     * @return array<string, mixed>
     */
    public static function formDefaults(OperationCoordinationService $coordination, string $itemType, int $itemId): array
    {
        $item = self::findItem($coordination, $itemType, $itemId);

        if ($item === null) {
            return [];
        }

        $nameColumn = self::nameColumn($itemType);
        $currentName = (string) ($item->getAttribute($nameColumn) ?? '');

        if ($itemType === 'medication') {
            return [
                'item_name' => $currentName,
                'indications' => (string) ($item->getAttribute('indications') ?? ''),
            ];
        }

        return [
            'catalog_id' => self::resolveCatalogId(
                $itemType,
                $currentName,
                (string) ($item->getAttribute('type') ?? ''),
            ),
        ];
    }

    /**
     * Empareja el valor guardado con el catálogo. Prioriza la coincidencia de
     * nombre + cobertura porque un mismo nombre puede existir como cubierto y no
     * cubierto; si no la hay, cae al nombre solo.
     */
    public static function resolveCatalogId(string $itemType, string $name, string $type): ?int
    {
        $catalog = self::catalogModelClass($itemType);
        $name = trim($name);

        if ($catalog === null || $name === '') {
            return null;
        }

        $matches = $catalog::query()
            ->whereRaw('UPPER(TRIM(name)) = ?', [mb_strtoupper($name)])
            ->get(['id', 'type']);

        if ($matches->isEmpty()) {
            return null;
        }

        $type = mb_strtoupper(trim($type));

        $exact = $matches->first(
            fn (Model $option): bool => mb_strtoupper(trim((string) ($option->getAttribute('type') ?? ''))) === $type
        );

        return (int) ($exact?->getKey() ?? $matches->first()->getKey());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function edit(
        OperationCoordinationService $coordination,
        string $itemType,
        int $itemId,
        string $itemTitle,
        array $data,
    ): void {
        $observationText = trim((string) ($data['edit_observation'] ?? ''));

        if (mb_strlen($observationText) < 10) {
            Notification::make()
                ->title('Motivo incompleto')
                ->body('El motivo de la edición debe tener al menos 10 caracteres.')
                ->danger()
                ->send();

            return;
        }

        if (self::clinicalItemModelClass($itemType) === null) {
            Notification::make()
                ->title('Ítem no válido')
                ->body('No se pudo identificar el tipo de ítem a editar.')
                ->danger()
                ->send();

            return;
        }

        $item = self::findItem($coordination, $itemType, $itemId);

        if ($item === null) {
            Notification::make()
                ->title('Ítem no encontrado')
                ->body('El ítem ya no está asociado a esta coordinación.')
                ->danger()
                ->send();

            return;
        }

        $currentStatus = mb_strtoupper(trim((string) ($item->getAttribute('status') ?? '')));

        if (! self::statusIsEditable($currentStatus)) {
            Notification::make()
                ->title('No se puede editar')
                ->body('Solo se pueden editar ítems en estado PENDIENTE. Estado actual: '.$currentStatus.'.')
                ->warning()
                ->send();

            return;
        }

        $nameColumn = (string) self::nameColumn($itemType);
        $category = (string) self::categoryForType($itemType);
        $currentName = (string) ($item->getAttribute($nameColumn) ?? '');

        if (self::itemHasServiceOrder(
            CoordinationServiceItemsManager::serviceOrderLinksByClinicalItemKey($coordination),
            $category,
            $currentName,
        )) {
            Notification::make()
                ->title('No se puede editar')
                ->body('El ítem ya tiene una orden de servicio emitida. Cancele la gestión si necesita modificarlo.')
                ->warning()
                ->send();

            return;
        }

        $changes = self::resolveChanges($item, $itemType, $nameColumn, $data);

        if ($changes === []) {
            Notification::make()
                ->title('Sin cambios')
                ->body('No se detectaron cambios en el ítem, no se registró nada en la bitácora.')
                ->warning()
                ->send();

            return;
        }

        $bitacoraDescription = self::buildBitacoraDescription($itemTitle, $changes, $observationText);
        $userId = Auth::id();
        $userName = (string) (Auth::user()?->name ?? 'SISTEMA');

        DB::transaction(function () use ($coordination, $item, $changes, $bitacoraDescription, $userId, $userName): void {
            $item->fill(array_map(
                static fn (array $change): mixed => $change['to'],
                $changes,
            ));
            $item->save();

            $previousObservations = trim((string) ($coordination->observations ?? ''));
            $coordination->observations = $previousObservations !== ''
                ? $previousObservations."\n\n".$bitacoraDescription
                : $bitacoraDescription;
            $coordination->updated_by = $userName;
            $coordination->save();

            if (filled($coordination->telemedicine_case_id)) {
                ObservationCase::query()->create([
                    'telemedicine_case_id' => $coordination->telemedicine_case_id,
                    'description' => $bitacoraDescription,
                    'created_by' => $userId !== null ? (string) $userId : null,
                ]);
            }
        });

        Notification::make()
            ->title('Ítem actualizado')
            ->body(
                filled($coordination->telemedicine_case_id)
                    ? 'Los cambios se guardaron y quedaron registrados en la coordinación y en la bitácora del caso.'
                    : 'Los cambios se guardaron y quedaron registrados en las observaciones de la coordinación.'
            )
            ->success()
            ->send();
    }

    /**
     * Cambios reales entre el ítem y lo enviado, indexados por columna.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{label: string, from: string, to: string}>
     */
    public static function resolveChanges(Model $item, string $itemType, string $nameColumn, array $data): array
    {
        if ($itemType === 'medication') {
            return self::changeIfDifferent(
                [],
                'indications',
                'Indicaciones',
                (string) ($item->getAttribute('indications') ?? ''),
                trim((string) ($data['indications'] ?? '')),
            );
        }

        $catalog = self::catalogModelClass($itemType);
        $catalogId = (int) ($data['catalog_id'] ?? 0);

        if ($catalog === null || $catalogId <= 0) {
            return [];
        }

        $option = $catalog::query()->whereKey($catalogId)->first(['id', 'name', 'type']);

        if ($option === null) {
            return [];
        }

        $changes = self::changeIfDifferent(
            [],
            $nameColumn,
            self::ITEM_TYPES[$itemType]['name_label'],
            (string) ($item->getAttribute($nameColumn) ?? ''),
            trim((string) ($option->getAttribute('name') ?? '')),
        );

        return self::changeIfDifferent(
            $changes,
            'type',
            'Cobertura',
            (string) ($item->getAttribute('type') ?? ''),
            mb_strtoupper(trim((string) ($option->getAttribute('type') ?? ''))),
        );
    }

    /**
     * @param  array<string, array{label: string, from: string, to: string}>  $changes
     * @return array<string, array{label: string, from: string, to: string}>
     */
    private static function changeIfDifferent(array $changes, string $column, string $label, string $from, string $to): array
    {
        if (trim($from) === trim($to)) {
            return $changes;
        }

        $changes[$column] = [
            'label' => $label,
            'from' => trim($from),
            'to' => $to,
        ];

        return $changes;
    }

    /**
     * @param  array<string, array{label: string, from: string, to: string}>  $changes
     */
    public static function buildBitacoraDescription(string $itemTitle, array $changes, string $observationText): string
    {
        $lines = self::OBSERVATION_PREFIX."\n"
            .'Ítem: '.trim($itemTitle)."\n"
            .'Cambios:';

        foreach ($changes as $change) {
            $lines .= "\n".'- '.$change['label'].': '
                .($change['from'] !== '' ? $change['from'] : '—')
                .' → '
                .($change['to'] !== '' ? $change['to'] : '—');
        }

        return $lines."\n".'Motivo: '.trim($observationText);
    }

    private static function findItem(OperationCoordinationService $coordination, string $itemType, int $itemId): ?Model
    {
        $modelClass = self::clinicalItemModelClass($itemType);

        if ($modelClass === null) {
            return null;
        }

        return $modelClass::query()
            ->where('operation_coordination_service_id', $coordination->id)
            ->whereKey($itemId)
            ->first();
    }
}
