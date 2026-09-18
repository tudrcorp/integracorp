<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Pages;

use App\Filament\Actions\ImportAction;
use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Business\Resources\PlanGenerators\PlanGeneratorResource;
use App\Filament\Imports\PlanGeneratorPopulationImporter;
use App\Models\PlanGenerator;
use App\Support\Filament\FilamentIosButton;
use App\Support\PlanGenerators\PlanGeneratorPopulationStatus;
use App\Support\PlanGenerators\PlanGeneratorPreAffiliationSession;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\File;

/**
 * Paso 2 de la pre-afiliación corporativa desde el generador de planes: cargar
 * la población por importación, igual que el padrón de una cotización
 * corporativa en Negocios.
 *
 * El padrón se guarda colgado del plan generado y no se copia a la afiliación
 * hasta que el analista la crea. El botón que continúa al formulario solo se
 * habilita cuando hay población y el import cerró: crear la afiliación con el
 * padrón a medio importar dejaría afiliados fuera sin aviso.
 */
class PreAffiliationPopulation extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = PlanGeneratorResource::class;

    protected static ?string $title = 'Población de la pre-afiliación';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.business.resources.plan-generators.pages.pre-affiliation-population';

    public static function getRoutePath(Panel $panel): string
    {
        return '/{record}/pre-affiliation-population';
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);

        $this->ensureCorporateSession();
    }

    /**
     * Sin sesión corporativa activa para este plan no hay coberturas elegidas y
     * la afiliación no podría armarse. Se devuelve al analista a la ficha para
     * que pase por «Aprobar cotización».
     */
    protected function ensureCorporateSession(): void
    {
        /** @var PlanGenerator $plan */
        $plan = $this->getRecord();

        $payload = PlanGeneratorPreAffiliationSession::get();
        $activePlanId = is_array($payload) ? (int) ($payload['plan_generator_id'] ?? 0) : 0;
        $type = is_array($payload) ? ($payload['type'] ?? null) : null;

        if ($type === PlanGeneratorPreAffiliationSession::TYPE_CORPORATE && $activePlanId === (int) $plan->getKey()) {
            return;
        }

        Notification::make()
            ->title('Seleccione primero las coberturas')
            ->body('Abra «Aprobar cotización» y elija las coberturas del grupo corporativo antes de cargar la población.')
            ->warning()
            ->send();

        $this->redirect(PlanGeneratorResource::getUrl('view', ['record' => $plan->getKey()]));
    }

    public function getTitle(): string|Htmlable
    {
        return 'Población de la pre-afiliación';
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var PlanGenerator $plan */
        $plan = $this->getRecord();

        return 'Paso 2 de 2 · '.((string) $plan->name).' · Nro. Control '.((string) $plan->control_number);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make('importPopulation')
                ->label('Importar población')
                ->icon('fluentui-database-arrow-up-20')
                ->color('success')
                ->modalHeading('Importar población')
                ->modalDescription('Mismo archivo que el padrón de cotizaciones corporativas. Separador punto y coma (;), máximo 5 MB.')
                ->importer(PlanGeneratorPopulationImporter::class)
                ->csvDelimiter(';')
                ->job(ImportCsv::class)
                ->chunkSize(100)
                ->options(fn (): array => [
                    'plan_generator_id' => $this->getRecord()->getKey(),
                ])
                ->fileRules([
                    File::types(['csv', 'txt'])->max(5120),
                ])
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                ]),
            Action::make('clearPopulation')
                ->label('Vaciar población')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Vaciar la población cargada')
                ->modalDescription('Se borran todas las filas importadas de este plan generado. La afiliación corporativa todavía no existe, así que no se pierde ninguna afiliación; habrá que volver a importar el padrón.')
                ->modalSubmitActionLabel('Vaciar población')
                ->visible(fn (): bool => PlanGeneratorPopulationStatus::importedCount($this->getRecord()) > 0)
                ->action(function (): void {
                    /** @var PlanGenerator $plan */
                    $plan = $this->getRecord();

                    $deleted = $plan->populations()->delete();
                    $plan->forceFill(['population_import_id' => null])->save();

                    SecurityAudit::log('AUDIT_BUSINESS_PLAN_GENERATOR_POPULATION_CLEARED', 'business.plan-generators.population.clear', [
                        'plan_generator_id' => $plan->getKey(),
                        'deleted_rows' => $deleted,
                    ]);

                    Notification::make()
                        ->title('Población vaciada')
                        ->body($deleted.' fila(s) eliminada(s). Importe el padrón corregido para continuar.')
                        ->success()
                        ->send();
                })
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('danger'),
                ]),
            Action::make('continueToAffiliation')
                ->label('Continuar a la pre-afiliación')
                ->icon(Heroicon::OutlinedArrowRight)
                ->color('primary')
                ->disabled(fn (): bool => ! PlanGeneratorPopulationStatus::canContinue($this->getRecord()))
                ->tooltip(fn (): ?string => PlanGeneratorPopulationStatus::blockedReason($this->getRecord()))
                ->action(function (): void {
                    /** @var PlanGenerator $plan */
                    $plan = $this->getRecord();

                    if (! PlanGeneratorPopulationStatus::canContinue($plan)) {
                        Notification::make()
                            ->title('Todavía no se puede continuar')
                            ->body((string) PlanGeneratorPopulationStatus::blockedReason($plan))
                            ->warning()
                            ->send();

                        return;
                    }

                    SecurityAudit::log('AUDIT_BUSINESS_PLAN_GENERATOR_POPULATION_READY', 'business.plan-generators.population.continue', [
                        'plan_generator_id' => $plan->getKey(),
                        'imported_rows' => PlanGeneratorPopulationStatus::importedCount($plan),
                        'declared_population' => PlanGeneratorPopulationStatus::declaredPopulation(),
                    ]);

                    $this->redirect(AffiliationCorporateResource::getUrl('create', panel: 'business'));
                })
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ]),
            Action::make('backToPlan')
                ->label('Volver al plan')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => PlanGeneratorResource::getUrl('view', ['record' => $this->getRecord()->getKey()]))
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                ]),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->getRecord()->populations()->getQuery())
            ->heading('Población cargada')
            ->description('Filas importadas para esta pre-afiliación. Se copiarán como afiliados corporativos al crear la afiliación.')
            ->emptyStateHeading('Sin población cargada')
            ->emptyStateDescription('Use «Importar población» para subir el padrón en CSV.')
            ->emptyStateIcon(Heroicon::OutlinedUsers)
            ->striped()
            ->defaultSort('last_name')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50, 100])
            ->poll('5s')
            ->columns([
                TextColumn::make('last_name')
                    ->label('Apellido')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nro_identificacion')
                    ->label('Cédula')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Nacimiento')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('age')
                    ->label('Edad')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('sex')
                    ->label('Sexo')
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('position_company')
                    ->label('Cargo')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->label('Correo')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            'fi-plan-generator-pre-affiliation-population-page',
            'fi-resource-'.str_replace('/', '-', static::getResource()::getSlug()),
            'fi-resource-record-'.$this->getRecord()->getKey(),
        ];
    }
}
