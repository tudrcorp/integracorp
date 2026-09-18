<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Pages;

use App\Filament\Business\Resources\Affiliations\AffiliationResource;
use App\Filament\Business\Resources\Helpdesks\Actions\HelpdeskTicketModalActions;
use App\Filament\Business\Resources\PlanGenerators\PlanGeneratorResource;
use App\Models\PlanGenerator;
use App\Support\PlanGenerators\PlanGeneratorPreAffiliationOptions;
use App\Support\PlanGenerators\PlanGeneratorPreAffiliationSession;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ViewPlanGenerator extends ViewRecord
{
    protected static string $resource = PlanGeneratorResource::class;

    private const IOS_BUTTON_BASE = ' shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray'.self::IOS_BUTTON_BASE;

    private const IOS_PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary'.self::IOS_BUTTON_BASE;

    private const IOS_SUCCESS_BUTTON_CLASS = 'aviso-btn-ios-success'.self::IOS_BUTTON_BASE;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approveQuote')
                ->label('Aprobar cotización')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->extraAttributes([
                    'class' => self::IOS_SUCCESS_BUTTON_CLASS,
                ])
                ->modalHeading('Aprobar cotización')
                ->modalDescription('Seleccione el destino de la pre-afiliación para continuar.')
                ->modalWidth(Width::TwoExtraLarge)
                ->extraModalWindowAttributes([
                    'class' => 'fi-plan-pre-affiliation-modal',
                ])
                ->modalContent(fn (): \Illuminate\Contracts\View\View => view('filament.business.plan-generators.pre-affiliation-modal', [
                    'plan' => $this->getRecord(),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelAction(
                    fn (Action $action): Action => $action
                        ->label('Cancelar')
                        ->color('gray')
                        ->extraAttributes([
                            'class' => self::IOS_GRAY_BUTTON_CLASS,
                        ]),
                )
                ->visible(fn (): bool => strtoupper((string) ($this->getRecord()->status ?? '')) === 'PRE-APROBADO'),
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(PlanGeneratorResource::getUrl())
                ->extraAttributes([
                    'class' => self::IOS_GRAY_BUTTON_CLASS,
                ]),
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::IOS_PRIMARY_BUTTON_CLASS,
                ]),
            Action::make('planPdfPreview')
                ->label('Vista previa PDF')
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->extraAttributes([
                    'class' => self::IOS_SUCCESS_BUTTON_CLASS,
                ])
                ->slideOver()
                ->formWrapper(false)
                ->modalWidth(Width::FiveExtraLarge)
                ->extraModalWindowAttributes([
                    'class' => 'fi-agency-command-center-window',
                ])
                ->modalHeading(fn (): string => 'Plan generado · '.($this->getRecord()->name ?? ''))
                ->modalDescription(fn (): string => 'Vista previa y descarga del PDF con la matriz de beneficios.')
                ->modalContent(fn (): \Illuminate\Contracts\View\View => $this->resolvePlanPdfPanelView())
                ->modalSubmitAction(false)
                ->modalCancelAction(
                    fn (Action $action): Action => $action
                        ->label('Cerrar')
                        ->extraAttributes([
                            'class' => HelpdeskTicketModalActions::IOS_GRAY_BTN,
                        ]),
                )
                ->action(fn (): null => null),
        ];
    }

    public function approveQuote(string $destination): void
    {
        /** @var PlanGenerator $plan */
        $plan = $this->getRecord();

        SecurityAudit::log('AUDIT_BUSINESS_PLAN_GENERATOR_PRE_AFFILIATION_STARTED', 'business.plan-generators.pre-affiliation-start', [
            'plan_generator_id' => $plan->getKey(),
            'plan_name' => $plan->name,
            'plan_status' => $plan->status,
            'destination' => $destination,
        ]);

        if ($destination === PlanGeneratorPreAffiliationSession::TYPE_NEW_BUSINESS) {
            PlanGeneratorPreAffiliationSession::store($plan, PlanGeneratorPreAffiliationSession::TYPE_NEW_BUSINESS);

            Notification::make()
                ->title('Pre-afiliación iniciada')
                ->body('El plan permanece en estatus PRE-APROBADO. Continúa con el registro de la empresa.')
                ->success()
                ->send();

            $this->redirect(PlanGeneratorResource::getUrl('register-company', ['record' => $plan->getKey()]));

            return;
        }

        // Individual y corporativo necesitan que el analista elija la cobertura
        // antes de abrir el formulario: la matriz tiene una tarifa por
        // (cobertura × rango etario) y el sistema no puede decidir cuál va.
        // `replaceMountedAction` cambia el contenido de esta misma modal.
        if ($destination === PlanGeneratorPreAffiliationSession::TYPE_INDIVIDUAL) {
            if (! $this->assertPreAffiliationOptionsExist(PlanGeneratorPreAffiliationOptions::individualRows($plan))) {
                return;
            }

            $this->replaceMountedAction('chooseIndividualCoverage');

            return;
        }

        if ($destination === PlanGeneratorPreAffiliationSession::TYPE_CORPORATE) {
            if (! $this->assertPreAffiliationOptionsExist(PlanGeneratorPreAffiliationOptions::corporateRows($plan))) {
                return;
            }

            $this->replaceMountedAction('chooseCorporateCoverages');

            return;
        }
    }

    /**
     * Una matriz sin tarifas no produce ninguna opción afiliable. Se avisa y se
     * deja la modal abierta en vez de abrir un selector vacío.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function assertPreAffiliationOptionsExist(array $rows): bool
    {
        if ($rows !== []) {
            return true;
        }

        Notification::make()
            ->title('La cotización no tiene tarifas')
            ->body('Cargue al menos una tarifa individual anual en la matriz del plan antes de pre-afiliar.')
            ->warning()
            ->persistent()
            ->send();

        return false;
    }

    /**
     * Paso 2 del flujo individual: elegir la cobertura y el rango etario del
     * titular. Una sola opción, igual que la tabla «Detalles de la cotización»
     * de Negocios, que rechaza la selección múltiple.
     */
    protected function chooseIndividualCoverageAction(): Action
    {
        return Action::make('chooseIndividualCoverage')
            ->label('Elegir cobertura')
            ->icon('heroicon-o-user')
            ->color('success')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalIcon('heroicon-o-user')
            ->modalHeading('Pre-afiliación individual · elija la cobertura')
            ->modalDescription(fn (): string => 'Tarifas calculadas en la cotización '.((string) $this->getRecord()->control_number).'. Marque la cobertura y el rango etario del titular.')
            ->modalSubmitActionLabel('Continuar a la afiliación')
            ->modalCancelActionLabel('Cancelar')
            ->closeModalByClickingAway(false)
            ->schema([
                Radio::make('option_key')
                    ->label('Cobertura y rango etario')
                    ->options(fn (): array => PlanGeneratorPreAffiliationOptions::individualOptions($this->getRecord()))
                    ->descriptions(fn (): array => PlanGeneratorPreAffiliationOptions::individualDescriptions($this->getRecord()))
                    ->required()
                    ->live()
                    ->columns(1)
                    ->validationMessages([
                        'required' => 'Marque la cobertura que desea afiliar.',
                    ])
                    ->helperText('Tarifas de la matriz del plan; el ajuste interno de tarifas ya está aplicado.'),
                TextInput::make('people')
                    ->label('Personas a afiliar')
                    ->helperText('Titular más beneficiarios de esta afiliación. No es la población que el rango lleva cotizada.')
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->minValue(1)
                    ->maxValue(fn (Get $get): int => max(
                        1,
                        (int) (PlanGeneratorPreAffiliationOptions::findIndividualRow(
                            $this->getRecord(),
                            $get('option_key'),
                        )['population'] ?? 1),
                    ))
                    ->live(onBlur: true)
                    ->visible(fn (Get $get): bool => filled($get('option_key')))
                    ->validationMessages([
                        'required' => 'Indique cuántas personas entran en esta afiliación.',
                        'max' => 'No puede afiliar más personas de las que el rango tiene cotizadas.',
                    ]),
                Placeholder::make('individual_totals')
                    ->label('Total a pagar')
                    ->visible(fn (Get $get): bool => filled($get('option_key')))
                    ->content(fn (Get $get): string => PlanGeneratorPreAffiliationOptions::individualTotalsLine(
                        PlanGeneratorPreAffiliationOptions::findIndividualRow($this->getRecord(), $get('option_key')),
                        (int) ($get('people') ?? 1),
                    )),
            ])
            ->action(function (array $data): void {
                /** @var PlanGenerator $plan */
                $plan = $this->getRecord();

                $row = PlanGeneratorPreAffiliationOptions::findIndividualRow($plan, $data['option_key'] ?? null);

                if ($row === null) {
                    Notification::make()
                        ->title('La cobertura ya no existe')
                        ->body('La matriz del plan cambió mientras elegía. Vuelva a abrir «Aprobar cotización».')
                        ->danger()
                        ->send();

                    return;
                }

                $people = max(1, (int) ($data['people'] ?? 1));

                PlanGeneratorPreAffiliationSession::storeIndividual($plan, $row, $people);

                SecurityAudit::log('AUDIT_BUSINESS_PLAN_GENERATOR_PRE_AFFILIATION_COVERAGE_CHOSEN', 'business.plan-generators.pre-affiliation-coverage', [
                    'plan_generator_id' => $plan->getKey(),
                    'destination' => PlanGeneratorPreAffiliationSession::TYPE_INDIVIDUAL,
                    'column_label' => $row['column_label'],
                    'age_range_label' => $row['age_range_label'],
                    'fee' => $row['fee'],
                    'people' => $people,
                    'subtotal_anual' => PlanGeneratorPreAffiliationOptions::amountsForPeople($row, $people)['subtotal_anual'],
                ]);

                $amounts = PlanGeneratorPreAffiliationOptions::amountsForPeople($row, $people);

                Notification::make()
                    ->title('Cobertura seleccionada')
                    ->body($row['column_label'].' · rango '.$row['age_range_label'].' · '
                        .$amounts['people'].' '.($amounts['people'] === 1 ? 'persona' : 'personas').' · '
                        .PlanGeneratorPreAffiliationOptions::money($amounts['subtotal_anual']).' anual. Complete la pre-afiliación individual.')
                    ->success()
                    ->send();

                $this->redirect(AffiliationResource::getUrl('create', panel: 'business'));
            });
    }

    /**
     * Paso 2 del flujo corporativo: elegir las coberturas. Una cobertura cubre
     * todos sus rangos etarios, así que acá se marca la columna completa y se
     * admite más de una (preafiliación múltiple).
     */
    protected function chooseCorporateCoveragesAction(): Action
    {
        return Action::make('chooseCorporateCoverages')
            ->label('Elegir coberturas')
            ->icon('heroicon-o-building-office-2')
            ->color('info')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalIcon('heroicon-o-building-office-2')
            ->modalHeading('Pre-afiliación corporativa · elija las coberturas')
            ->modalDescription(fn (): string => 'Paso 1 de 2. Totales grupales de la cotización '.((string) $this->getRecord()->control_number).'. Después se carga la población por importación.')
            ->modalSubmitActionLabel('Continuar a la población')
            ->modalCancelActionLabel('Cancelar')
            ->closeModalByClickingAway(false)
            ->schema([
                CheckboxList::make('column_keys')
                    ->label('Coberturas a pre-afiliar')
                    ->options(fn (): array => PlanGeneratorPreAffiliationOptions::corporateOptions($this->getRecord()))
                    ->descriptions(fn (): array => PlanGeneratorPreAffiliationOptions::corporateDescriptions($this->getRecord()))
                    ->required()
                    ->bulkToggleable()
                    ->columns(1)
                    ->validationMessages([
                        'required' => 'Marque al menos una cobertura.',
                    ])
                    ->helperText('Con una cobertura la pre-afiliación es simple; con dos o más, múltiple. Los totales ya incluyen el ajuste interno de tarifas.'),
            ])
            ->action(function (array $data): void {
                /** @var PlanGenerator $plan */
                $plan = $this->getRecord();

                $rows = PlanGeneratorPreAffiliationOptions::findCorporateRows(
                    $plan,
                    (array) ($data['column_keys'] ?? []),
                );

                if ($rows === []) {
                    Notification::make()
                        ->title('Las coberturas ya no existen')
                        ->body('La matriz del plan cambió mientras elegía. Vuelva a abrir «Aprobar cotización».')
                        ->danger()
                        ->send();

                    return;
                }

                PlanGeneratorPreAffiliationSession::storeCorporate($plan, $rows);

                SecurityAudit::log('AUDIT_BUSINESS_PLAN_GENERATOR_PRE_AFFILIATION_COVERAGE_CHOSEN', 'business.plan-generators.pre-affiliation-coverage', [
                    'plan_generator_id' => $plan->getKey(),
                    'destination' => PlanGeneratorPreAffiliationSession::TYPE_CORPORATE,
                    'columns' => array_column($rows, 'column_label'),
                    'subtotal_anual' => array_sum(array_column($rows, 'subtotal_anual')),
                ]);

                $this->redirect(PlanGeneratorResource::getUrl('pre-affiliation-population', ['record' => $plan->getKey()]));
            });
    }

    public function getTitle(): string|Htmlable
    {
        $plan = $this->getRecord();
        $name = (string) ($plan->name ?? 'Sin nombre');
        $status = strtoupper((string) ($plan->status ?? 'SIN ESTADO'));
        $badgeStyle = $this->badgeStyleForStatus($status);

        return new HtmlString(
            '<div class="flex flex-col gap-2">'
            .'<span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">'.e($name).'</span>'
            .'<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">'
            .'<span style="background-color: '.$badgeStyle['bg'].';color:#fff;padding:5px 14px;border-radius:999px;font-size:.78rem;font-weight:700;box-shadow:'.$badgeStyle['shadow'].';">'
            .e($status)
            .'</span>'
            .'<span class="text-sm text-gray-600 dark:text-gray-300">'.e((string) ($plan->columns_count ?? $plan->columns()->count())).' columnas</span>'
            .'<span class="text-sm text-gray-600 dark:text-gray-300">'.e((string) ($plan->rows_count ?? $plan->rows()->count())).' beneficios</span>'
            .'</div>'
            .'</div>'
        );
    }

    /**
     * @return array{bg: string, shadow: string}
     */
    private function badgeStyleForStatus(string $status): array
    {
        return match ($status) {
            'ACTIVO', 'ACTIVA' => ['bg' => '#16a34a', 'shadow' => '0 8px 20px rgba(22,163,74,.35)'],
            'APROBADA', 'APROBADO' => ['bg' => '#16a34a', 'shadow' => '0 8px 20px rgba(22,163,74,.35)'],
            'PRE-APROBADO' => ['bg' => '#d97706', 'shadow' => '0 8px 20px rgba(217,119,6,.35)'],
            'INACTIVO', 'INACTIVA' => ['bg' => '#6b7280', 'shadow' => '0 8px 20px rgba(107,114,128,.35)'],
            default => ['bg' => '#6b7280', 'shadow' => '0 8px 20px rgba(107,114,128,.35)'],
        };
    }

    private function resolvePlanPdfPanelView(): \Illuminate\Contracts\View\View
    {
        /** @var PlanGenerator $planGenerator */
        $planGenerator = $this->getRecord();

        SecurityAudit::log('AUDIT_BUSINESS_PLAN_GENERATOR_PDF_VIEWED', 'business.plan-generators.pdf.view-page', [
            'plan_generator_id' => $planGenerator->getKey(),
            'plan_name' => $planGenerator->name,
            'source' => 'view_plan_generator_header',
        ]);

        return view('filament.business.plan-generators.plan-pdf-panel', [
            'record' => $planGenerator,
        ]);
    }
}
