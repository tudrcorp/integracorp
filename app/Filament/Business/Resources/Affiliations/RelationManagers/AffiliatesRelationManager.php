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
use Filament\Support\Enums\Width;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Log;
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
                            ->label('Numero de Identificacion')
                            ->required()
                            ->numeric(),
                        Select::make('sex')
                            ->label('Genero')
                            ->required()
                            ->options([
                                'MASCULINO' => 'MASCULINO',
                                'FEMENINO' => 'FEMENINO',
                            ]),
                        Grid::make()
                            ->schema([
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
                                
                            ])->columnSpanFull()->columns(2),
                        Textarea::make('address')
                            ->label('Direccion')
                            ->columnSpanFull()
                            ->required()
                            ->autosize(),
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

                                Select::make('fee')
                                    ->label('Tarifa Anual')
                                    ->options(function (get $get) {
                                        Log::info(Fee::where('age_range_id', $get('age_range_id'))->where('coverage_id', $get('coverage_id'))->get()->pluck('price', 'price'));
                                        return Fee::where('age_range_id', $get('age_range_id'))->where('coverage_id', $get('coverage_id'))->get()->pluck('price', 'price');
                                    })
                                    ->live()
                                    ->searchable()
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Obligatorio',
                                    ])
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->preload(),
                                Select::make('payment_frequency')
                                    ->label('Frecuencia de pago')
                                    ->live()
                                    ->options([
                                        'ANUAL'      => 'ANUAL',
                                        'SEMESTRAL'  => 'SEMESTRAL',
                                        'TRIMESTRAL' => 'TRIMESTRAL',
                                    ])
                                    ->searchable()
                                    ->live()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Obligatorio',
                                    ])
                                    ->preload(),
                            ])->columnSpanFull()->columns(2),

                    ])->columnSpanFull()->columns(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('CARGA FAMILIAR')
            ->description('Lista de familiares afiliados')
            ->recordTitleAttribute('affiliation_id')
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
                TextColumn::make('status')
                    ->color(function (string $state): string {
                        return match ($state) {
                            'ACTIVO' => 'success',
                            'INACTIVO' => 'danger',
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
                Action::make('upload_info_ils')
                    ->label('Vaucher ILS')
                    ->color('warning')
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
                DeleteAction::make()
                    ->label('Dar de Baja')
                    ->icon('heroicon-s-trash')
                    ->color('danger')
                    ->requiresConfirmation(),
                    
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar Familiar')
                    ->icon('heroicon-s-user-plus')
                    //Actualizo el total de familiarles en la afiliacion
                    ->after(function (array $data) {
                        $record = $this->getOwnerRecord();
                        if($record->payment_frequency == 'ANUAL'){
                            $record->fee_anual = $record->fee_anual + $data['fee'];
                            $record->total_amount = $record->total_amount + $data['fee'];
                            $record->family_members = $record->family_members + 1;
                            $record->save();
                            return;
                        }
                        if( $record->payment_frequency == 'SEMESTRAL'){
                            $record->fee_anual = $record->fee_anual + $data['fee'];
                            $record->total_amount = $record->total_amount + ($data['fee'] / 2);
                            $record->family_members = $record->family_members + 1;
                            $record->save();
                            return;
                        }
                        if( $record->payment_frequency == 'TRIMESTRAL'){
                            $record->fee_anual = $record->fee_anual + $data['fee'];
                            $record->total_amount = $record->total_amount + ($data['fee'] / 4);
                            $record->family_members = $record->family_members + 1;
                            $record->save();
                            return;
                        }
                    }),
            ]);
    }
}