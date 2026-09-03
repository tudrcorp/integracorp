<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Pages;

use App\Filament\Operations\Resources\OperationCoordinationServices\OperationCoordinationServiceResource;
use App\Filament\Operations\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Filament\Operations\Resources\TelemedicinePatients\Actions\RegisterTpaRetailServicesAction;
use App\Models\OperationCoordinationService;
use App\Models\OperationDocumentList;
use App\Support\Operations\CoordinationServiceCoveredItemsFinalizer;
use App\Support\Operations\CoordinationServiceItemsManager;
use App\Support\Operations\CoordinationServiceQuoteManager;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Throwable;

class ViewOperationCoordinationService extends ViewRecord
{
    protected static string $resource = OperationCoordinationServiceResource::class;

    protected static ?string $title = 'Ficha Técnica del Servicio de Coordinación';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const BUTTON_TAIL = 'shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray '.self::BUTTON_TAIL;

    private const INFO_BUTTON_CLASS = 'aviso-btn-ios-info '.self::BUTTON_TAIL;

    private const PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary '.self::BUTTON_TAIL;

    private const WARNING_BUTTON_CLASS = 'aviso-btn-ios-warning '.self::BUTTON_TAIL;

    public function getRelationManagers(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_tpa_retail_service_quote')
                ->label('Crear cotización')
                ->icon(Heroicon::OutlinedDocumentCurrencyDollar)
                ->color('success')
                ->button()
                ->visible(fn (): bool => $this->canCreateTpaRetailServiceQuote())
                ->extraAttributes([
                    'class' => self::PRIMARY_BUTTON_CLASS,
                ])
                ->url(fn (): string => ManageCoordinationServiceItems::getUrl(['record' => $this->getRecord()])),

            Action::make('manage_service_quote')
                ->label('Gestionar Cotización')
                ->icon(Heroicon::OutlinedDocumentCurrencyDollar)
                ->color('warning')
                ->button()
                ->visible(fn (): bool => CoordinationServiceQuoteManager::coordinationQuotes($this->getRecord())->isNotEmpty())
                ->extraAttributes([
                    'class' => self::WARNING_BUTTON_CLASS,
                ])
                ->url(fn (): string => ManageCoordinationServiceQuotes::getUrl(['record' => $this->getRecord()])),

            Action::make('upload_coordination_documents')
                ->label('Cargar documentos')
                ->icon('heroicon-o-paper-clip')
                ->color('warning')
                ->button()
                ->visible(fn (): bool => $this->coordinationIsEnGestion())
                ->extraAttributes([
                    'x-on:click.stop' => '',
                    'class' => self::INFO_BUTTON_CLASS,
                ])
                ->modalHeading('Cargar documentos de la coordinación')
                ->modalDescription('Agregue uno o varios documentos. Cada archivo puede incluir uno o varios tipos de documento.')
                ->modalWidth(Width::FourExtraLarge)
                ->form([
                    Repeater::make('documents')
                        ->label('Documentos')
                        ->defaultItems(1)
                        ->addActionLabel('Agregar documento')
                        ->reorderable()
                        ->minItems(1)
                        ->schema([
                            Select::make('service_item_keys')
                                ->label('Servicio(s) asociado(s)')
                                ->helperText('Opcional: indique a qué servicio de la coordinación pertenece este documento.')
                                ->options(fn (): array => CoordinationServiceItemsManager::manageServiceItemOptions($this->getRecord()))
                                ->searchable()
                                ->preload()
                                ->multiple(),
                            Select::make('document_type_ids')
                                ->label('Tipo(s) de documento')
                                ->helperText('Seleccione uno o varios tipos según la información contenida en el archivo.')
                                ->options(fn (): array => OperationDocumentList::query()
                                    ->orderBy('name', 'asc')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->multiple()
                                ->required(),
                            FileUpload::make('document_file')
                                ->label('Archivo')
                                ->directory(fn () => 'operation-coordination-services/'.$this->getRecord()->id.'/documents')
                                ->preserveFilenames()
                                ->required()
                                ->maxSize(10240),
                        ])
                        ->columns(1)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $record = $this->getRecord();

                    $newDocuments = CoordinationServiceCoveredItemsFinalizer::buildUploadedDocumentsFromForm(
                        $data,
                        CoordinationServiceItemsManager::manageServiceItemOptions($record),
                    );

                    if ($newDocuments === []) {
                        Notification::make()
                            ->warning()
                            ->title('Sin documentos válidos')
                            ->body('Debe cargar al menos un documento con archivo y tipos seleccionados.')
                            ->send();

                        return;
                    }

                    $existingDocuments = is_array($record->uploaded_documents)
                        ? $record->uploaded_documents
                        : [];

                    $record->update([
                        'uploaded_documents' => array_values(array_merge($existingDocuments, $newDocuments)),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Documentos cargados')
                        ->body(count($newDocuments) > 1
                            ? 'Se cargaron '.count($newDocuments).' documentos en la coordinación.'
                            : 'Se cargó 1 documento en la coordinación.')
                        ->send();
                }),

            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->extraAttributes([
                    'class' => self::GRAY_BUTTON_CLASS,
                ])
                ->url(OperationCoordinationServiceResource::getUrl()),
        ];
    }

    private function canCreateTpaRetailServiceQuote(): bool
    {
        if (in_array('ATENMEDI', Auth::user()?->departament ?? [], true)) {
            return false;
        }

        $record = $this->getRecord();

        if (! RegisterTpaRetailServicesAction::isTpaRetailStandaloneCoordination($record)) {
            return false;
        }

        RegisterTpaRetailServicesAction::ensureStandaloneManagementItem($record);

        return CoordinationServiceItemsManager::hasManageServiceSelectableItems($record);
    }

    private function coordinationIsEnGestion(): bool
    {
        return mb_strtoupper(trim((string) $this->getRecord()->status)) === 'EN GESTION';
    }

    public function getTitle(): string|Htmlable
    {
        $record = $this->getRecord();
        $status = (string) ($record->status ?? '');
        $clinicalItems = CoordinationServiceItemsManager::clinicalItemsWithEffectiveDisplayStatus($record);
        $statusCountersHtml = CoordinationServiceItemsManager::renderClinicalItemsStatusCounterPills($clinicalItems);

        if ($statusCountersHtml === '') {
            $badgeStyle = $this->badgeStyleForStatus($status);
            $statusCountersHtml = '<span class="fi-coordination-header__status" style="background:'.$badgeStyle['bg'].';box-shadow:'.$badgeStyle['shadow'].',inset 0 1px 0 rgba(255,255,255,.25);">'
                .'<span aria-hidden="true" class="fi-coordination-header__status-dot">●</span> '.e($status)
                .'</span>';
        }

        $patientName = trim((string) ($record->patient ?? ''));

        return new HtmlString(
            '<div class="fi-coordination-header">'
            .'<p class="fi-coordination-header__eyebrow">Detalles del Servicio de Coordinación</p>'
            .'<p class="fi-coordination-header__title">Paciente: '.e($patientName !== '' ? $patientName : 'Paciente no definido').'</p>'
            .'<div class="fi-coordination-header__pills">'.$this->headerPillsHtml($record).'</div>'
            .'<div class="fi-coordination-header__statuses">'.$statusCountersHtml.'</div>'
            .'</div>'
        );
    }

    /**
     * Metadatos de cabecera. El caso de telemedicina se enlaza a su ficha; el resto
     * son informativos y se omiten cuando el dato no existe.
     */
    private function headerPillsHtml(OperationCoordinationService $record): string
    {
        $case = $record->telemedicineCase;
        $caseCode = trim((string) ($case->code ?? ''));

        $pills = [
            $this->renderHeaderPill('Referencia', (string) ($record->reference_number ?? '')),
            $this->renderHeaderPill('C.I. paciente', (string) ($record->ci_patient ?? '')),
            $this->renderHeaderPill(
                'Caso',
                $caseCode,
                $case !== null && $caseCode !== ''
                    ? TelemedicineCaseResource::getUrl('view', ['record' => $case])
                    : null,
                'Abrir la ficha del caso de telemedicina',
            ),
            $this->renderHeaderPill('Fecha de servicio', $this->serviceDateLabel($record)),
            $this->renderHeaderPill('Médico tratante', (string) ($record->telemedicineDoctor->full_name ?? '')),
        ];

        return implode('', array_filter($pills));
    }

    /**
     * `date_service` no está casteado en el modelo, así que puede llegar como fecha,
     * fecha-hora o texto libre: se formatea cuando se puede y si no se muestra crudo.
     */
    private function serviceDateLabel(OperationCoordinationService $record): string
    {
        $value = trim((string) ($record->date_service ?? ''));

        if ($value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (Throwable) {
            return $value;
        }
    }

    private function renderHeaderPill(string $label, string $value, ?string $url = null, ?string $tooltip = null): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $inner = '<span class="fi-coordination-header__pill-label">'.e($label).':</span>'
            .'<span class="fi-coordination-header__pill-value">'.e($value).'</span>';

        if ($url === null) {
            return '<span class="fi-coordination-header__pill">'.$inner.'</span>';
        }

        return '<a href="'.e($url).'" '
            .'class="fi-coordination-header__pill fi-coordination-header__pill--link" '
            .'title="'.e($tooltip ?? ('Abrir '.$label)).'">'
            .$inner
            .'<span aria-hidden="true" class="fi-coordination-header__pill-arrow">&rarr;</span>'
            .'</a>';
    }

    /**
     * @return array{bg: string, shadow: string}
     */
    private function badgeStyleForStatus(string $status): array
    {
        return match ($status) {
            'EN GESTION' => [
                'bg' => '#ffc107',
                'shadow' => '0 4px 12px rgba(255, 193, 7, 0.35)',
            ],
            'CANCELADA' => [
                'bg' => '#ff3b30',
                'shadow' => '0 4px 12px rgba(255, 59, 48, 0.35)',
            ],
            'FINALIZADO' => [
                'bg' => '#28cd41',
                'shadow' => '0 4px 12px rgba(40, 205, 65, 0.35)',
            ],
            'PENDIENTE' => [
                'bg' => '#ffcc00',
                'shadow' => '0 4px 12px rgba(255, 204, 0, 0.35)',
            ],
            'PENDIENTE POR RESULTADOS' => [
                'bg' => '#ffcc00',
                'shadow' => '0 4px 12px rgba(255, 204, 0, 0.35)',
            ],
            'NOVEDAD ADMON' => [
                'bg' => '#ff3b30',
                'shadow' => '0 4px 12px rgba(255, 59, 48, 0.35)',
            ],
            default => [
                'bg' => '#8e8e93',
                'shadow' => '0 4px 12px rgba(142, 142, 147, 0.35)',
            ],
        };
    }
}
