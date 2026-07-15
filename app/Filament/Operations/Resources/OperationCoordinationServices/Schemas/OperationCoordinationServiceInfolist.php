<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Schemas;

use App\Models\OperationCoordinationService;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use App\Support\Operations\CoordinationServiceCoveredItemsFinalizer;
use App\Support\Operations\CoordinationServiceDocumentsAggregator;
use App\Support\Operations\CoordinationServiceItemCancellation;
use App\Support\Operations\CoordinationServiceItemsManager;
use App\Support\Telemedicine\TelemedicineMedicationCoverage;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class OperationCoordinationServiceInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('operationCoordinationServiceInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Solicitud')
                            ->icon(Heroicon::DocumentText)
                            ->schema([
                                Section::make('Solicitud')
                                    ->description('Datos generales de la coordinación')
                                    ->icon(Heroicon::DocumentText)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Datos de la solicitud')
                                            ->schema([
                                                TextEntry::make('date_solicitud')
                                                    ->label('Fecha solicitud')
                                                    ->placeholder('-'),
                                                TextEntry::make('date_service')
                                                    ->label('Fecha servicio')
                                                    ->placeholder('-'),
                                                TextEntry::make('businessLine.definition')
                                                    ->label('Línea de negocio')
                                                    ->placeholder('-'),
                                                TextEntry::make('businessUnit.definition')
                                                    ->label('Unidad de negocio')
                                                    ->placeholder('-'),
                                                TextEntry::make('reference_number')
                                                    ->label('Nº referencia')
                                                    ->placeholder('-'),
                                                TextEntry::make('status')
                                                    ->label('Estado')
                                                    ->badge()
                                                    ->placeholder('-'),
                                            ])->columns(2),
                                    ])->columnSpanFull(),
                            ]),
                        Tab::make('Paciente')
                            ->icon(Heroicon::UserGroup)
                            ->schema([
                                Section::make('Paciente')
                                    ->description('Información del paciente')
                                    ->icon(Heroicon::UserGroup)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Paciente')
                                            ->schema([
                                                TextEntry::make('patient')
                                                    ->label('Paciente')
                                                    ->placeholder('-'),
                                                TextEntry::make('ci_patient')
                                                    ->label('C.I. paciente')
                                                    ->placeholder('-'),
                                                TextEntry::make('phone_holder')
                                                    ->label('Teléfono')
                                                    ->placeholder('-'),
                                                TextEntry::make('birth_date_patient')
                                                    ->label('Fecha nacimiento')

                                                    ->placeholder('-'),
                                                TextEntry::make('age_patient')
                                                    ->label('Edad')
                                                    ->suffix(' años')
                                                    ->placeholder('-'),
                                                TextEntry::make('relationship_patient')
                                                    ->label('Parentesco')
                                                    ->placeholder('-'),
                                            ])->columns(2),
                                    ])->columnSpanFull(),
                            ]),
                        Tab::make('Ubicación')
                            ->icon(Heroicon::MapPin)
                            ->schema([
                                Section::make('Ubicación')
                                    ->description('Dirección y ubicación')
                                    ->icon(Heroicon::MapPin)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Dirección y contacto')
                                            ->schema([
                                                TextEntry::make('contractor')
                                                    ->label('Contratante')
                                                    ->placeholder('-'),
                                                TextEntry::make('state.definition')
                                                    ->label('Estado')
                                                    ->placeholder('-'),
                                                TextEntry::make('city.definition')
                                                    ->label('Ciudad')
                                                    ->placeholder('-'),
                                                TextEntry::make('address')
                                                    ->label('Dirección')
                                                    ->placeholder('-')
                                                    ->columnSpanFull(),
                                            ])->columns(2),
                                    ])->columnSpanFull(),
                            ]),
                        Tab::make('Servicio')
                            ->icon(Heroicon::WrenchScrewdriver)
                            ->schema([
                                Section::make('Servicio')
                                    ->description('Detalle del servicio solicitado')
                                    ->icon(Heroicon::WrenchScrewdriver)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Detalle del servicio')
                                            ->schema([
                                                TextEntry::make('symptoms_diagnosis')
                                                    ->label('Síntomas / diagnóstico')
                                                    ->placeholder('-')
                                                    ->columnSpanFull(),
                                                TextEntry::make('servicie')
                                                    ->label('Servicio')
                                                    ->placeholder('-'),
                                                TextEntry::make('specific_service')
                                                    ->label('Servicio específico')
                                                    ->placeholder('-'),
                                                TextEntry::make('type_service')
                                                    ->label('Tipo de servicio')
                                                    ->placeholder('-'),
                                                TextEntry::make('supplier_service')
                                                    ->label('Proveedor del servicio')
                                                    ->placeholder('-'),
                                                TextEntry::make('farmadoc')
                                                    ->label('Farmadoc')
                                                    ->placeholder('-'),
                                            ])->columns(2),
                                    ])->columnSpanFull(),
                            ]),
                        Tab::make('Ítems asociados')
                            ->icon(Heroicon::ClipboardDocumentList)
                            ->schema([
                                Section::make('Ítems asociados a la coordinación')
                                    ->description('Visualice solo los ítems realmente asociados a esta coordinación.')
                                    ->icon(Heroicon::ClipboardDocumentList)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->visible(fn (OperationCoordinationService $record): bool => self::hasAnyAssociatedItems($record))
                                    ->headerActions([
                                        CoordinationServiceCoveredItemsFinalizer::makePlaceCoveredItemsInManagementAction(),
                                        CoordinationServiceCoveredItemsFinalizer::makeUploadCoveredMedicationDeliveryReceiptAction(),
                                        CoordinationServiceCoveredItemsFinalizer::makeUploadCoveredLabDeliveryReceiptAction(),
                                        CoordinationServiceCoveredItemsFinalizer::makeUploadCoveredStudyDeliveryReceiptAction(),
                                        CoordinationServiceCoveredItemsFinalizer::makeUploadCoveredSpecialtyDeliveryReceiptAction(),
                                        // CoordinationServiceCoveredItemsFinalizer::makeUploadAndFinalizeAction(),
                                    ])
                                    ->schema([
                                        Fieldset::make('Medicamentos')
                                            ->visible(fn (OperationCoordinationService $record): bool => self::hasMedications($record))
                                            ->schema([
                                                RepeatableEntry::make('telemedicinePatientMedicationsSummary')
                                                    ->label('Medicamentos e indicaciones')
                                                    ->state(fn (OperationCoordinationService $record): array => self::medicationsItemsState($record))
                                                    ->contained(false)
                                                    ->schema([
                                                        self::associatedItemCardEntry(),
                                                    ])
                                                    ->columnSpanFull(),
                                            ]),
                                        Fieldset::make('Laboratorios')
                                            ->visible(fn (OperationCoordinationService $record): bool => self::hasLaboratories($record))
                                            ->schema([
                                                RepeatableEntry::make('telemedicinePatientLabsSummary')
                                                    ->label('Laboratorios')
                                                    ->state(fn (OperationCoordinationService $record): array => self::laboratoriesItemsState($record))
                                                    ->contained(false)
                                                    ->schema([
                                                        self::associatedItemCardEntry(),
                                                    ])
                                                    ->columnSpanFull(),
                                            ]),
                                        Fieldset::make('Estudios')
                                            ->visible(fn (OperationCoordinationService $record): bool => self::hasStudies($record))
                                            ->schema([
                                                RepeatableEntry::make('telemedicinePatientStudiesSummary')
                                                    ->label('Estudios')
                                                    ->state(fn (OperationCoordinationService $record): array => self::studiesItemsState($record))
                                                    ->contained(false)
                                                    ->schema([
                                                        self::associatedItemCardEntry(),
                                                    ])
                                                    ->columnSpanFull(),
                                            ]),
                                        Fieldset::make('Consulta con especialista')
                                            ->visible(fn (OperationCoordinationService $record): bool => self::hasSpecialties($record))
                                            ->schema([
                                                RepeatableEntry::make('telemedicinePatientSpecialtiesSummary')
                                                    ->label('Especialistas')
                                                    ->state(fn (OperationCoordinationService $record): array => self::specialtiesItemsState($record))
                                                    ->contained(false)
                                                    ->schema([
                                                        self::associatedItemCardEntry(),
                                                    ])
                                                    ->columnSpanFull(),
                                            ]),
                                    ])->columnSpanFull(),
                            ]),
                        Tab::make('Negociación y precios')
                            ->icon(Heroicon::CurrencyDollar)
                            ->schema([
                                Section::make('Negociación y precios')
                                    ->description('Montos y negociación')
                                    ->icon(Heroicon::CurrencyDollar)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Negociación')
                                            ->schema([
                                                TextEntry::make('type_negotiation')
                                                    ->label('Tipo negociación')
                                                    ->placeholder('-'),
                                                TextEntry::make('status_negotiation')
                                                    ->label('Estado negociación')
                                                    ->placeholder('-'),
                                                TextEntry::make('negotiation')
                                                    ->label('Negociación')
                                                    ->placeholder('-'),
                                                TextEntry::make('neto')
                                                    ->label('Neto')
                                                    ->numeric()
                                                    ->placeholder('-'),
                                                TextEntry::make('porcen_tdec')
                                                    ->label('% TDEC')
                                                    ->numeric()
                                                    ->suffix('%')
                                                    ->placeholder('-'),
                                                TextEntry::make('quote_price')
                                                    ->label('Precio cotizado')
                                                    ->money()
                                                    ->placeholder('-'),
                                                TextEntry::make('porcen_discount')
                                                    ->label('% descuento')
                                                    ->numeric()
                                                    ->suffix('%')
                                                    ->placeholder('-'),
                                                TextEntry::make('price_discount')
                                                    ->label('Precio con descuento')
                                                    ->numeric()
                                                    ->placeholder('-'),
                                            ])->columns(2),
                                    ])->columnSpanFull(),
                            ]),
                        Tab::make('Documentos y facturación')
                            ->icon(Heroicon::DocumentDuplicate)
                            ->schema([
                                Section::make('Documentos y facturación')
                                    ->description('Números de cotización, orden y factura')
                                    ->icon(Heroicon::DocumentDuplicate)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Números y factura')
                                            ->schema([
                                                TextEntry::make('quote_number')
                                                    ->label('Nº cotización')
                                                    ->placeholder('-'),
                                                TextEntry::make('approved_number')
                                                    ->label('Nº aprobación')
                                                    ->placeholder('-'),
                                                TextEntry::make('service_order_number')
                                                    ->label('Nº orden de servicio')
                                                    ->placeholder('-'),
                                                TextEntry::make('bill_number')
                                                    ->label('Nº factura')
                                                    ->placeholder('-'),
                                                TextEntry::make('bill_price')
                                                    ->label('Monto factura')
                                                    ->money()
                                                    ->placeholder('-'),
                                                TextEntry::make('bill_date')
                                                    ->label('Fecha factura')

                                                    ->placeholder('-'),
                                            ])->columns(2),
                                    ])->columnSpanFull(),
                            ]),
                        Tab::make('Incidencias y observaciones')
                            ->icon(Heroicon::ChatBubbleLeftRight)
                            ->schema([
                                Section::make('Incidencias y observaciones')
                                    ->description('Notas e incidencias')
                                    ->icon(Heroicon::ChatBubbleLeftRight)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Observaciones')
                                            ->schema([
                                                TextEntry::make('incidence')
                                                    ->label('Incidencia')
                                                    ->placeholder('-')
                                                    ->columnSpanFull(),
                                                TextEntry::make('negotiation_description')
                                                    ->label('Descripción negociación')
                                                    ->placeholder('-')
                                                    ->columnSpanFull(),
                                                TextEntry::make('qc_description')
                                                    ->label('Descripción QC')
                                                    ->placeholder('-')
                                                    ->columnSpanFull(),
                                                TextEntry::make('observations')
                                                    ->label('Observaciones')
                                                    ->placeholder('-')
                                                    ->columnSpanFull(),
                                            ]),
                                    ])->columnSpanFull(),
                            ]),
                        Tab::make('Vinculación telemedicina')
                            ->icon(Heroicon::Signal)
                            ->schema([
                                Section::make('Vinculación telemedicina')
                                    ->description('Identificadores de telemedicina')
                                    ->icon(Heroicon::Signal)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('IDs telemedicina')
                                            ->schema([
                                                TextEntry::make('telemedicine_patient_id')
                                                    ->label('ID paciente')
                                                    ->placeholder('-'),
                                                TextEntry::make('telemedicine_case_id')
                                                    ->label('ID caso')
                                                    ->placeholder('-'),
                                                TextEntry::make('telemedicine_doctor_id')
                                                    ->label('ID doctor')
                                                    ->placeholder('-'),
                                                TextEntry::make('telemedicine_consultation_patient_id')
                                                    ->label('ID consulta paciente')
                                                    ->placeholder('-'),
                                            ])->columns(2),
                                    ])->columnSpanFull(),
                            ]),
                        Tab::make('Documentos')
                            ->icon(Heroicon::OutlinedFolderOpen)
                            ->schema([
                                Section::make('Documentos cargados')
                                    ->description('Validación en tiempo real de documentos y tipos asociados por archivo.')
                                    ->icon(Heroicon::OutlinedFolderOpen)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        RepeatableEntry::make('uploaded_documents')
                                            ->label('Listado de documentos')
                                            ->state(fn (OperationCoordinationService $record): array => CoordinationServiceDocumentsAggregator::forCoordination($record))
                                            ->placeholder('Aún no hay documentos cargados en esta coordinación.')
                                            ->table([
                                                TableColumn::make('Documento')->width('24%'),
                                                TableColumn::make('Servicio')->width('24%'),
                                                TableColumn::make('Origen')->width('14%'),
                                                TableColumn::make('Tipo(s)')->width('18%'),
                                                TableColumn::make('Fecha')->width('12%'),
                                            ])
                                            ->schema([
                                                TextEntry::make('document_name')
                                                    ->label('Documento')
                                                    ->formatStateUsing(function (TextEntry $component, mixed $state): Htmlable|string {
                                                        $row = self::uploadedDocumentRowFromComponent($component);

                                                        return new HtmlString(self::renderDocumentNameCell(
                                                            self::uploadedDocumentDisplayName($row, $state),
                                                            $row,
                                                        ));
                                                    })
                                                    ->prefixActions([
                                                        fn (TextEntry $component): array => self::uploadedDocumentDownloadPrefixActions($component),
                                                    ])
                                                    ->placeholder('—'),
                                                TextEntry::make('services')
                                                    ->label('Servicio')
                                                    ->badge()
                                                    ->color('info')
                                                    ->formatStateUsing(fn (mixed $state): ?string => filled($state)
                                                        ? trim((string) $state)
                                                        : null)
                                                    ->placeholder('Sin servicio asociado'),
                                                TextEntry::make('source')
                                                    ->label('Origen')
                                                    ->badge()
                                                    ->color('gray')
                                                    ->placeholder('—'),
                                                TextEntry::make('document_types')
                                                    ->label('Tipo(s)')
                                                    ->badge()
                                                    ->color('success')
                                                    ->formatStateUsing(fn (mixed $state): ?string => filled($state)
                                                        ? trim((string) $state)
                                                        : null)
                                                    ->placeholder('Sin tipo asociado'),
                                                TextEntry::make('uploaded_at')
                                                    ->label('Fecha')
                                                    ->formatStateUsing(fn (mixed $state): string => self::formatUploadedAt($state))
                                                    ->placeholder('—'),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Auditoría')
                            ->icon(Heroicon::Clock)
                            ->schema([
                                Section::make('Auditoría')
                                    ->description('Registro de cambios')
                                    ->icon(Heroicon::Clock)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Registro')
                                            ->schema([
                                                TextEntry::make('created_by')
                                                    ->label('Creado por')
                                                    ->placeholder('-'),
                                                TextEntry::make('updated_by')
                                                    ->label('Actualizado por')
                                                    ->placeholder('-'),
                                                TextEntry::make('created_at')
                                                    ->label('Fecha creación')
                                                    ->dateTime('d/m/Y H:i')
                                                    ->placeholder('-'),
                                                TextEntry::make('updated_at')
                                                    ->label('Última actualización')
                                                    ->dateTime('d/m/Y H:i')
                                                    ->placeholder('-'),
                                            ])->columns(2),
                                    ])->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function uploadedDocumentRowFromComponent(TextEntry $component): ?array
    {
        $containerState = $component->getContainer()->getConstantState();

        if (is_array($containerState)) {
            return $containerState;
        }

        if (is_object($containerState)) {
            return (array) $containerState;
        }

        $legacyState = $component->getConstantState();

        return is_array($legacyState) ? $legacyState : null;
    }

    /**
     * @param  array<string, mixed>|null  $row
     */
    private static function uploadedDocumentDisplayName(?array $row, mixed $state): string
    {
        $name = is_array($row) ? trim((string) ($row['document_name'] ?? '')) : '';

        if ($name === '') {
            $name = trim((string) $state);
        }

        return $name !== '' ? $name : 'Documento sin nombre';
    }

    /**
     * @return array<int, Action>
     */
    private static function uploadedDocumentDownloadPrefixActions(TextEntry $component): array
    {
        $row = self::uploadedDocumentRowFromComponent($component);
        $downloadUrl = self::resolveUploadedDocumentDownloadUrlFromRecord($row);

        if ($downloadUrl === null) {
            return [];
        }

        $name = self::uploadedDocumentDisplayName($row, null);

        return [
            Action::make('downloadUploadedDocument')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->iconButton()
                ->color('info')
                ->tooltip('Descargar '.($name !== '' ? $name : 'documento'))
                ->url($downloadUrl)
                ->openUrlInNewTab(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $record
     */
    private static function renderDocumentNameCell(string $name, ?array $record): string
    {
        $filePath = $record !== null ? trim((string) ($record['file_path'] ?? '')) : '';
        $extension = strtoupper((string) pathinfo($filePath, PATHINFO_EXTENSION));
        $downloadUrl = self::resolveUploadedDocumentDownloadUrlFromRecord($record);

        $meta = $extension !== '' ? $extension : 'Archivo';
        $hasDownload = $downloadUrl !== null;

        $content = '<div class="flex items-center gap-2">'
            .'<span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-cyan-500/10 text-cyan-500 dark:text-cyan-300">📄</span>'
            .'<div class="min-w-0">'
            .'<p class="truncate text-sm font-semibold '.($hasDownload
                ? 'text-cyan-600 dark:text-cyan-300'
                : 'text-gray-900 dark:text-gray-100').'">'.e($name).'</p>'
            .'<p class="text-[11px] text-gray-500 dark:text-gray-400">'.e($meta)
            .($hasDownload ? ' · Use el icono para descargar' : '').'</p>'
            .'</div>'
            .'</div>';

        if (! $hasDownload) {
            return $content;
        }

        $downloadName = $filePath !== '' ? basename($filePath) : $name;

        return '<a href="'.e($downloadUrl).'" target="_blank" rel="noopener noreferrer" '
            .'download="'.e($downloadName).'" title="Descargar '.e($name).'" '
            .'class="group block rounded-lg transition hover:bg-cyan-500/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500/40">'
            .$content
            .'</a>';
    }

    private static function renderDocumentTypesBadges(mixed $state): string
    {
        if (! is_array($state) || $state === []) {
            return '<span class="text-xs text-gray-400">Sin tipo asociado</span>';
        }

        $badges = collect($state)
            ->map(static fn (mixed $item): string => trim((string) $item))
            ->filter(static fn (string $value): bool => $value !== '')
            ->map(static fn (string $value): string => '<span class="inline-flex items-center rounded-full border border-emerald-400/30 bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-300">'.e($value).'</span>')
            ->values()
            ->all();

        if ($badges === []) {
            return '<span class="text-xs text-gray-400">Sin tipo asociado</span>';
        }

        return '<div class="flex flex-wrap gap-1">'.implode('', $badges).'</div>';
    }

    /**
     * Filament descompone arrays de estado en ítems individuales; normalizamos array o string.
     *
     * @return array<int, string>
     */
    private static function normalizeDocumentTypesState(mixed $state): array
    {
        if (is_array($state)) {
            return collect($state)
                ->map(static fn (mixed $item): string => trim((string) $item))
                ->filter(static fn (string $value): bool => $value !== '')
                ->values()
                ->all();
        }

        if (is_string($state) && trim($state) !== '') {
            return [trim($state)];
        }

        return [];
    }

    private static function formatUploadedAt(mixed $state): string
    {
        if (! filled($state)) {
            return '—';
        }

        try {
            return Carbon::parse((string) $state)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $state;
        }
    }

    /**
     * @param  array<string, mixed>|null  $record
     */
    private static function resolveUploadedDocumentDownloadUrlFromRecord(?array $record): ?string
    {
        if ($record === null) {
            return null;
        }

        $filePath = trim((string) ($record['file_path'] ?? ''));

        if ($filePath === '' || ! Storage::disk('public')->exists($filePath)) {
            return null;
        }

        return asset('storage/'.$filePath);
    }

    private static function medicationsItemsState(OperationCoordinationService $record): array
    {
        $orderLinks = CoordinationServiceItemsManager::serviceOrderLinksByClinicalItemKey($record);

        return $record->telemedicinePatientMedications()
            ->orderBy('id')
            ->with('operationInventory:id,is_covered')
            ->get(['id', 'medicine', 'indications', 'status', 'is_covered', 'operation_inventory_id'])
            ->map(function (TelemedicinePatientMedications $item) use ($record, $orderLinks): array {
                $label = (string) ($item->medicine ?? 'Medicamento sin nombre');
                $rawStatus = (string) ($item->status ?? 'SIN ESTATUS');
                $status = CoordinationServiceItemsManager::effectiveDisplayStatusForClinicalItem(
                    $record,
                    'Medicamento',
                    $label,
                    $rawStatus,
                    $orderLinks,
                );

                return self::associatedItemState(
                    id: (int) $item->id,
                    itemType: 'medication',
                    title: 'Medicamento: '.$label,
                    detail: 'Indicación: '.($item->indications ?? 'Sin indicación'),
                    status: $status,
                    coverage: TelemedicineMedicationCoverage::isCovered($item),
                );
            })
            ->values()
            ->all();
    }

    private static function laboratoriesItemsState(OperationCoordinationService $record): array
    {
        $orderLinks = CoordinationServiceItemsManager::serviceOrderLinksByClinicalItemKey($record);

        return $record->telemedicinePatientLabs()
            ->orderBy('id')
            ->get(['id', 'laboratory', 'type', 'status'])
            ->map(function (TelemedicinePatientLab $item) use ($record, $orderLinks): array {
                $label = (string) ($item->laboratory ?? 'Laboratorio sin nombre');
                $rawStatus = (string) ($item->status ?? 'SIN ESTATUS');
                $status = CoordinationServiceItemsManager::effectiveDisplayStatusForClinicalItem(
                    $record,
                    'Laboratorio',
                    $label,
                    $rawStatus,
                    $orderLinks,
                );

                return self::associatedItemState(
                    id: (int) $item->id,
                    itemType: 'lab',
                    title: 'Laboratorio: '.$label,
                    detail: 'Tipo: '.($item->type ?? '—'),
                    status: $status,
                    coverage: self::catalogItemCoverageValue($item->type),
                );
            })
            ->values()
            ->all();
    }

    private static function studiesItemsState(OperationCoordinationService $record): array
    {
        $orderLinks = CoordinationServiceItemsManager::serviceOrderLinksByClinicalItemKey($record);

        return $record->telemedicinePatientStudies()
            ->orderBy('id')
            ->get(['id', 'study', 'type', 'status'])
            ->map(function (TelemedicinePatientStudy $item) use ($record, $orderLinks): array {
                $label = (string) ($item->study ?? 'Estudio sin nombre');
                $rawStatus = (string) ($item->status ?? 'SIN ESTATUS');
                $status = CoordinationServiceItemsManager::effectiveDisplayStatusForClinicalItem(
                    $record,
                    'Estudio',
                    $label,
                    $rawStatus,
                    $orderLinks,
                );

                return self::associatedItemState(
                    id: (int) $item->id,
                    itemType: 'study',
                    title: 'Estudio: '.$label,
                    detail: 'Tipo: '.($item->type ?? '—'),
                    status: $status,
                    coverage: self::catalogItemCoverageValue($item->type),
                );
            })
            ->values()
            ->all();
    }

    private static function specialtiesItemsState(OperationCoordinationService $record): array
    {
        $orderLinks = CoordinationServiceItemsManager::serviceOrderLinksByClinicalItemKey($record);

        return $record->telemedicinePatientSpecialties()
            ->orderBy('id')
            ->get(['id', 'specialty', 'type', 'status'])
            ->map(function (TelemedicinePatientSpecialty $item) use ($record, $orderLinks): array {
                $label = (string) ($item->specialty ?? 'Especialidad sin nombre');
                $rawStatus = (string) ($item->status ?? 'SIN ESTATUS');
                $status = CoordinationServiceItemsManager::effectiveDisplayStatusForClinicalItem(
                    $record,
                    'Especialista',
                    $label,
                    $rawStatus,
                    $orderLinks,
                );

                return self::associatedItemState(
                    id: (int) $item->id,
                    itemType: 'specialty',
                    title: 'Especialidad: '.$label,
                    detail: 'Tipo: '.($item->type ?? '—'),
                    status: $status,
                    coverage: self::catalogItemCoverageValue($item->type),
                );
            })
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, item_type: string, title: string, detail: string, status: string, coverage: bool|null, can_cancel: bool}
     */
    private static function associatedItemState(
        int $id,
        string $itemType,
        string $title,
        string $detail,
        string $status,
        ?bool $coverage,
    ): array {
        return [
            'id' => $id,
            'item_type' => $itemType,
            'title' => $title,
            'detail' => $detail,
            'status' => $status,
            'coverage' => $coverage,
            'can_cancel' => CoordinationServiceItemCancellation::statusIsCancellable($status),
        ];
    }

    private static function catalogItemCoverageValue(?string $type): ?bool
    {
        if ($type === null || trim($type) === '') {
            return null;
        }

        return mb_strtoupper(trim($type)) === 'CUBIERTO';
    }

    private static function coverageLabel(?bool $isCovered): string
    {
        return match ($isCovered) {
            true => 'Cubierto',
            false => 'No cubierto',
            default => 'Sin dato',
        };
    }

    private static function coverageBadgeClasses(?bool $isCovered): string
    {
        return match ($isCovered) {
            true => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-400/15 dark:text-emerald-200',
            false => 'border-rose-500/40 bg-rose-500/10 text-rose-700 dark:border-rose-400/40 dark:bg-rose-400/15 dark:text-rose-200',
            default => 'border-gray-400/40 bg-gray-500/10 text-gray-700 dark:border-gray-300/30 dark:bg-gray-400/15 dark:text-gray-200',
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function associatedItemRowFromComponent(TextEntry $component): ?array
    {
        $containerState = $component->getContainer()->getConstantState();

        if (is_array($containerState)) {
            return $containerState;
        }

        if (is_object($containerState)) {
            return (array) $containerState;
        }

        $legacyState = $component->getConstantState();

        return is_array($legacyState) ? $legacyState : null;
    }

    private static function associatedItemCardEntry(): TextEntry
    {
        return TextEntry::make('card')
            ->hiddenLabel()
            ->html()
            ->state(fn (TextEntry $component): string => self::renderAssociatedItemCardFromComponent($component))
            ->extraAttributes([
                // El borde/padding del card envuelve contenido + icono (suffixAction).
                'class' => 'fi-coordination-associated-item-card flex w-full items-center gap-2 rounded-xl border border-gray-200/80 bg-white px-4 py-3 shadow-xs dark:border-white/10 dark:bg-white/5',
            ])
            ->suffixActions([
                fn (TextEntry $component): array => self::cancelAssociatedItemSuffixActions($component),
            ])
            ->columnSpanFull();
    }

    private static function renderAssociatedItemCardFromComponent(TextEntry $component): string
    {
        $item = self::associatedItemRowFromComponent($component);

        if ($item === null) {
            return '';
        }

        return self::renderAssociatedItemCard($item);
    }

    /**
     * @return array<int, Action>
     */
    private static function cancelAssociatedItemSuffixActions(TextEntry $component): array
    {
        $row = self::associatedItemRowFromComponent($component);
        $action = $row !== null
            ? CoordinationServiceItemCancellation::makeCancelAction($row)
            : null;

        return $action instanceof Action ? [$action] : [];
    }

    /**
     * @param  array{title: string, detail: string, status: string, coverage: bool|null}  $item
     */
    private static function renderAssociatedItemCard(array $item): string
    {
        $status = mb_strtoupper(trim($item['status']));
        $statusBadgeClasses = match ($status) {
            'FINALIZADO' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-400/15 dark:text-emerald-200',
            'PENDIENTE' => 'border-rose-500/40 bg-rose-500/10 text-rose-700 dark:border-rose-400/40 dark:bg-rose-400/15 dark:text-rose-200',
            'EN GESTION' => 'border-orange-500/45 bg-orange-500/10 text-orange-700 dark:border-orange-400/45 dark:bg-orange-400/15 dark:text-orange-200',
            'CANCELADO', 'CANCELADA', 'CADUCADA' => 'border-rose-500/40 bg-rose-500/10 text-rose-700 dark:border-rose-400/40 dark:bg-rose-400/15 dark:text-rose-200',
            default => 'border-gray-400/40 bg-gray-500/10 text-gray-700 dark:border-gray-300/30 dark:bg-gray-400/15 dark:text-gray-200',
        };

        $coverageLabel = self::coverageLabel($item['coverage'] ?? null);
        $coverageBadgeClasses = self::coverageBadgeClasses($item['coverage'] ?? null);

        return '<div class="flex min-w-0 flex-1 items-center justify-between gap-3">'
            .'<div class="min-w-0">'
            .'<p class="text-sm font-semibold text-gray-900 dark:text-white">'.e($item['title']).'</p>'
            .'<p class="mt-1 text-sm text-gray-600 dark:text-gray-300">'.e($item['detail']).'</p>'
            .'</div>'
            .'<div class="flex shrink-0 flex-wrap items-center gap-2">'
            .'<span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide '.$coverageBadgeClasses.'">'.e($coverageLabel).'</span>'
            .'<span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide '.$statusBadgeClasses.'">'.e($item['status']).'</span>'
            .'</div>'
            .'</div>';
    }

    private static function hasAnyAssociatedItems(OperationCoordinationService $record): bool
    {
        return self::hasMedications($record)
            || self::hasLaboratories($record)
            || self::hasStudies($record)
            || self::hasSpecialties($record);
    }

    private static function hasMedications(OperationCoordinationService $record): bool
    {
        return $record->telemedicinePatientMedications()->exists();
    }

    private static function hasLaboratories(OperationCoordinationService $record): bool
    {
        return $record->telemedicinePatientLabs()->exists();
    }

    private static function hasStudies(OperationCoordinationService $record): bool
    {
        return $record->telemedicinePatientStudies()->exists();
    }

    private static function hasSpecialties(OperationCoordinationService $record): bool
    {
        return $record->telemedicinePatientSpecialties()->exists();
    }
}
