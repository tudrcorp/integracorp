<?php

namespace App\Filament\Business\Resources\Affiliations\RelationManagers;

use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\AgeRange;
use App\Models\Fee;
use App\Models\Plan;
use App\Support\AffiliateVaucherIlsRemainingDays;
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
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AffiliatesRelationManager extends RelationManager
{
    protected static string $relationship = 'affiliates';

    protected static ?string $title = 'FAMILIARES AFILIADOS';

    protected static string|BackedEnum|null $icon = 'heroicon-s-user-group';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('FAMILIAR')
                    ->description('Fomulario de familiar.')
                    ->icon('heroicon-s-user')
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
                            ->label('Direccion')
                            ->columnSpanFull()
                            ->required()
                            ->autosize(),
                        Hidden::make('created_by')->default(Auth::user()->name),
                        Hidden::make('status')->default('ACTIVO'),

                        // ... INFORMACION DEL PLAN
                        Fieldset::make('Plan de afiliación')
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
                                    ->options(function (get $get, $state) {
                                        Log::info($state);

                                        return AgeRange::where('plan_id', $get('plan_id'))->get()->pluck('range', 'id');
                                    })
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
                            ])->columnSpanFull()->columns(2),

                        // ... VAUCHER ILS
                        Fieldset::make('Vaucher ILS')
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
                            ])->columnSpanFull()->columns(2),

                    ])->columnSpanFull()->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('CARGA FAMILIAR')
            ->description('Lista de familiares afiliados')
            ->columns([
                TextInputColumn::make('full_name')
                    ->label('Nombre y Apellidos'),
                TextInputColumn::make('nro_identificacion')
                    ->label('Nro Identificacion'),
                TextColumn::make('birth_date')
                    ->label('Fecha de Nacimiento'),
                TextColumn::make('age')
                    ->label('Edad'),
                TextColumn::make('sex')
                    ->label('Genero'),
                TextColumn::make('relationship')
                    ->label('Parentesco'),
                TextColumn::make('address')
                    ->label('Direccion Completa'),
                TextInputColumn::make('phone')
                    ->label('Numero de Telefono'),
                TextInputColumn::make('email')
                    ->label('Correo Electronico'),
                TextColumn::make('relationship')
                    ->label('Parentesco'),
                TextColumn::make('plan.description')
                    ->badge()
                    ->color('success'),
                TextColumn::make('ageRange.range')
                    ->label('Rango de Edad')
                    ->badge()
                    ->color('success'),
                TextColumn::make('coverage.price')
                    ->badge()
                    ->color('success'),
                TextColumn::make('payment_frequency')
                    ->label('Frecuencia de Pago')
                    ->badge()
                    ->color('success'),
                TextColumn::make('fee')
                    ->label('Tarifa Anual')
                    ->badge()
                    ->color('success'),
                TextColumn::make('total_amount')
                    ->label('Monto a Pagar')
                    ->badge()
                    ->color('success'),
                TextColumn::make('status')
                    ->color(function (string $state): string {
                        return match ($state) {
                            'ACTIVO' => 'success',
                            'INACTIVO' => 'danger',
                            'EXCLUIDO' => 'danger',
                            default => 'primary',
                        };
                    })
                    ->badge()
                    ->label('Estatus'),
                TextColumn::make('vaucherIls')
                    ->label('Voucher ILS')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->default(fn ($record) => $record->vaucherIls == null ? '--------' : $record->vaucherIls),
                TextColumn::make('dateInit')
                    ->label('Fecha Inicio')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->default(fn ($record) => $record->dateInit == null ? '--/--/---' : $record->dateInit),
                TextColumn::make('dateEnd')
                    ->label('Fecha Fin')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->default(fn ($record) => $record->dateEnd === null ? '--/--/---' : $record->dateEnd),
                TextColumn::make('numberDays')
                    ->label('Dias Cobertura')
                    ->suffix(' Dias Restantes')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->default(function ($record) {
                        if ($record->dateEnd === null) {
                            return '--';
                        }

                        $days = AffiliateVaucherIlsRemainingDays::remainingDaysUntilEnd($record->dateEnd);

                        return $days === null ? '--' : $days;
                    }),
                IconColumn::make('document_ils')
                    ->alignment(Alignment::Center)
                    ->label('Comprobante')
                    ->icon(function ($record) {
                        // Muestra un ícono si la imagen existe
                        return $record->document_ils != null
                            ? 'heroicon-o-check-circle' // Ícono de "check" si la imagen existe
                            : 'heroicon-o-x-circle';   // Ícono de "x" si no existe
                    })
                    // ->iconPosition(IconPosition::After), // Posición del ícono
                    ->color(function ($record) {
                        // Color del ícono basado en la existencia de la imagen
                        return $record->document_ils != null
                            ? 'success' // Verde si la imagen existe
                            : 'danger'; // Rojo si no existe
                    })
                    ->url(function ($record) {
                        return asset('storage/'.$record->document_ils);
                    })
                    ->openUrlInNewTab(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([

                    Action::make('upload_info_ils')
                        ->label('Vaucher ILS')
                        ->color('info')
                        ->icon(Heroicon::Ticket)
                        ->requiresConfirmation()
                        ->modalWidth(Width::TwoExtraLarge)
                        ->modalHeading('Activar afiliacion')
                        ->form([
                            Section::make('ACTIVAR AFILIACION')
                                ->description('Foirmulario de activacion de afiliacion. Campo Requerido(*)')
                                ->icon('heroicon-s-check-circle')
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
                                    ->title('Vaucher ILS Activado')
                                    ->send();
                            } catch (\Throwable $th) {
                                Log::error($th->getMessage());
                                Notification::make()
                                    ->danger()
                                    ->title('Error')
                                    ->body('Hubo un error activando el Vaucher ILS. Por favor, intente nuevamente.')
                                    ->send();
                            }
                        })
                        ->hidden(function (Affiliate $record): bool {
                            if ($record->vaucherIls != null) {
                                return true;
                            }

                            return false;
                        }),

                    EditAction::make()
                        ->label('Editar Afiliación')
                        ->icon('heroicon-s-pencil')
                        ->color('warning')
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

                                $fee_anual = $this->recalculateAffiliationTotalsFromAffiliates($owner->affiliates->toArray());

                                $owner->plan_id = (int) $data['plan_id'];
                                $owner->coverage_id = (int) $data['coverage_id'];
                                $owner->payment_frequency = $frequency;
                                $owner->fee_anual = $fee_anual;
                                $owner->family_members = $owner->affiliates->count();
                                $owner->total_amount = $this->totalAmountForPaymentFrequency($owner->fee_anual, $frequency);
                                $owner->save();

                            } catch (\Throwable $th) {
                                dd($th);
                                Log::error($th->getMessage());
                                Notification::make()
                                    ->danger()
                                    ->title('Error')
                                    ->body('Hubo un error actualizando la afiliación. Por favor, intente nuevamente.')
                                    ->send();
                            }
                        }),

                    Action::make('changet_status')
                        ->label('Dar de Baja')
                        ->icon('heroicon-s-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Affiliate $record): void {

                            $record->update([
                                'status' => 'INACTIVO',
                            ]);

                            $owner = $this->getOwnerRecord();
                            $owner->family_members = $owner->affiliates()->where('status', 'ACTIVO')->count();
                            $owner->save();

                            $this->recalculateAffiliationTotalsFromAffiliates($owner);

                            Notification::make()
                                ->success()
                                ->title('Afiliacion de Baja')
                                ->send();
                        }),

                    Action::make('asociated_amount_affiliation')
                        ->label('Asociar Monto Afiliación')
                        ->color('success')
                        ->icon('heroicon-s-cog')
                        ->action(function (Affiliate $record): void {

                            try {

                                $info_afiliacion = Affiliation::where('id', $record->affiliation_id)->first();

                                $age_range_id = Fee::where('price', $info_afiliacion->fee_anual)->where('coverage_id', $info_afiliacion->coverage_id)->first()->age_range_id;

                                $record->update([
                                    'plan_id' => $info_afiliacion->plan_id,
                                    'coverage_id' => $info_afiliacion->coverage_id,
                                    'age_range_id' => $age_range_id,
                                    'total_amount' => $info_afiliacion->total_amount,
                                ]);

                                Notification::make()
                                    ->success()
                                    ->title('Asociacion Completada!')
                                    ->send();
                            } catch (\Throwable $th) {
                                dd($th);
                                Log::error($th->getMessage());
                                Notification::make()
                                    ->danger()
                                    ->title('Error')
                                    ->body('Hubo un error activando el Vaucher ILS. Por favor, intente nuevamente.')
                                    ->send();
                            }
                        })
                        ->hidden(fn ($record) => Auth::user()->is_business_admin != 1),

                ])->hidden(function ($record): bool {
                    if ($this->getOwnerRecord()->status == 'EXCLUIDO' || Auth::user()->is_business_admin != 1) {
                        return true;
                    }

                    return false;
                }),

            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar Familiar')
                    ->icon('heroicon-s-user-plus')
                    ->after(function (array $data): void {
                        $owner = $this->getOwnerRecord();
                        $owner->family_members = $owner->affiliates()->where('status', 'ACTIVO')->count();
                        $owner->save();

                        $this->recalculateAffiliationTotalsFromAffiliates($owner);
                    })
                    ->hidden(fn () => ! in_array('SUPERADMIN', Auth::user()->departament)),

            ]);
    }

    public function totalAmountForPaymentFrequency(float $annualFee, string $frequency): float
    {
        return match ($frequency) {
            'ANUAL' => round($annualFee, 2),
            'SEMESTRAL' => round($annualFee / 2, 2),
            'TRIMESTRAL' => round($annualFee / 4, 2),
            default => round($annualFee, 2),
        };
    }

    public function recalculateAffiliationTotalsFromAffiliates(array $affiliates): float
    {
        $total_amount = 0;
        foreach ($affiliates as $affiliate) {
            $total_amount += $affiliate['fee'];
        }

        return round($total_amount, 2);
    }
}
