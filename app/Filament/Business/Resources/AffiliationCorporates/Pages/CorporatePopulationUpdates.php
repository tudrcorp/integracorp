<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporates\Pages;

use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Jobs\AnalyzeCorporatePopulationUpdateJob;
use App\Models\AffiliationCorporate;
use App\Models\CorporatePopulationTemplate;
use App\Models\CorporatePopulationUpdate;
use App\Models\CorporatePopulationUpdateItem as Item;
use App\Models\User;
use App\Support\AffiliationCorporates\Population\PopulationColumnMapping;
use App\Support\AffiliationCorporates\Population\PopulationFileReader;
use App\Support\AffiliationCorporates\Population\PopulationUpdateUrls;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\FilamentIosButton;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * «Actualizar población» de una afiliación corporativa: historial de cargas y
 * carga de un archivo nuevo. El análisis corre en cola y no cambia afiliados;
 * aprobar y revertir viven en {@see CorporatePopulationUpdateDetail}.
 */
class CorporatePopulationUpdates extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    public const DISK = 'local';

    protected static string $resource = AffiliationCorporateResource::class;

    protected static ?string $title = 'Actualizar población';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.business.resources.affiliation-corporates.pages.population-updates';

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function getRoutePath(Panel $panel): string
    {
        return '/{record}/poblacion';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return AffiliationCorporateResource::canAccess()
            && BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::UPDATE_CORPORATE_POPULATION);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(static::canAccess(), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Actualizar población · '.($this->owner()->name_corporate ?: $this->owner()->code);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Suba el archivo de población que envió la empresa. El sistema lo compara con los afiliados y le muestra qué agregar, reactivar, dar de baja o corregir. Nada cambia hasta que usted lo aprueba con la clave OTP, y cada carga se puede revertir.';
    }

    public function owner(): AffiliationCorporate
    {
        /** @var AffiliationCorporate $owner */
        $owner = $this->getRecord();

        return $owner;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver a la afiliación')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => AffiliationCorporateResource::getUrl('view', ['record' => $this->owner()->getKey()])),
            $this->newUploadAction(),
        ];
    }

    protected function newUploadAction(): Action
    {
        return Action::make('newUpload')
            ->label('Nueva carga')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('primary')
            ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor('primary')])
            ->modalHeading('Nueva carga de población')
            ->modalDescription('El archivo debe traer la POBLACIÓN COMPLETA de la empresa: quien no aparezca se propondrá para dar de baja (usted decide antes de aplicar).')
            ->modalWidth(Width::FourExtraLarge)
            ->steps([
                Step::make('Archivo')
                    ->description('Excel, CSV o TXT')
                    ->icon(Heroicon::OutlinedDocumentArrowUp)
                    ->schema([
                        FileUpload::make('file')
                            ->label('Archivo de población')
                            ->helperText('Excel (.xlsx), CSV o TXT de hasta 15 MB. La primera fila deben ser los encabezados; solo se lee la primera hoja.')
                            ->disk(self::DISK)
                            ->directory(fn (): string => 'corporate-population/'.$this->owner()->getKey())
                            ->visibility('private')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'text/x-csv', 'text/tab-separated-values',
                            ])
                            ->maxSize(15 * 1024)
                            ->storeFileNamesIn('file_name')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                $this->readUploadedHeaders($state, $set);
                            }),
                        Hidden::make('headers_json'),
                        Placeholder::make('file_status')
                            ->hiddenLabel()
                            ->visible(fn (Get $get): bool => filled($get('headers_json')) || filled($get('file_error')))
                            ->content(fn (Get $get): HtmlString => new HtmlString(filled($get('file_error'))
                                ? '<p class="text-sm font-medium text-danger-600 dark:text-danger-400">'.e((string) $get('file_error')).'</p>'
                                : '<p class="text-sm text-success-700 dark:text-success-400">Archivo leído: '.count((array) json_decode((string) $get('headers_json'), true)).' columnas. Continúe para confirmar qué es cada una.</p>')),
                        Hidden::make('file_error'),
                    ]),
                Step::make('Columnas')
                    ->description('Qué es cada columna')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->schema(fn (): array => [
                        Placeholder::make('mapping_hint')
                            ->hiddenLabel()
                            ->content(fn (): string => CorporatePopulationTemplate::query()->where('affiliation_corporate_id', $this->owner()->getKey())->exists()
                                ? 'Se precargó la plantilla guardada de esta empresa. Revise y continúe.'
                                : 'Primera carga de esta empresa: confirme qué columna es cada dato. Se guardará como plantilla para la próxima vez.'),
                        Grid::make(2)->schema(array_map(
                            fn (string $field, array $definition): Select => Select::make('mapping.'.$field)
                                ->label($definition['label'])
                                ->helperText($definition['help'])
                                ->placeholder($definition['required'] ? 'Elija la columna' : '— No viene en el archivo —')
                                ->options(fn (Get $get): array => $this->headerOptions($get('headers_json')))
                                ->required($definition['required'])
                                ->native(false)
                                ->searchable(),
                            array_keys(PopulationColumnMapping::fields()),
                            PopulationColumnMapping::fields(),
                        )),
                    ]),
            ])
            ->action(function (array $data): void {
                $this->startAnalysis($data);
            });
    }

    protected function readUploadedHeaders(mixed $state, Set $set): void
    {
        $file = is_array($state) ? (array_values($state)[0] ?? null) : $state;
        $set('headers_json', null);
        $set('file_error', null);

        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        try {
            $extension = strtolower($file->getClientOriginalExtension());
            $headers = PopulationFileReader::headers($file->getRealPath(), $extension);
        } catch (Throwable $exception) {
            $set('file_error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'No se pudo leer el archivo.');

            return;
        }

        $set('headers_json', json_encode($headers, JSON_UNESCAPED_UNICODE));

        $template = CorporatePopulationTemplate::query()->where('affiliation_corporate_id', $this->owner()->getKey())->value('mapping');
        foreach (PopulationColumnMapping::suggest($headers, is_array($template) ? $template : null) as $field => $header) {
            $set('mapping.'.$field, $header);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function headerOptions(mixed $json): array
    {
        $headers = json_decode((string) $json, true);

        return is_array($headers) ? array_combine($headers, $headers) : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function startAnalysis(array $data): void
    {
        abort_unless(static::canAccess(), 403);

        $user = Auth::user();
        $owner = $this->owner();
        $path = is_array($data['file'] ?? null) ? (string) array_values($data['file'])[0] : (string) ($data['file'] ?? '');
        $name = is_array($data['file_name'] ?? null) ? (string) array_values($data['file_name'])[0] : (string) ($data['file_name'] ?? basename($path));

        try {
            if ($path === '' || ! Storage::disk(self::DISK)->exists($path)) {
                throw new \RuntimeException('No se recibió el archivo. Súbalo de nuevo.');
            }

            $format = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: pathinfo($path, PATHINFO_EXTENSION));
            $headers = PopulationFileReader::headers(Storage::disk(self::DISK)->path($path), $format);
            $mapping = PopulationColumnMapping::validate((array) ($data['mapping'] ?? []), $headers);
        } catch (Throwable $exception) {
            Notification::make()
                ->title('No se pudo iniciar el análisis')
                ->body($exception instanceof \RuntimeException || $exception instanceof \InvalidArgumentException ? $exception->getMessage() : 'Revise el archivo e intente de nuevo.')
                ->danger()
                ->send();

            return;
        }

        $sha1 = (string) sha1_file(Storage::disk(self::DISK)->path($path));

        $update = CorporatePopulationUpdate::query()->create([
            'public_id' => (string) Str::uuid(),
            'affiliation_corporate_id' => $owner->getKey(),
            'status' => CorporatePopulationUpdate::STATUS_ANALYZING,
            'mode' => CorporatePopulationUpdate::MODE_FULL,
            'file_disk' => self::DISK,
            'file_path' => $path,
            'file_name' => mb_substr($name, 0, 255),
            'file_sha1' => $sha1,
            'file_format' => $format,
            'mapping' => $mapping,
            'analyzed_by' => $user?->getKey(),
            'analyzed_by_name' => $user instanceof User ? (string) $user->name : null,
        ]);

        CorporatePopulationTemplate::query()->updateOrCreate(
            ['affiliation_corporate_id' => $owner->getKey()],
            ['mapping' => $mapping, 'headers' => $headers, 'updated_by' => $user instanceof User ? (string) $user->name : null],
        );

        SecurityAudit::log('AUDIT_CORPORATE_POPULATION_UPLOADED', 'business.corporate-population.upload', [
            'update_id' => $update->getKey(),
            'affiliation_code' => $owner->code,
            'file' => $update->file_name,
            'sha1' => $sha1,
            'mapping' => $mapping,
        ]);

        $alreadyApplied = CorporatePopulationUpdate::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->whereKeyNot($update->getKey())
            ->where('file_sha1', $sha1)
            ->where('status', CorporatePopulationUpdate::STATUS_APPLIED)
            ->latest('applied_at')
            ->first();

        AnalyzeCorporatePopulationUpdateJob::dispatch((int) $update->getKey());

        Notification::make()
            ->title('Analizando el archivo')
            ->body('Le avisaremos cuando el resumen esté listo. Nada cambia hasta que usted lo apruebe.'
                .($alreadyApplied !== null ? ' Atención: este mismo archivo ya se aplicó el '.$alreadyApplied->applied_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i').'.' : ''))
            ->{$alreadyApplied !== null ? 'warning' : 'success'}()
            ->send();

        $url = PopulationUpdateUrls::detail($update);
        if ($url !== null) {
            $this->redirect($url, navigate: true);
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => CorporatePopulationUpdate::query()->where('affiliation_corporate_id', $this->owner()->getKey()))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Cargada')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (CorporatePopulationUpdate $record): ?string => $record->analyzed_by_name ? 'Por '.$record->analyzed_by_name : null)
                    ->sortable(),
                TextColumn::make('file_name')
                    ->label('Archivo')
                    ->wrap()
                    ->limit(40)
                    ->tooltip(fn (CorporatePopulationUpdate $record): string => (string) $record->file_name),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => CorporatePopulationUpdate::labelFor($state))
                    ->color(fn (string $state): string => CorporatePopulationUpdate::colorFor($state)),
                TextColumn::make('summary')
                    ->label('Resumen')
                    ->state(fn (CorporatePopulationUpdate $record): string => self::summaryLine($record))
                    ->wrap(),
                TextColumn::make('approved_by_name')
                    ->label('Aplicada')
                    ->placeholder('—')
                    ->description(fn (CorporatePopulationUpdate $record): ?string => $record->applied_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i')),
                TextColumn::make('rolled_back_by_name')
                    ->label('Revertida')
                    ->placeholder('—')
                    ->description(fn (CorporatePopulationUpdate $record): ?string => $record->rolled_back_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i'))
                    ->toggleable(),
            ])
            ->recordUrl(fn (CorporatePopulationUpdate $record): ?string => PopulationUpdateUrls::detail($record))
            ->recordActions([
                Action::make('open')
                    ->label('Abrir')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (CorporatePopulationUpdate $record): ?string => PopulationUpdateUrls::detail($record)),
            ])
            ->poll(fn (): ?string => CorporatePopulationUpdate::query()
                ->where('affiliation_corporate_id', $this->owner()->getKey())
                ->whereIn('status', [CorporatePopulationUpdate::STATUS_ANALYZING, CorporatePopulationUpdate::STATUS_APPLYING, CorporatePopulationUpdate::STATUS_ROLLING_BACK])
                ->exists() ? '4s' : null)
            ->emptyStateIcon(Heroicon::OutlinedUserGroup)
            ->emptyStateHeading('Todavía no hay cargas de población')
            ->emptyStateDescription('Use «Nueva carga» para subir el archivo que envió la empresa.')
            ->paginated([10, 25]);
    }

    public static function summaryLine(CorporatePopulationUpdate $record): string
    {
        $counts = (array) ($record->summary['counts'] ?? []);
        if ($counts === []) {
            return $record->status === CorporatePopulationUpdate::STATUS_FAILED ? (string) $record->error : '—';
        }

        $parts = [];
        foreach ([Item::ACTION_ADD => 'agregar', Item::ACTION_REACTIVATE => 'reactivar', Item::ACTION_DEACTIVATE => 'baja', Item::ACTION_FIX_DOCUMENT => 'cédula', Item::ACTION_REVIEW => 'revisar', Item::ACTION_BLOCKED_OPEN_CASE => 'bloqueados'] as $action => $label) {
            if (($counts[$action] ?? 0) > 0) {
                $parts[] = $counts[$action].' '.$label;
            }
        }

        return $parts === [] ? 'Sin cambios: coincide con la base' : implode(' · ', $parts);
    }
}
