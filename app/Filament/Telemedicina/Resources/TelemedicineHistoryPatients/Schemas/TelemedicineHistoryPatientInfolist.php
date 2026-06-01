<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Schemas;

use App\Models\TelemedicineHistoryPatient;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TelemedicineHistoryPatientInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('telemedicineHistoryPatientTelemedicinaInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Información general')
                            ->icon(Heroicon::OutlinedIdentification)
                            ->schema(self::informacionGeneralTab()),
                        Tab::make('Familiares')
                            ->icon(Heroicon::OutlinedUsers)
                            ->schema(self::antecedentesFamiliaresTab()),
                        Tab::make('Patológicos')
                            ->icon(Heroicon::OutlinedBeaker)
                            ->schema(self::antecedentesPatologicosTab()),
                        Tab::make('Hábitos y social')
                            ->icon(Heroicon::OutlinedFire)
                            ->schema(self::antecedentesNoPatologicosTab()),
                        Tab::make('Quirúrgicos')
                            ->icon(Heroicon::OutlinedScissors)
                            ->schema(self::quirurgicosTab()),
                        Tab::make('Alergias')
                            ->icon(Heroicon::OutlinedExclamationTriangle)
                            ->schema(self::alergiasTab()),
                        Tab::make('Medicamentos')
                            ->icon(Heroicon::OutlinedArchiveBox)
                            ->schema(self::medicamentosTab()),
                        Tab::make('Ginecológicos')
                            ->icon(Heroicon::OutlinedHeart)
                            ->schema(self::ginecologicosTab()),
                    ]),
            ]);
    }

    /**
     * @return array<int, Section>
     */
    private static function informacionGeneralTab(): array
    {
        return [
            Section::make('Paciente principal')
                ->description(fn (?TelemedicineHistoryPatient $record): ?string => self::patientSummaryLine($record))
                ->icon(Heroicon::OutlinedUserCircle)
                ->extraAttributes(['class' => self::SECTION_CARD])
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                        ->extraAttributes([
                            'class' => self::IOS_INNER_CLASS,
                        ])
                        ->schema([
                            TextEntry::make('telemedicinePatient.full_name')
                                ->label('Paciente')
                                ->badge()
                                ->color('primary')
                                ->placeholder('—'),
                            TextEntry::make('telemedicinePatient.nro_identificacion')
                                ->label('Cédula / identificación')
                                ->placeholder('—'),
                            TextEntry::make('telemedicinePatient.code')
                                ->label('Código paciente')
                                ->placeholder('—'),
                            TextEntry::make('telemedicinePatient.sex')
                                ->label('Sexo')
                                ->badge()
                                ->placeholder('—'),
                            TextEntry::make('telemedicinePatient.age')
                                ->label('Edad')
                                ->suffix(' años')
                                ->placeholder('—'),
                            TextEntry::make('telemedicinePatient.phone')
                                ->label('Teléfono principal')
                                ->placeholder('—'),
                        ]),
                ]),
            Section::make('Identificación y medidas')
                ->description('Datos de la historia, antropometría y registro.')
                ->icon(Heroicon::OutlinedIdentification)
                ->extraAttributes(['class' => self::SECTION_CARD])
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                        ->extraAttributes([
                            'class' => self::IOS_INNER_CLASS,
                        ])
                        ->schema([
                            TextEntry::make('code')
                                ->label('N.º de historia')
                                ->badge()
                                ->color('success')
                                ->placeholder('—'),
                            TextEntry::make('history_date')
                                ->label('Fecha de historia')
                                ->icon(Heroicon::OutlinedCalendarDays)
                                ->placeholder('—'),
                            TextEntry::make('weight')
                                ->label('Peso')
                                ->helperText('kg')
                                ->icon(Heroicon::OutlinedScale)
                                ->badge()
                                ->color('success')
                                ->placeholder('—'),
                            TextEntry::make('height')
                                ->label('Estatura')
                                ->helperText('cm / m')
                                ->icon(Heroicon::OutlinedChevronDoubleUp)
                                ->badge()
                                ->color('success')
                                ->placeholder('—'),
                            TextEntry::make('imc')
                                ->label('IMC')
                                ->helperText('Índice de masa corporal')
                                ->icon(Heroicon::OutlinedChartBar)
                                ->badge()
                                ->color('success')
                                ->placeholder('—'),
                            TextEntry::make('createdBy.name')
                                ->label('Registrado por')
                                ->badge()
                                ->color('primary')
                                ->placeholder('—'),
                            TextEntry::make('created_at')
                                ->label('Registro el')
                                ->badge()
                                ->icon(Heroicon::OutlinedCalendarDays)
                                ->dateTime()
                                ->placeholder('—'),
                        ]),
                ]),
        ];
    }

    /**
     * @return array<int, Section>
     */
    private static function antecedentesFamiliaresTab(): array
    {
        return [
            Section::make('Antecedentes personales y familiares')
                ->description('Antecedentes declarados a nivel familiar o personal.')
                ->icon(Heroicon::OutlinedUsers)
                ->extraAttributes(['class' => self::SECTION_CARD])
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                        ->extraAttributes([
                            'class' => self::IOS_INNER_CLASS,
                        ])
                        ->schema([
                            IconEntry::make('tension_alta')
                                ->boolean()
                                ->label('Hipertensión arterial'),
                            IconEntry::make('diabetes')
                                ->boolean()
                                ->label('Diabetes mellitus'),
                            IconEntry::make('asma')
                                ->boolean()
                                ->label('Asma bronquial'),
                            IconEntry::make('cardiacos')
                                ->boolean()
                                ->label('Enfermedades cardíacas'),
                            IconEntry::make('gastritis_ulceras')
                                ->boolean()
                                ->label('Gastropatías'),
                            IconEntry::make('enfermedad_autoimmune')
                                ->boolean()
                                ->label('Enfermedad autoinmune'),
                            IconEntry::make('trombosis_embooleanas')
                                ->boolean()
                                ->label('Insuficiencia venosa'),
                            IconEntry::make('fracturas')
                                ->boolean()
                                ->label('Traumatismos'),
                            IconEntry::make('cancer')
                                ->boolean()
                                ->label('Cáncer'),
                            IconEntry::make('tranfusiones_sanguineas')
                                ->boolean()
                                ->label('Anemia'),
                            IconEntry::make('tiroides')
                                ->boolean()
                                ->label('Tiroides'),
                            IconEntry::make('hepatitis')
                                ->boolean()
                                ->label('Hepatitis'),
                            IconEntry::make('moretones_frecuentes')
                                ->boolean()
                                ->label('Enfermedades hematológicas'),
                            IconEntry::make('psiquiatricas')
                                ->boolean()
                                ->label('Enfermedades psiquiátricas'),
                            IconEntry::make('covid')
                                ->boolean()
                                ->label('COVID-19'),
                        ]),
                    Fieldset::make('Observaciones')
                        ->schema([
                            TextEntry::make('observations_personal')
                                ->label('Detalle')
                                ->columnSpanFull()
                                ->placeholder('Sin observaciones'),
                        ])
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @return array<int, Section>
     */
    private static function antecedentesPatologicosTab(): array
    {
        return [
            Section::make('Antecedentes patológicos')
                ->description('Condiciones declaradas en la revisión patológica.')
                ->icon(Heroicon::OutlinedBeaker)
                ->extraAttributes(['class' => self::SECTION_CARD])
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                        ->extraAttributes([
                            'class' => self::IOS_INNER_CLASS,
                        ])
                        ->schema([
                            IconEntry::make('tension_alta_app')
                                ->boolean()
                                ->label('Hipertensión arterial'),
                            IconEntry::make('diabetes')
                                ->boolean()
                                ->label('Diabetes mellitus'),
                            IconEntry::make('asma_app')
                                ->boolean()
                                ->label('Asma bronquial'),
                            IconEntry::make('cardiacos_app')
                                ->boolean()
                                ->label('Enfermedades cardíacas'),
                            IconEntry::make('gastritis_ulceras_app')
                                ->boolean()
                                ->label('Gastropatías'),
                            IconEntry::make('enfermedad_autoimmune_app')
                                ->boolean()
                                ->label('Enfermedad autoinmune'),
                            IconEntry::make('trombosis_embooleanas_app')
                                ->boolean()
                                ->label('Insuficiencia venosa'),
                            IconEntry::make('fracturas_app')
                                ->boolean()
                                ->label('Traumatismos'),
                            IconEntry::make('cancer_app')
                                ->boolean()
                                ->label('Cáncer'),
                            IconEntry::make('tranfusiones_sanguineas_app')
                                ->boolean()
                                ->label('Anemia'),
                            IconEntry::make('tiroides_app')
                                ->boolean()
                                ->label('Tiroides'),
                            IconEntry::make('hepatitis_app')
                                ->boolean()
                                ->label('Hepatitis'),
                            IconEntry::make('moretones_frecuentes_app')
                                ->boolean()
                                ->label('Enfermedades hematológicas'),
                            IconEntry::make('psiquiatricas_app')
                                ->boolean()
                                ->label('Enfermedades psiquiátricas'),
                            IconEntry::make('covid_app')
                                ->boolean()
                                ->label('COVID-19'),
                        ]),
                    Fieldset::make('Observaciones')
                        ->schema([
                            TextEntry::make('observations_pathological')
                                ->label('Detalle')
                                ->columnSpanFull()
                                ->placeholder('Sin observaciones'),
                        ])
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @return array<int, Section>
     */
    private static function antecedentesNoPatologicosTab(): array
    {
        return [
            Section::make('Hábitos y antecedentes no patológicos')
                ->description('Tabaco, alcohol y otros hábitos.')
                ->icon(Heroicon::OutlinedFire)
                ->extraAttributes(['class' => self::SECTION_CARD])
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                        ->extraAttributes([
                            'class' => self::IOS_INNER_CLASS,
                        ])
                        ->schema([
                            IconEntry::make('tabaco')
                                ->boolean()
                                ->label('Tabaquismo'),
                            IconEntry::make('alcohol')
                                ->boolean()
                                ->label('Alcohol'),
                            IconEntry::make('drogas')
                                ->boolean()
                                ->label('Drogas'),
                        ]),
                    Fieldset::make('Observaciones')
                        ->schema([
                            TextEntry::make('observations_not_pathological')
                                ->label('Detalle')
                                ->columnSpanFull()
                                ->placeholder('Sin observaciones'),
                        ])
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @return array<int, Section>
     */
    private static function quirurgicosTab(): array
    {
        return [
            Section::make('Antecedentes quirúrgicos')
                ->description('Intervenciones y procedimientos previos.')
                ->icon(Heroicon::OutlinedScissors)
                ->extraAttributes(['class' => self::SECTION_CARD])
                ->schema([
                    Fieldset::make('Detalle')
                        ->schema([
                            TextEntry::make('history_surgical')
                                ->label('Antecedentes quirúrgicos')
                                ->columnSpanFull()
                                ->placeholder('Sin registro'),
                        ])
                        ->columnSpanFull()
                        ->extraAttributes([
                            'class' => self::IOS_INNER_CLASS,
                        ]),
                ]),
        ];
    }

    /**
     * @return array<int, Section>
     */
    private static function alergiasTab(): array
    {
        return [
            Section::make('Alergias')
                ->description('Sustancias y reacciones declaradas.')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->extraAttributes(['class' => self::SECTION_CARD])
                ->schema([
                    Fieldset::make('Lista')
                        ->schema([
                            TextEntry::make('allergies')
                                ->label('Alergias')
                                ->columnSpanFull()
                                ->formatStateUsing(fn (mixed $state): ?string => self::formatAllergiesState($state))
                                ->placeholder('Sin registro'),
                            TextEntry::make('observations_allergies')
                                ->label('Observaciones')
                                ->columnSpanFull()
                                ->placeholder('Sin observaciones'),
                        ])
                        ->columnSpanFull()
                        ->extraAttributes([
                            'class' => self::IOS_INNER_CLASS,
                        ]),
                ]),
        ];
    }

    /**
     * @return array<int, Section>
     */
    private static function medicamentosTab(): array
    {
        return [
            Section::make('Medicamentos y suplementos')
                ->description('Tratamiento farmacológico y suplementación.')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->extraAttributes(['class' => self::SECTION_CARD])
                ->schema([
                    Fieldset::make('Uso actual')
                        ->schema([
                            TextEntry::make('medications_supplements')
                                ->label('Medicamentos o suplementos')
                                ->columnSpanFull()
                                ->placeholder('Sin registro'),
                        ])
                        ->columnSpanFull()
                        ->extraAttributes([
                            'class' => self::IOS_INNER_CLASS,
                        ]),
                    Fieldset::make('Observaciones')
                        ->schema([
                            TextEntry::make('observations_medication')
                                ->label('Detalle')
                                ->columnSpanFull()
                                ->placeholder('Sin observaciones'),
                        ])
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @return array<int, Section>
     */
    private static function ginecologicosTab(): array
    {
        return [
            Section::make('Antecedentes ginecológicos')
                ->description('Historia obstétrica cuando aplica.')
                ->icon(Heroicon::OutlinedHeart)
                ->extraAttributes(['class' => self::SECTION_CARD])
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                        ->extraAttributes([
                            'class' => self::IOS_INNER_CLASS,
                        ])
                        ->schema([
                            TextEntry::make('numero_embarazos')
                                ->label('Embarazos')
                                ->badge()
                                ->color('success')
                                ->placeholder('—'),
                            TextEntry::make('numero_partos')
                                ->label('Partos')
                                ->badge()
                                ->color('success')
                                ->placeholder('—'),
                            TextEntry::make('numero_abortos')
                                ->label('Abortos')
                                ->badge()
                                ->color('success')
                                ->placeholder('—'),
                            TextEntry::make('cesareas')
                                ->label('Cesáreas')
                                ->badge()
                                ->color('success')
                                ->placeholder('—'),
                        ]),
                    Fieldset::make('Observaciones')
                        ->schema([
                            TextEntry::make('observations_ginecologica')
                                ->label('Detalle')
                                ->columnSpanFull()
                                ->placeholder('Sin observaciones'),
                        ])
                        ->columnSpanFull(),
                ]),
        ];
    }

    private static function patientSummaryLine(?TelemedicineHistoryPatient $record): ?string
    {
        if ($record === null) {
            return null;
        }

        $patient = $record->telemedicinePatient;
        $name = $patient?->full_name ?? '—';
        $age = $patient?->age !== null && $patient->age !== ''
            ? (string) $patient->age.' años'
            : '—';
        $sex = $patient?->sex ?? '—';

        return "Paciente: {$name} · Edad: {$age} · Sexo: {$sex}";
    }

    private static function formatAllergiesState(mixed $state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        if (is_array($state)) {
            $parts = [];
            foreach ($state as $item) {
                if (is_string($item) && $item !== '') {
                    $parts[] = $item;
                } elseif ($item !== null && $item !== '') {
                    $parts[] = (string) json_encode($item);
                }
            }

            return $parts === [] ? null : implode(', ', $parts);
        }

        return (string) $state;
    }
}
