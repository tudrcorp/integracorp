<?php

namespace App\Filament\Business\Resources\Affiliations\RelationManagers;

use BackedEnum;
use Carbon\Carbon;
use App\Models\Fee;
use App\Models\Plan;
use App\Models\AgeRange;
use App\Models\Coverage;
use App\Models\Affiliate;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Alignment;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Business\Resources\Affiliations\AffiliationResource;

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
                                'MADRE'     => 'MADRE',
                                'PADRE'     => 'PADRE',
                                'ESPOSA'    => 'ESPOSA',
                                'ESPOSO'    => 'ESPOSO',
                                'HIJO'      => 'HIJO',
                                'HIJA'      => 'HIJA',
                            ]),
                        Textarea::make('address')
                            ->label('Direccion')
                            ->columnSpanFull()
                            ->required()
                            ->autosize(),
                        Hidden::make('created_by')->default(Auth::user()->name),
                        Hidden::make('status')->default('ACTIVO'),
                        Fieldset::make('Plan de afiliación')
                            ->schema([
                                Select::make('plan_id')
                                    ->options(function () {
                                        return Plan::all()->pluck('description', 'id');
                                    })
                                    ->label('Planes')
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Obligatorio',
                                    ])
                                    ->preload()
                                    ->placeholder('Seleccione plan(es)'),

                                Select::make('age_range_id')
                                    ->label('Rango de edad')
                                    ->options(function (get $get, $state) {
                                        Log::info($state);
                                        return AgeRange::where('plan_id', $get('plan_id'))->get()->pluck('range', 'id');
                                    })
                                    ->searchable()
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Obligatorio',
                                    ])
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->preload(),

                                Select::make('coverage_id')
                                    ->label('Cobertura')
                                    ->options(function (get $get) {
                                        if ($get('age_range_id') == 1 || $get('age_range_id') == NULL) {
                                            return [];
                                        }
                                        $arrayFee = AgeRange::where('plan_id', $get('plan_id'))->where('id', $get('age_range_id'))->with('fees')->get()->toArray();
                                        return collect($arrayFee[0]['fees'])->pluck('coverage', 'coverage_id');
                                    })
                                    ->searchable()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->preload(),

                                TextInput::make('fee')
                                    ->label('Tarifa Anual')
                                    ->live(onBlur: true)
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Obligatorio',
                                    ])
                                    ->prefixIcon('heroicon-s-globe-europe-africa'),
                                Select::make('payment_frequency')
                                    ->label('Frecuencia de pago')
                                    ->live(onBlur: true)
                                    ->options([
                                        'ANUAL'      => 'ANUAL',
                                        'SEMESTRAL'  => 'SEMESTRAL',
                                        'TRIMESTRAL' => 'TRIMESTRAL',
                                    ])
                                    ->searchable()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Obligatorio',
                                    ])
                                    ->preload()
                                    ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                        if  ($state == 'ANUAL') {
                                            $set('total_amount', $get('fee'));
                                        }
                                        if  ($state == 'SEMESTRAL') {
                                            $set('total_amount', $get('fee') / 2);
                                        }
                                        if  ($state == 'TRIMESTRAL') {
                                            $set('total_amount', $get('fee') / 4);
                                        }
                                    }),
                                TextInput::make('total_amount')
                                    ->label('Monto Total')
                                    ->required()
                                    ->live()
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->validationMessages([
                                        'required'  => 'Campo Obligatorio',
                                    ])
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
                    ->label('Plan')
                    ->badge(),
                TextColumn::make('ageRange.range')
                    ->suffix(' Años')
                    ->label('Rango de Edad')
                    ->badge(),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->badge(),
                TextColumn::make('fee')
                    ->label('Tarifa Anual')
                    ->badge(),
                TextColumn::make('total_amount')
                    ->label('Total a Pagar')
                    ->badge(),
                TextColumn::make('status')
                    ->color(function (string $state): string {
                        return match ($state) {
                            'ACTIVO'    => 'success',
                            'INACTIVO'  => 'danger',
                            'EXCLUIDO'  => 'danger',
                            default     => 'primary',
                        };
                    })
                    ->badge()
                    ->label('Estatus'),
                TextColumn::make('vaucherIls')
                    ->label('Voucher ILS')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->default(fn($record) => $record->vaucherIls == null ? '--------' : $record->vaucherIls),
                TextColumn::make('dateInit')
                    ->label('Fecha Inicio')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->default(fn($record) => $record->dateInit == null ? '--/--/---' : $record->dateInit),
                TextColumn::make('dateEnd')
                    ->label('Fecha Fin')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->default(fn($record) => $record->DateEnd == null ? '--/--/---' : $record->DateEnd),
                TextColumn::make('numberDays')
                    ->label('Dias Cobertura')
                    ->suffix(' Dias Restantes')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->default(fn($record) => $record->numberDays == null ? '0 ' : $record->numberDays),
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
                        return asset('storage/' . $record->document_ils);
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
                        ->icon('heroicon-o-paper-clip')
                        ->requiresConfirmation()
                        ->modalWidth(Width::ExtraLarge)
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
                                    Grid::make(2)->schema([
                                        DatePicker::make('dateInit')
                                            ->label('Desde')
                                            ->format('d-m-Y')
                                            ->required(),
                                        DatePicker::make('dateEnd')
                                            ->label('Hasta')
                                            ->format('d-m-Y')
                                            ->required(),
    
                                    ]),
                                    Grid::make(1)->schema([
                                        FileUpload::make('document_ils')
                                            ->label('Documento/Comprobante ILS')
                                            ->required(),
                                    ])
                                ])
                        ])
                        ->action(function (Affiliate $record, array $data): void {
    
                            $record->update([
                                'vaucherIls'    => $data['vaucherIls'],
                                'dateInit'      => $data['dateInit'],
                                'dateEnd'       => $data['dateEnd'],
                                'numberDays'    => 180,
                                'document_ils'  => $data['document_ils']
                            ]);
    
                            Notification::make()
                                ->success()
                                ->title('Vaucher ILS Activado')
                                ->send();
                        })
                        ->hidden(function (Affiliate $record): bool {
                            if ($record->vaucherIls != null) {
                                return true;
                            }
                            return false;
                        }),
                    EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-s-pencil')
                        ->color('warning'),
                    Action::make('changet_status')
                        ->label('Dar de Baja')
                        ->icon('heroicon-s-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Affiliate $record): void {
                            
                            //... Actualizo la afiliacion
                            $owner = $this->getOwnerRecord();
                            $owner->fee_anual = $owner->fee_anual - $record->fee;
                            $owner->total_amount = $owner->total_amount - $record->total_amount;
                            $owner->family_members = $owner->family_members - 1;
                            $owner->save();

                            //... Actualizo el familiar
                            $record->update([
                                'status' => 'INACTIVO'
                            ]);
                            
                            Notification::make()
                                ->success()
                                ->title('Afiliacion de Baja')
                                ->send();
                        })
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
                    //Actualizo el total de familiarles en la afiliacion
                    ->after(function (array $data) {
                        $record = $this->getOwnerRecord();
                        $record->fee_anual = $record->fee_anual + $data['fee'];
                        $record->total_amount = $record->total_amount + $data['total_amount'];
                        $record->family_members = $record->family_members + 1;
                        $record->save();
                        return;
                    })
                    ->hidden(fn() => Auth::user()->is_business_admin != 1),
            ]);
    }
}