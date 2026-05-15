<?php

namespace App\Filament\Operations\Resources\TelemedicineCases\RelationManagers;

use App\Filament\Operations\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use App\Models\TelemedicineConsultationPatient;
use App\Support\Telemedicine\TelemedicineCoverageCatalog;
use App\Support\Telemedicine\TelemedicineDerivedServiceBadge;
use App\Support\Telemedicine\TelemedicineMedicationCoverage;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ConsultationsRelationManager extends RelationManager
{
    protected static string $relationship = 'consultations';

    // public function form(Schema $schema): Schema
    // {
    //     return $schema
    //         ->components([
    //             TextInput::make('telemedicine_case_id')
    //                 ->required()
    //                 ->maxLength(255),
    //         ]);
    // }

    // public function infolist(Schema $schema): Schema
    // {
    //     return $schema
    //         ->components([
    //             Section::make()
    //                 ->description(fn(TelemedicineConsultationPatient $record) => 'PACIENTE: ' . $record->full_name . ' | ' . 'EDAD: ' . $record->telemedicinePatient->age . ' años | ' . 'SEXO: ' . $record->telemedicinePatient->sex)
    //                 ->columnSpanFull()
    //                 ->icon(Heroicon::Bars3BottomLeft)
    //                 ->schema([
    //                     Fieldset::make('INFORMACIÓN PRINCIPAL')
    //                         ->schema([
    //                             TextEntry::make('telemedicine_case_code')
    //                                 ->label('NÚMERO DE CASO:')
    //                                 ->badge()
    //                                 ->color('success'),
    //                             TextEntry::make('full_name')
    //                                 ->label('NOMBRE COMPLETO:')
    //                                 ->badge()
    //                                 ->default(fn(TelemedicineConsultationPatient $record) => strtoupper($record->full_name))
    //                                 ->color('success'),
    //                             TextEntry::make('nro_identificacion')
    //                                 ->label('NÚMERO DE IDENTIFICACION:')
    //                                 ->prefix('V-')
    //                                 ->badge()
    //                                 ->color('success'),
    //                             TextEntry::make('telemedicineServiceList.name')
    //                                 ->label('SERVICIO:')
    //                                 ->badge()
    //                                 ->color('success'),

    //                             TextEntry::make('telemedicineDoctor.full_name')
    //                                 ->label('ATENIDO POR:')
    //                                 ->prefix('Dr(a). ')
    //                                 ->badge(),
    //                             TextEntry::make('created_at')
    //                                 ->label('FECHA DE REGISTRO:')
    //                                 ->badge()
    //                                 ->date('d/m/Y'),
    //                             TextEntry::make('status')
    //                                 ->label('ESTADO:')
    //                                 ->badge()
    //                                 ->color(function (TelemedicineConsultationPatient $record) {
    //                                     if ($record->status == 'EN SEGUIMIENTO') {
    //                                         return 'warning';
    //                                     } elseif ($record->status == 'CONSULTA INICIAL') {
    //                                         return 'info';
    //                                     } elseif ($record->status == 'ALTA MEDICA') {
    //                                         return 'success';
    //                                     }
    //                                 }),
    //                             // TextEntry::make('telemedicinePriority.name')
    //                             //     ->label('PRIORIDAD:')
    //                             //     ->badge()
    //                             //     ->color(function (string $state): string {
    //                             //         return match ($state) {
    //                             //             'ALTA'          => 'success',
    //                             //             'MEDIA'         => 'warning',
    //                             //             'BAJA'          => 'primary',
    //                             //             'EMERGENCIA'    => 'danger',
    //                             //         };
    //                             //     })
    //                             //     ->icon(function (string $state): string {
    //                             //         return match ($state) {
    //                             //             'ALTA'             => 'healthicons-f-health',
    //                             //             'MEDIA'            => 'healthicons-f-health',
    //                             //             'BAJA'             => 'healthicons-f-health',
    //                             //             'EMERGENCIA'       => 'heroicon-c-shield-exclamation',
    //                             //         };
    //                             //     }),
    //                             TextEntry::make('updated_at')
    //                                 ->label('Ultima Actualización')
    //                                 ->default(fn(TelemedicineCase $record): string => $record->updated_at->diffForHumans()),

    //                         ])->columnSpanFull()->columns(5),
    //                     Fieldset::make('INFORMACIÓN MEDICA')
    //                         ->hidden(fn(TelemedicineConsultationPatient $record) => $record->status == 'EN SEGUIMIENTO' || $record->status == 'ALTA MAEDICA')
    //                         ->schema([
    //                             TextEntry::make('reason_consultation')
    //                                 ->label('RAZÓN DE CONSULTA:'),
    //                             TextEntry::make('actual_phatology')
    //                                 ->label('PATOLÓGICO ACTUAL:'),
    //                             TextEntry::make('background')
    //                                 ->label('ANTECEDENTES:'),
    //                             TextEntry::make('diagnostic_impression')
    //                                 ->label('IMPRESIÓN DIAGNOSTICA:'),
    //                         ])->columnSpanFull()->columns(2),
    //                     Fieldset::make('CUESTIONARIO DE SEGUIMIENTO')
    //                         ->hidden(fn(TelemedicineConsultationPatient $record) => $record->status == 'CONSULTA INICIAL')
    //                         ->schema([
    //                             TextEntry::make('cuestion_1')
    //                                 ->label('1.- ¿COMO SE SIENTE EL DIA DE HOY?')
    //                                 ->prefix('RESPUESTA: '),
    //                             TextEntry::make('cuestion_2')
    //                                 ->label('2.- ¿COMO HA RESPONDIDO AL TRATAMIENTO INDICADO?')
    //                                 ->prefix('RESPUESTA: '),
    //                             TextEntry::make('cuestion_3')
    //                                 ->label('3. ¿SIENTE QUE HAN MEJORADO LOS SÍNTOMAS?')
    //                                 ->prefix('RESPUESTA: '),
    //                             TextEntry::make('cuestion_4')
    //                                 ->label('4. ¿SE REALIZO LOS ESTUDIOS SOLICITADOS?')
    //                                 ->prefix('RESPUESTA: '),
    //                             TextEntry::make('cuestion_5')
    //                                 ->label('5. EN VISTA DE QUE SUS RESULTADOS DE LABORATORIO ESTÁN ALTERADOS, SE MODIFICAN LAS INDICACIONES MEDICAS.')
    //                                 ->prefix('RESPUESTA: '),
    //                         ])->columnSpanFull()->columns(2),
    //                 ])->columnSpanFull(),
    //         ]);
    // }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Bitácora de gestión médica')
            ->description('Seguimientos y registros vinculados a este caso. Los valores de cobertura se muestran como listas cuando provienen de formularios estructurados.')
            ->emptyStateHeading('Sin consultas en este caso')
            ->emptyStateDescription('Aún no hay registros de consulta o seguimiento. Use «Crear» para iniciar una nueva.')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'telemedicinePatientMedications.operationInventory',
            ]))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha de registro')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (TelemedicineConsultationPatient $record): string => $record->updated_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->iconColor('gray'),
                TextColumn::make('telemedicine_case_code')
                    ->label('N.º de caso')
                    ->weight(FontWeight::Medium)
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('code_reference')
                    ->label('Referencia')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('telemedicinePatient.full_name')
                    ->label('Paciente')
                    ->description(fn (TelemedicineConsultationPatient $record): string => 'Atendido por: Dr(a). '.($record->telemedicineDoctor?->full_name ?? '—'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->label('Identificación')
                    ->prefix('V-')
                    ->alignCenter()
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('telemedicineServiceList.name')
                    ->label('Servicio')
                    ->badge()
                    ->color('success')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('telemedicineServiceListDrift.name')
                    ->label('Servicio derivado')
                    ->badge()
                    ->placeholder('—')
                    ->color(fn (?string $state): string => TelemedicineDerivedServiceBadge::driftNameIsCritical($state) ? 'danger' : 'info')
                    ->icon(fn (?string $state): Heroicon => TelemedicineDerivedServiceBadge::driftNameIsCritical($state)
                        ? Heroicon::OutlinedExclamationTriangle
                        : Heroicon::OutlinedInformationCircle)
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                ColumnGroup::make('Medicamentos (consulta / caso)', [
                    TextColumn::make('medications_case_lines')
                        ->label('Medicamentos')
                        ->alignStart()
                        ->html()
                        ->getStateUsing(fn (TelemedicineConsultationPatient $record): HtmlString => self::consultationMedicationsHtml($record, 'medicine')),
                ]),
                ColumnGroup::make('Cobertura (incluidos)', [
                    TextColumn::make('labs_badges')
                        ->label('Laboratorios')
                        ->alignStart()

                        ->html()
                        ->getStateUsing(fn (TelemedicineConsultationPatient $record): HtmlString => self::consultationCoverageCatalogBadgesHtml(
                            $record,
                            'labs',
                            fn (string $label): bool => TelemedicineCoverageCatalog::laboratoryIsCovered($label)
                        )),
                    TextColumn::make('studies_badges')
                        ->label('Estudios')
                        ->alignStart()
                        ->html()
                        ->getStateUsing(fn (TelemedicineConsultationPatient $record): HtmlString => self::consultationCoverageCatalogBadgesHtml(
                            $record,
                            'studies',
                            fn (string $label): bool => TelemedicineCoverageCatalog::studyIsCovered($label)
                        )),
                    TextColumn::make('consult_specialist_badges')
                        ->label('Especialistas')
                        ->alignStart()
                        ->html()
                        ->getStateUsing(fn (TelemedicineConsultationPatient $record): HtmlString => self::consultationCoverageCatalogBadgesHtml(
                            $record,
                            'consult_specialist',
                            fn (string $label): bool => TelemedicineCoverageCatalog::specialistIsCovered($label)
                        )),
                ]),
                // ColumnGroup::make('Fuera de cobertura', [
                //     TextColumn::make('other_labs')
                //         ->label('Otros laboratorios')
                //         ->alignCenter()
                //         ->wrap()
                //         ->badge()
                //         ->formatStateUsing(fn (mixed $state): string => self::formatCoverageList($state))
                //         ->color(fn (mixed $state): string => self::coverageListIsFilled($state) ? 'warning' : 'gray'),
                //     TextColumn::make('other_studies')
                //         ->label('Otros estudios')
                //         ->alignCenter()
                //         ->wrap()
                //         ->badge()
                //         ->formatStateUsing(fn (mixed $state): string => self::formatCoverageList($state))
                //         ->color(fn (mixed $state): string => self::coverageListIsFilled($state) ? 'warning' : 'gray'),
                //     TextColumn::make('other_specialist')
                //         ->label('Otros especialistas')
                //         ->alignCenter()
                //         ->wrap()
                //         ->badge()
                //         ->formatStateUsing(fn (mixed $state): string => self::formatCoverageList($state))
                //         ->color(fn (mixed $state): string => self::coverageListIsFilled($state) ? 'warning' : 'gray'),
                // ]),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (TelemedicineConsultationPatient $record): string => match ($record->status) {
                        'EN SEGUIMIENTO' => 'warning',
                        'CONSULTA INICIAL' => 'info',
                        'ALTA MEDICA' => 'success',
                        default => 'gray',
                    })
                    ->icon(fn (TelemedicineConsultationPatient $record): Heroicon => match ($record->status) {
                        'EN SEGUIMIENTO' => Heroicon::OutlinedArrowPath,
                        'CONSULTA INICIAL' => Heroicon::OutlinedHeart,
                        'ALTA MEDICA' => Heroicon::OutlinedCheckBadge,
                        default => Heroicon::OutlinedQuestionMarkCircle,
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver detalle')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('primary')
                    ->link()
                    ->url(function (TelemedicineConsultationPatient $record): string {
                        $url = TelemedicineConsultationPatientResource::getUrl('view', ['record' => $record->getKey()]);
                        if (request()->query('from') === 'patient') {
                            $url .= (str_contains($url, '?') ? '&' : '?').'from=patient';
                        }

                        return $url;
                    })
                    ->openUrlInNewTab(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nueva consulta')
                    ->icon(Heroicon::OutlinedPlus),
            ]);
    }

    /**
     * @param  'indications'|'medicine'  $line
     */
    private static function consultationMedicationsHtml(TelemedicineConsultationPatient $record, string $line): HtmlString
    {
        $medications = $record->relationLoaded('telemedicinePatientMedications')
            ? $record->telemedicinePatientMedications
            : $record->telemedicinePatientMedications()->orderBy('id')->get();

        $medications = $medications->sortBy('id')->values();

        if ($medications->isEmpty()) {
            return new HtmlString('<span class="text-gray-500 dark:text-gray-400">—</span>');
        }

        $badges = [];
        foreach ($medications as $medication) {
            $covered = TelemedicineMedicationCoverage::isCovered($medication);

            if ($line === 'medicine') {
                $label = e((string) ($medication->medicine ?? '—'));
            } else {
                $raw = $medication->indications ?? null;
                $label = filled($raw) ? e((string) $raw) : '—';
            }

            $badges[] = self::coverageStatusBadgeHtml(
                $covered,
                $label,
                'Cubierto por el plan o convenio',
                'Fuera de cobertura'
            );
        }

        return new HtmlString(
            '<div class="flex flex-wrap items-start gap-1.5">'.implode('', $badges).'</div>'
        );
    }

    /**
     * Laboratorios / estudios / especialistas según catálogo (CUBIERTO vs NO CUBIERTO).
     *
     * @param  callable(string): bool  $coverageResolver
     */
    private static function consultationCoverageCatalogBadgesHtml(
        TelemedicineConsultationPatient $record,
        string $attribute,
        callable $coverageResolver,
    ): HtmlString {
        $items = self::flattenCoverageStateToStrings($record->{$attribute} ?? null);

        if ($items === []) {
            return new HtmlString('<span class="text-gray-500 dark:text-gray-400">—</span>');
        }

        $badges = [];
        foreach ($items as $label) {
            $escaped = e($label);
            $covered = $coverageResolver($label);
            $badges[] = self::coverageStatusBadgeHtml($covered, $escaped);
        }

        return new HtmlString(
            '<div class="flex flex-wrap items-start gap-1.5">'.implode('', $badges).'</div>'
        );
    }

    /**
     * @return list<string>
     */
    private static function flattenCoverageStateToStrings(mixed $state): array
    {
        if ($state === null || $state === '' || $state === []) {
            return [];
        }

        if (is_string($state)) {
            return $state !== '' ? [trim($state)] : [];
        }

        if (! is_array($state)) {
            $s = trim((string) $state);

            return $s !== '' ? [$s] : [];
        }

        $flat = [];

        $walker = function (mixed $value) use (&$flat, &$walker): void {
            if (is_array($value)) {
                foreach ($value as $inner) {
                    $walker($inner);
                }

                return;
            }

            if ($value === null || $value === '') {
                return;
            }

            $flat[] = trim((string) $value);
        };

        $walker($state);

        return array_values(array_filter($flat, fn (string $v): bool => $v !== ''));
    }

    /**
     * Badge: icono + texto; verde si cubierto, rojo si no (misma línea visual que medicamentos).
     */
    private static function coverageStatusBadgeHtml(
        bool $covered,
        string $escapedLabel,
        string $titleWhenCovered = 'Cubierto según catálogo maestro',
        string $titleWhenNotCovered = 'No cubierto según catálogo maestro',
    ): string {
        $title = e($covered ? $titleWhenCovered : $titleWhenNotCovered);

        $surface = $covered
            ? 'bg-emerald-50 text-emerald-900 ring-emerald-600/15 dark:bg-emerald-400/10 dark:text-emerald-100 dark:ring-emerald-400/25'
            : 'bg-red-50 text-red-900 ring-red-600/15 dark:bg-red-400/10 dark:text-red-100 dark:ring-red-400/25';

        $icon = $covered ? self::svgIconShieldCheck() : self::svgIconXCircle();

        return '<span class="fi-badge inline-flex max-w-full items-center gap-x-1.5 rounded-lg px-2 py-0.5 text-xs font-semibold ring-1 ring-inset '.$surface.'" title="'.$title.'">'.$icon.'<span class="min-w-0 flex-1 whitespace-normal break-words leading-snug">'.$escapedLabel.'</span></span>';
    }

    private static function svgIconShieldCheck(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3.5 w-3.5 shrink-0 text-emerald-600 dark:text-emerald-300" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>';
    }

    private static function svgIconXCircle(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3.5 w-3.5 shrink-0 text-red-600 dark:text-red-300" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>';
    }

    private static function formatCoverageList(mixed $state): string
    {
        if ($state === null || $state === '' || $state === []) {
            return '—';
        }

        if (is_string($state)) {
            return $state;
        }

        if (! is_array($state)) {
            return (string) $state;
        }

        $flat = [];

        $walker = function (mixed $value) use (&$flat, &$walker): void {
            if (is_array($value)) {
                foreach ($value as $inner) {
                    $walker($inner);
                }

                return;
            }

            if ($value === null || $value === '') {
                return;
            }

            $flat[] = (string) $value;
        };

        $walker($state);

        return $flat === [] ? '—' : implode(', ', $flat);
    }

    private static function coverageListIsFilled(mixed $state): bool
    {
        return self::formatCoverageList($state) !== '—';
    }
}
