<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\Affiliates\Schemas;

use App\Models\Affiliate;
use Carbon\Carbon;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AffiliateInfolist
{
    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private static function statusColor(?string $state): string
    {
        return match (strtoupper((string) $state)) {
            'ACTIVO', 'ACTIVA' => 'success',
            'PENDIENTE' => 'warning',
            'EXCLUIDO', 'INACTIVO' => 'danger',
            default => 'gray',
        };
    }

    /**
     * @return array<int, IconEntry>
     */
    private static function medicalQuestionIconEntries(): array
    {
        $labels = [
            'affiliation.cuestion_1' => '¿Usted y el grupo de beneficiarios solicitantes, gozan de buena salud?',
            'affiliation.cuestion_2' => '¿Usted o el grupo de beneficiarios presentan alguna condición médica o congénita?',
            'affiliation.cuestion_3' => '¿Usted o el grupo de beneficiario ha sido intervenido quirúrgicamente?',
            'affiliation.cuestion_4' => '¿Usted o el grupo de beneficiario padece o ha padecido alguna enfermedad?',
            'affiliation.cuestion_5' => 'Enfermedades Cardiovasculares, tales como; Hipertensión Arterial, Ataque cardíaco, Angina o dolor de pecho, Soplo Cardíaco, Insuficiencia Cardíaca Congestiva o desórdenes del corazón o sistema circulatorio.',
            'affiliation.cuestion_6' => 'Enfermedades Cerebrovasculares, tales como: Desmayos, confusión, parálisis de miembros, dificultad para hablar, articular y entender, Accidente Cerebro-vascular (ACV). Cefalea o migraña. Epilepsia o Convulsiones. Otros trastornos o enfermedad del Cerebro o Sistema Nervioso.',
            'affiliation.cuestion_7' => 'Enfermedades Respiratorias, tales como: Asma Bronquial, Bronquitis, Bronquiolitis, Enfisema, Neumonía, Enfermedad pulmonar Obstructiva Crónica (EPOC) u otras enfermedades del Sistema Respiratorio.',
            'affiliation.cuestion_8' => 'Enfermedades o Trastornos Endocrinos tales como: Diabetes Mellitus, Bocio, hipertiroidismo, hipotiroidismo, Tiroiditis, Resistencia a la insulina, enfermedad de Cushing, cáncer de tiroides.',
            'affiliation.cuestion_9' => 'Enfermedades Gastrointestinales como: Litiasis vesicular, Cólico Biliar, Úlcera gástrica, gastritis, Hemorragia digestivas, colitis, hemorroides, Apendicitis, Peritonitis, Pancreatitis u otros desórdenes del estómago, intestino, hígado o vesícula biliar.',
            'affiliation.cuestion_10' => 'Enfermedades Renales: Litiasis renal, Cólico nefrítico, Sangre en la orina o Hematuria, Cistitis, Infecciones urinarias, Pielonefritis, Insuficiencia renal aguda. Otras enfermedades del riñón, vejiga o próstata.',
            'affiliation.cuestion_11' => 'Enfermedades Osteoarticulares, Artrosis, Artritis reumatoide, Traumatismo craneoencefálico, Fracturas óseas, Luxaciones o esguinces, tumores óseos, u otros trastornos de los músculos, articulaciones o columna vertical o espalda.',
            'affiliation.cuestion_12' => '¿Ha sufrido o padece de alguna enfermedad de la Piel como: Dermatitis, Celulitis, Abscesos cutáneos, quistes, tumores o cáncer? Quemaduras o Heridas Complicadas.',
            'affiliation.cuestion_13' => '¿Padece de alguna enfermedad o desorden de los ojos, oídos, nariz o garganta?',
            'affiliation.cuestion_14' => '¿Ha padecido de algún Envenenamiento o Intoxicación, Alergia o Reacción de Hipersensibilidad (medicamentosa, alimentaria, picadura de insecto, otras), edema de glotis o anafilaxia?',
            'affiliation.cuestion_15' => '¿Usted o alguno de los solicitantes, toma algún tipo de medicamentos por tratamiento prolongado?',
            'affiliation.cuestion_16' => '¿Ha padecido de algún Envenenamiento o Intoxicación, Alergia o Reacción de Hipersensibilidad (medicamentosa, alimentaria, picadura de insecto, otras), edema de glotis o anafilaxia?',
        ];

        return collect($labels)
            ->map(fn (string $label, string $field): IconEntry => IconEntry::make($field)
                ->label($label)
                ->boolean())
            ->values()
            ->all();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Resumen')
                    ->description(fn (Affiliate $record): string => ($record->full_name ?? '—').' · '.($record->phone ?? '—').' · '.($record->email ?? '—'))
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(1)
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('full_name')
                                    ->label('Nombre completo')
                                    ->size('lg')
                                    ->weight('semibold')
                                    ->color('gray')
                                    ->formatStateUsing(fn (?string $state): string => $state !== null && $state !== '' ? mb_strtoupper($state) : '—'),
                                TextEntry::make('status')
                                    ->label('Estatus')
                                    ->badge()
                                    ->color(fn (?string $state): string => self::statusColor($state))
                                    ->icon(Heroicon::OutlinedSignal),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Datos personales')
                    ->icon(Heroicon::OutlinedUser)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('nro_identificacion')
                                    ->label('Identificación')
                                    ->prefix('V-')
                                    ->icon(Heroicon::OutlinedIdentification)
                                    ->copyable()
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('birth_date')
                                    ->label('Fecha de nacimiento')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->formatStateUsing(function (mixed $state): ?string {
                                        if (blank($state)) {
                                            return null;
                                        }
                                        try {
                                            return Carbon::parse($state)->format('d/m/Y');
                                        } catch (\Throwable) {
                                            return (string) $state;
                                        }
                                    })
                                    ->placeholder('—'),
                                TextEntry::make('age')
                                    ->label('Edad')
                                    ->suffix(' años'),
                                TextEntry::make('sex')
                                    ->label('Sexo')
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('phone')
                                    ->label('Teléfono')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->copyable(),
                                TextEntry::make('email')
                                    ->label('Correo')
                                    ->icon(Heroicon::OutlinedEnvelope)
                                    ->copyable()
                                    ->wrap(),
                                TextEntry::make('address')
                                    ->label('Dirección')
                                    ->icon(Heroicon::OutlinedMapPin)
                                    ->columnSpan(['default' => 1, 'lg' => 2])
                                    ->wrap(),
                                TextEntry::make('city.definition')
                                    ->label('Ciudad')
                                    ->icon(Heroicon::OutlinedBuildingOffice2)
                                    ->placeholder('—'),
                                TextEntry::make('country.name')
                                    ->label('País')
                                    ->icon(Heroicon::OutlinedGlobeAmericas)
                                    ->placeholder('—'),
                                TextEntry::make('state.definition')
                                    ->label('Estado / provincia')
                                    ->icon(Heroicon::OutlinedMap)
                                    ->placeholder('—'),
                                TextEntry::make('region')
                                    ->label('Región')
                                    ->placeholder('—'),
                                TextEntry::make('created_at')
                                    ->label('Fecha de registro')
                                    ->icon(Heroicon::OutlinedClock)
                                    ->dateTime('d/m/Y H:i'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Agente')
                    ->description('Cuando la afiliación tiene agente asignado.')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->visible(fn (Affiliate $record): bool => (bool) ($record->affiliation?->agent_id))
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('affiliation.agent.name')
                                    ->label('Nombre')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->weight('medium')
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('—'),
                                TextEntry::make('affiliation.agent.ci')
                                    ->label('Identificación')
                                    ->icon(Heroicon::OutlinedIdentification)
                                    ->copyable()
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('—'),
                                TextEntry::make('affiliation.agent.phone')
                                    ->label('Teléfono')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('affiliation.agent.email')
                                    ->label('Correo')
                                    ->icon(Heroicon::OutlinedEnvelope)
                                    ->copyable()
                                    ->placeholder('—')
                                    ->wrap(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Agencia')
                    ->description('Cuando no hay agente (canal agencia).')
                    ->icon(Heroicon::OutlinedBuildingLibrary)
                    ->visible(fn (Affiliate $record): bool => $record->affiliation !== null && $record->affiliation->agent_id === null)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('affiliation.agency.name_corporative')
                                    ->label('Razón social')
                                    ->icon(Heroicon::OutlinedBuildingOffice2)
                                    ->weight('medium')
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('—'),
                                TextEntry::make('affiliation.agency.ci_responsable')
                                    ->label('CI responsable')
                                    ->copyable()
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('—'),
                                TextEntry::make('affiliation.agency.phone')
                                    ->label('Teléfono')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('affiliation.agency.email')
                                    ->label('Correo')
                                    ->icon(Heroicon::OutlinedEnvelope)
                                    ->copyable()
                                    ->placeholder('—')
                                    ->wrap(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Afiliación')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('affiliation.code')
                                    ->label('Código')
                                    ->icon(Heroicon::OutlinedHashtag)
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('plan.description')
                                    ->label('Plan')
                                    ->badge()
                                    ->color('primary')
                                    ->wrap(),
                                TextEntry::make('plan.businessUnit.definition')
                                    ->label('Unidad de negocio')
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('coverage.price')
                                    ->label('Cobertura')
                                    ->money('USD')
                                    ->placeholder('—'),
                                TextEntry::make('affiliation.effective_date')
                                    ->label('Vigencia')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->placeholder('—'),
                                TextEntry::make('affiliation.service_providers')
                                    ->label('Proveedores')
                                    ->formatStateUsing(function (mixed $state): ?string {
                                        if (blank($state)) {
                                            return null;
                                        }
                                        if (is_array($state)) {
                                            return implode(', ', array_filter(array_map('strval', $state)));
                                        }

                                        return (string) $state;
                                    })
                                    ->columnSpan(['default' => 1, 'lg' => 3])
                                    ->wrap(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Aliado de servicio nivel 1')
                    ->icon(Heroicon::OutlinedBuildingStorefront)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('affiliation.aliado_1_name')
                                    ->label('Nombre del aliado')
                                    ->default(fn (Affiliate $record): string => $record->affiliation?->aliado_1_name ?? '—')
                                    ->icon(Heroicon::OutlinedDocumentText)
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('affiliation.date_init_aliado_1')
                                    ->label('Inicio')
                                    ->default(fn (Affiliate $record): string => $record->affiliation?->date_init_aliado_1 ?? '—')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('affiliation.date_end_aliado_1')
                                    ->label('Vencimiento')
                                    ->default(fn (Affiliate $record): string => $record->affiliation?->date_end_aliado_1 ?? '—')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->badge()
                                    ->color('primary'),
                                IconEntry::make('affiliation_voucher_aliado_1')
                                    ->label('Voucher')
                                    ->icon(fn (Affiliate $record): string => filled($record->affiliation?->vaucher_aliado_1)
                                        ? 'heroicon-o-check-circle'
                                        : 'heroicon-o-x-circle')
                                    ->color(fn (Affiliate $record): string => filled($record->affiliation?->vaucher_aliado_1) ? 'success' : 'danger')
                                    ->url(fn (Affiliate $record): ?string => filled($record->affiliation?->vaucher_aliado_1)
                                        ? asset('storage/'.$record->affiliation->vaucher_aliado_1)
                                        : null)
                                    ->openUrlInNewTab(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Aliado de servicio nivel 2 (ILS)')
                    ->icon(Heroicon::OutlinedDocumentCheck)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('vaucherIls')
                                    ->label('Voucher ILS')
                                    ->default(fn (Affiliate $record): string => filled($record->vaucherIls) ? (string) $record->vaucherIls : '—')
                                    ->icon(Heroicon::OutlinedDocumentText)
                                    ->badge()
                                    ->color('primary')
                                    ->copyable(),
                                TextEntry::make('dateInit')
                                    ->label('Inicio')
                                    ->default(fn (Affiliate $record): string => filled($record->dateInit) ? (string) $record->dateInit : '—')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('dateEnd')
                                    ->label('Vencimiento')
                                    ->default(fn (Affiliate $record): string => filled($record->dateEnd) ? (string) $record->dateEnd : '—')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->badge()
                                    ->color('primary'),
                                IconEntry::make('document_ils_entry')
                                    ->label('Documento ILS')
                                    ->icon(fn (Affiliate $record): string => filled($record->document_ils)
                                        ? 'heroicon-o-check-circle'
                                        : 'heroicon-o-x-circle')
                                    ->color(fn (Affiliate $record): string => filled($record->document_ils) ? 'success' : 'danger')
                                    ->url(fn (Affiliate $record): ?string => filled($record->document_ils)
                                        ? asset('storage/'.$record->document_ils)
                                        : null)
                                    ->openUrlInNewTab(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Beneficios del plan')
                    ->icon(Heroicon::OutlinedQueueList)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'lg' => 2])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('plan.benefitPlans.description')
                                    ->label('Beneficios')
                                    ->badge()
                                    ->color('success')
                                    ->listWithLineBreaks(),
                                TextEntry::make('plan.benefitPlans.limit.description')
                                    ->label('Límites')
                                    ->badge()
                                    ->color('gray')
                                    ->listWithLineBreaks(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Cuestionario médico')
                    ->description('Visible para el plan configurado (ID 3).')
                    ->icon(Heroicon::OutlinedHeart)
                    ->collapsed()
                    ->hidden(fn (Affiliate $record): bool => (int) $record->plan_id !== 3)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'lg' => 2])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema(self::medicalQuestionIconEntries()),
                    ])
                    ->columnSpanFull(),

                Section::make('Responsable de pago')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('affiliation.full_name_payer')
                                    ->label('Nombre')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->weight('medium')
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('—'),
                                TextEntry::make('affiliation.nro_identificacion_payer')
                                    ->label('Identificación')
                                    ->copyable()
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('—'),
                                TextEntry::make('affiliation.phone_payer')
                                    ->label('Teléfono')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('affiliation.email_payer')
                                    ->label('Correo')
                                    ->icon(Heroicon::OutlinedEnvelope)
                                    ->copyable()
                                    ->placeholder('—')
                                    ->wrap(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
