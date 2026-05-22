<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Affiliations\RelationManagers;

use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\AgeRange;
use App\Models\Plan;
use App\Support\AffiliateVaucherIlsRemainingDays;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\FilamentDateDisplay;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AffiliatesRelationManager extends RelationManager
{
    protected static string $relationship = 'affiliates';

    protected static ?string $title = 'Familiares afiliados';

    protected static string|BackedEnum|null $icon = 'heroicon-s-user-group';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos personales')
                    ->description('Identificación y parentesco del familiar.')
                    ->icon(Heroicon::User)
                    ->schema([
                        TextInput::make('full_name')
                            ->required()
                            ->label('Nombre completo'),
                        TextInput::make('nro_identificacion')
                            ->label('Numero de Identificación')
                            ->required()
                            ->numeric(),
                        Select::make('sex')
                            ->label('Genero')
                            ->required()
                            ->options([
                                'MASCULINO' => 'MASCULINO',
                                'FEMENINO' => 'FEMENINO',
                            ]),
                        TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->required()
                            ->email(),
                        TextInput::make('phone')
                            ->label('Numero de Teléfono')
                            ->required()
                            ->numeric(),
                        DatePicker::make('birth_date')
                            ->label('Fecha de Nacimiento')
                            ->required()
                            ->live()
                            ->format('d/m/Y')
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('age', intval(Carbon::createFromFormat('d/m/Y', $state)->diffInYears(now())));
                            }),
                        TextInput::make('age')
                            ->label('Edad')
                            ->required()
                            ->live()
                            ->numeric(),
                        Select::make('relationship')
                            ->label('Parentesco')
                            ->required()
                            ->options([
                                'TITULAR' => 'TITULAR',
                                'MADRE' => 'MADRE',
                                'PADRE' => 'PADRE',
                                'ESPOSA' => 'ESPOSA',
                                'ESPOSO' => 'ESPOSO',
                                'HIJO' => 'HIJO',
                                'HIJA' => 'HIJA',
                                'OTRO' => 'OTRO',
                            ]),
                        Textarea::make('address')
                            ->label('Dirección')
                            ->columnSpanFull()
                            ->required()
                            ->autosize(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Plan de afiliación')
                    ->description('Cobertura, tarifa y frecuencia de pago.')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->schema([
                        Fieldset::make('Plan de afiliación')
                            ->schema([
                                Select::make('plan_id')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Campo Obligatorio',
                                    ])
                                    ->options(function () {
                                        return Plan::all()->pluck('description', 'id');
                                    })
                                    ->label('Planes')
                                    ->validationMessages([
                                        'required' => 'Campo Obligatorio',
                                    ])
                                    ->preload()
                                    ->placeholder('Seleccione plan(es)'),

                                Select::make('age_range_id')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Campo Obligatorio',
                                    ])
                                    ->label('Rango de edad')
                                    ->options(fn (Get $get): array => AgeRange::query()
                                        ->where('plan_id', $get('plan_id'))
                                        ->pluck('range', 'id')
                                        ->all())
                                    ->searchable()
                                    ->validationMessages([
                                        'required' => 'Campo Obligatorio',
                                    ])
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->preload(),

                                Select::make('coverage_id')
                                    ->validationMessages([
                                        'required' => 'Campo Obligatorio',
                                    ])
                                    ->label('Cobertura')
                                    ->options(function (get $get) {
                                        if ($get('age_range_id') == 1 || $get('age_range_id') == null) {
                                            return [];
                                        }
                                        $arrayFee = AgeRange::where('plan_id', $get('plan_id'))->where('id', $get('age_range_id'))->with('fees')->get()->toArray();

                                        return collect($arrayFee[0]['fees'])->pluck('coverage', 'coverage_id');
                                    })
                                    ->searchable()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->preload(),

                                TextInput::make('fee')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Campo Obligatorio',
                                    ])
                                    ->label('Tarifa Anual')
                                    ->live(onBlur: true)
                                    ->prefixIcon('heroicon-s-globe-europe-africa'),
                                Select::make('payment_frequency')

                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Campo Obligatorio',
                                    ])
                                    ->label('Frecuencia de pago')
                                    ->live(onBlur: true)
                                    ->options([
                                        'ANUAL' => 'ANUAL',
                                        'SEMESTRAL' => 'SEMESTRAL',
                                        'TRIMESTRAL' => 'TRIMESTRAL',
                                    ])
                                    ->searchable()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->validationMessages([
                                        'required' => 'Campo Obligatorio',
                                    ])
                                    ->preload()
                                    ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                        if ($state == 'ANUAL') {
                                            $set('total_amount', $get('fee'));
                                        }
                                        if ($state == 'SEMESTRAL') {
                                            $set('total_amount', $get('fee') / 2);
                                        }
                                        if ($state == 'TRIMESTRAL') {
                                            $set('total_amount', $get('fee') / 4);
                                        }
                                    }),
                                TextInput::make('total_amount')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Campo Obligatorio',
                                    ])
                                    ->label('Monto Total')
                                    ->live()
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->validationMessages([
                                        'required' => 'Campo Obligatorio',
                                    ]),
                            ])
                            ->columnSpanFull()
                            ->columns(2),
                    ])
                    ->columnSpanFull(),
                Section::make('Voucher ILS')
                    ->description('Cobertura internacional de servicios, si aplica.')
                    ->icon(Heroicon::Ticket)
                    ->schema([
                        Fieldset::make('Voucher ILS')
                            ->schema([
                                Grid::make()->schema([
                                    TextInput::make('vaucherIls')
                                        ->label('Vaucher ILS'),
                                    DatePicker::make('dateInit')
                                        ->label('Desde')
                                        ->live()
                                        ->format('d-m-Y')
                                        ->afterStateUpdated(function (Set $set, Get $get): void {
                                            $days = AffiliateVaucherIlsRemainingDays::remainingDaysUntilEnd($get('dateEnd'));
                                            $set('numberDays', $days ?? 0);
                                        }),
                                    DatePicker::make('dateEnd')
                                        ->label('Hasta')
                                        ->live()
                                        ->format('d-m-Y')
                                        ->afterStateUpdated(function (Set $set, Get $get): void {
                                            $days = AffiliateVaucherIlsRemainingDays::remainingDaysUntilEnd($get('dateEnd'));
                                            $set('numberDays', $days ?? 0);
                                        }),
                                    TextInput::make('numberDays')
                                        ->label('Dias Restantes')
                                        ->disabled()
                                        ->dehydrated(),
                                ])->columnSpanFull()->columns(3),
                                Grid::make(1)->schema([
                                    FileUpload::make('document_ils')
                                        ->label('Documento/Comprobante ILS')
                                        ->required(),
                                ]),
                            ])
                            ->columnSpanFull()
                            ->columns(2),
                        Hidden::make('created_by')->default(fn (): string => Auth::user()?->name ?? ''),
                        Hidden::make('status')->default('ACTIVO'),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['plan', 'ageRange', 'coverage'])
                ->orderByRaw("CASE WHEN relationship = 'TITULAR' THEN 0 ELSE 1 END")
                ->orderBy('full_name'))
            ->heading('Carga familiar')
            ->description('Familiares vinculados a esta afiliación. Use búsqueda, filtros y columnas para ajustar la vista.')
            ->emptyStateHeading('Sin familiares registrados')
            ->emptyStateDescription('Agregue un familiar con el botón superior o complete la pre-afiliación.')
            ->emptyStateIcon(Heroicon::UserGroup)
            ->striped()
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50])
            ->columns([
                TextColumn::make('relationship')
                    ->label('Parentesco')
                    ->icon(Heroicon::UserCircle)
                    ->badge()
                    ->color(fn (string $state): string => $state === 'TITULAR' ? 'primary' : 'gray')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('full_name')
                    ->label('Nombre completo')
                    ->icon(Heroicon::User)
                    ->weight(FontWeight::SemiBold)
                    ->wrap()
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Nombre copiado')
                    ->extraCellAttributes(['class' => 'min-w-44 sm:min-w-56']),
                TextColumn::make('nro_identificacion')
                    ->label('C.I.')
                    ->icon(Heroicon::Identification)
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->copyable()
                    ->copyMessage('C.I. copiada'),
                TextColumn::make('age')
                    ->label('Edad')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('birth_date')
                    ->label('Nacimiento')
                    ->icon(Heroicon::CalendarDays)
                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                    ->toggleable(),
                TextColumn::make('sex')
                    ->label('Sexo')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->icon(Heroicon::Signal)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ACTIVO' => 'success',
                        'INACTIVO', 'EXCLUIDO' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->limit(24)
                    ->tooltip(fn (Affiliate $record): ?string => strlen((string) ($record->plan?->description ?? '')) > 24
                        ? $record->plan?->description
                        : null),
                TextColumn::make('fee')
                    ->label('Tarifa anual')
                    ->icon(Heroicon::CurrencyDollar)
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' US$')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Monto período')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' US$')
                    ->sortable(),
                TextColumn::make('payment_frequency')
                    ->label('Frecuencia')
                    ->badge()
                    ->color('info')
                    ->toggleable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon(Heroicon::Phone)
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->label('Correo')
                    ->icon(Heroicon::Envelope)
                    ->copyable()
                    ->limit(28)
                    ->tooltip(fn (Affiliate $record): ?string => strlen((string) ($record->email ?? '')) > 28 ? $record->email : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->icon(Heroicon::MapPin)
                    ->wrap()
                    ->lineClamp(2)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ageRange.range')
                    ->label('Rango edad')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' US$')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('vaucherIls')
                    ->label('Voucher ILS')
                    ->badge()
                    ->color('warning')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dateInit')
                    ->label('ILS desde')
                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                    ->badge()
                    ->color('warning')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dateEnd')
                    ->label('ILS hasta')
                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                    ->badge()
                    ->color('warning')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('numberDays')
                    ->label('Días ILS')
                    ->suffix(' días')
                    ->badge()
                    ->color('warning')
                    ->getStateUsing(function (Affiliate $record): string {
                        if ($record->dateEnd === null) {
                            return '—';
                        }
                        $days = AffiliateVaucherIlsRemainingDays::remainingDaysUntilEnd($record->dateEnd);

                        return $days === null ? '—' : (string) $days;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('document_ils')
                    ->alignment(Alignment::Center)
                    ->label('Doc. ILS')
                    ->icon(fn (Affiliate $record): string => $record->document_ils !== null
                        ? 'heroicon-o-check-circle'
                        : 'heroicon-o-x-circle')
                    ->color(fn (Affiliate $record): string => $record->document_ils !== null
                        ? 'success'
                        : 'danger')
                    ->url(fn (Affiliate $record): ?string => $record->document_ils
                        ? asset('storage/'.$record->document_ils)
                        : null)
                    ->openUrlInNewTab()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'ACTIVO' => 'Activo',
                        'INACTIVO' => 'Inactivo',
                        'EXCLUIDO' => 'Excluido',
                    ])
                    ->native(false),
                SelectFilter::make('relationship')
                    ->label('Parentesco')
                    ->options([
                        'TITULAR' => 'Titular',
                        'CONYUGE' => 'Cónyuge',
                        'HIJO' => 'Hijo',
                        'HIJA' => 'Hija',
                        'PADRE' => 'Padre',
                        'MADRE' => 'Madre',
                        'OTRO' => 'Otro',
                    ])
                    ->native(false),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('upload_info_ils')
                        ->label('Voucher ILS')
                        ->color('info')
                        ->icon(Heroicon::Ticket)
                        ->modalWidth(Width::TwoExtraLarge)
                        ->modalHeading('Activar cobertura ILS')
                        ->modalDescription('Indique vigencia y adjunte el comprobante del voucher.')
                        ->form([
                            Section::make('Datos del voucher')
                                ->description('Todos los campos son obligatorios.')
                                ->icon(Heroicon::CheckCircle)
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('vaucherIls')
                                            ->label('Vaucher ILS')
                                            ->required(),
                                    ]),
                                    Grid::make(3)->schema([
                                        DatePicker::make('dateInit')
                                            ->label('Desde')
                                            ->live()
                                            ->format('d/m/Y')
                                            ->afterStateUpdated(function (Set $set, Get $get): void {
                                                $days = AffiliateVaucherIlsRemainingDays::remainingDaysUntilEnd($get('dateEnd'));
                                                $set('numberDays', $days ?? 0);
                                            })
                                            ->required(),
                                        DatePicker::make('dateEnd')
                                            ->label('Hasta')
                                            ->live()
                                            ->format('d/m/Y')
                                            ->afterStateUpdated(function (Set $set, Get $get): void {
                                                $days = AffiliateVaucherIlsRemainingDays::remainingDaysUntilEnd($get('dateEnd'));
                                                $set('numberDays', $days ?? 0);
                                            })
                                            ->required(),
                                        TextInput::make('numberDays')
                                            ->label('Dias Restantes')
                                            ->disabled()
                                            ->dehydrated()
                                            ->required(),

                                    ]),
                                    Grid::make(1)->schema([
                                        FileUpload::make('document_ils')
                                            ->label('Documento/Comprobante ILS')
                                            ->required(),
                                    ]),
                                ]),
                        ])
                        ->action(function (Affiliate $record, array $data): void {

                            try {

                                $record->update([
                                    'vaucherIls' => $data['vaucherIls'],
                                    'dateInit' => $data['dateInit'],
                                    'dateEnd' => $data['dateEnd'],
                                    'numberDays' => AffiliateVaucherIlsRemainingDays::remainingDaysUntilEnd($data['dateEnd']) ?? 0,
                                    'document_ils' => $data['document_ils'],
                                ]);

                                Notification::make()
                                    ->success()
                                    ->title('Voucher ILS activado')
                                    ->icon(Heroicon::CheckCircle)
                                    ->send();
                            } catch (\Throwable $th) {
                                Notification::make()
                                    ->danger()
                                    ->title('Error al activar ILS')
                                    ->body('No se pudo guardar el voucher. Intente nuevamente.')
                                    ->send();
                            }
                        })
                        ->hidden(fn (Affiliate $record): bool => $record->vaucherIls !== null),

                    EditAction::make()
                        ->label('Editar')
                        ->icon(Heroicon::PencilSquare)
                        ->color('warning')
                        ->modalWidth(Width::SevenExtraLarge)
                        ->modalHeading('Editar familiar')
                        ->modalDescription('Actualice datos personales, plan y montos. Los cambios recalculan los totales de la afiliación.')
                        ->after(function (Affiliate $record, array $data): void {
                            try {
                                $record->refresh();

                                $annualFee = (float) ($data['fee'] ?? 0);
                                $frequency = (string) ($data['payment_frequency'] ?? 'ANUAL');

                                $recalculatedTotal = $this->totalAmountForPaymentFrequency($annualFee, $frequency);

                                $record->update([
                                    'plan_id' => $data['plan_id'],
                                    'coverage_id' => $data['coverage_id'],
                                    'age_range_id' => $data['age_range_id'],
                                    'fee' => $annualFee,
                                    'total_amount' => $recalculatedTotal,
                                    'payment_frequency' => $frequency,
                                ]);

                                $owner = $this->getOwnerRecord();
                                $owner->plan_id = (int) $data['plan_id'];
                                $owner->coverage_id = (int) $data['coverage_id'];
                                $owner->payment_frequency = $frequency;
                                $this->recalculateAffiliationTotalsFromAffiliates($owner);

                            } catch (\Throwable) {
                                Notification::make()
                                    ->danger()
                                    ->title('Error al actualizar')
                                    ->body('No se pudieron guardar los cambios del familiar.')
                                    ->send();
                            }
                        }),

                    Action::make('changet_status')
                        ->label('Dar de baja')
                        ->icon(Heroicon::Trash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Dar de baja al familiar')
                        ->modalDescription('El familiar quedará inactivo y dejará de sumar en los totales de la afiliación.')
                        ->action(function (Affiliate $record): void {
                            $record->update([
                                'status' => 'INACTIVO',
                            ]);

                            app(AffiliationAffiliateFeeCalculator::class)
                                ->recalculateAffiliationTotalsFromAffiliates($this->getOwnerRecord());

                            Notification::make()
                                ->success()
                                ->title('Familiar dado de baja')
                                ->icon(Heroicon::CheckCircle)
                                ->send();
                        }),

                    Action::make('asociated_amount_affiliation')
                        ->label('Asociar montos')
                        ->color('success')
                        ->icon(Heroicon::Cog6Tooth)
                        ->requiresConfirmation()
                        ->modalHeading('Asociar montos de la afiliación')
                        ->modalDescription('Asigna plan, cobertura y tarifa según la edad del familiar y la cobertura de la afiliación.')
                        ->action(function (Affiliate $record): void {
                            $affiliation = Affiliation::query()->findOrFail($record->affiliation_id);
                            $calculator = app(AffiliationAffiliateFeeCalculator::class);

                            if (! $calculator->isInitialPlanWithoutCoverage($affiliation) && blank($affiliation->coverage_id)) {
                                Notification::make()
                                    ->danger()
                                    ->title('Cobertura no definida')
                                    ->body('La afiliación no tiene cobertura asociada. Defínala antes de asociar montos.')
                                    ->send();

                                return;
                            }

                            $affiliateAge = $calculator->resolveAffiliateAge($record);

                            if ($affiliateAge === null) {
                                Notification::make()
                                    ->danger()
                                    ->title('Edad no disponible')
                                    ->body('El familiar debe tener edad o fecha de nacimiento para calcular la tarifa.')
                                    ->send();

                                return;
                            }

                            $fee = $calculator->resolveFeeForAffiliateAge($affiliation, $affiliateAge);

                            if ($fee === null) {
                                $context = $calculator->isInitialPlanWithoutCoverage($affiliation)
                                    ? 'plan inicial (sin cobertura)'
                                    : "cobertura #{$affiliation->coverage_id}";

                                Notification::make()
                                    ->danger()
                                    ->title('Tarifa no encontrada')
                                    ->body("No hay tarifa para {$context} y edad {$affiliateAge} años.")
                                    ->send();

                                return;
                            }

                            try {
                                if (! $calculator->applyAmountsToAffiliate($affiliation, $record)) {
                                    Notification::make()
                                        ->danger()
                                        ->title('Error al asociar')
                                        ->body('No se pudieron guardar los montos del familiar.')
                                        ->send();

                                    return;
                                }

                                $calculator->recalculateAffiliationTotalsFromAffiliates($affiliation);

                                $annualFee = (float) $fee->price;
                                $periodAmount = $calculator->totalAmountForPaymentFrequency(
                                    $annualFee,
                                    (string) ($affiliation->payment_frequency ?? 'ANUAL'),
                                );

                                Notification::make()
                                    ->success()
                                    ->title('Montos asociados')
                                    ->body("Tarifa anual US$ {$annualFee} ({$fee->range}) · período US$ {$periodAmount}.")
                                    ->icon(Heroicon::CheckCircle)
                                    ->send();
                            } catch (\Throwable) {
                                Notification::make()
                                    ->danger()
                                    ->title('Error al asociar')
                                    ->body('No se pudieron guardar los montos del familiar.')
                                    ->send();
                            }
                        })
                        ->hidden(fn (): bool => Auth::user()?->is_business_admin != 1),

                ])
                    ->icon(Heroicon::EllipsisVertical)
                    ->hidden(fn (): bool => $this->getOwnerRecord()->status === 'EXCLUIDO'
                        || Auth::user()?->is_business_admin != 1),

            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar familiar')
                    ->icon(Heroicon::UserPlus)
                    ->color('success')
                    ->createAnother(false)
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalHeading('Nuevo familiar')
                    ->modalDescription('Complete los datos del familiar y el plan. Los totales de la afiliación se actualizarán al guardar.')
                    ->after(function (): void {
                        app(AffiliationAffiliateFeeCalculator::class)
                            ->recalculateAffiliationTotalsFromAffiliates($this->getOwnerRecord());
                    })
                    ->hidden(fn (): bool => ! in_array('SUPERADMIN', Auth::user()?->departament ?? [], true)),

            ]);
    }

    public function totalAmountForPaymentFrequency(float $annualFee, string $frequency): float
    {
        return app(AffiliationAffiliateFeeCalculator::class)
            ->totalAmountForPaymentFrequency($annualFee, $frequency);
    }

    public function recalculateAffiliationTotalsFromAffiliates(Affiliation $owner): void
    {
        app(AffiliationAffiliateFeeCalculator::class)
            ->recalculateAffiliationTotalsFromAffiliates($owner);
    }
}
