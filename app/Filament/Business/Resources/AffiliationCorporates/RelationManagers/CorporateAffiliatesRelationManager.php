<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\RelationManagers;

use BackedEnum;
use Carbon\Carbon;
use App\Models\Fee;
use App\Models\Plan;

use App\Models\AgeRange;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use App\Models\AffiliateCorporate;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Log;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Alignment;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use App\Models\AfilliationCorporatePlan;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Http\Controllers\AffiliateCorporateController;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;

class CorporateAffiliatesRelationManager extends RelationManager
{
    protected static string $relationship = 'corporateAffiliates';

    protected static ?string $title = 'Afiliado(s)';

    protected static string|BackedEnum|null $icon = 'heroicon-s-user-group';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('FAMILIAR')
                    ->description('Fomulario de familiar.')
                    ->icon('heroicon-s-user')
                    ->schema([
                        TextInput::make('first_name')
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
                            TextInput::make('phone')
                                ->label('Telefono'),
                            TextInput::make('email')
                                ->label('Email address')
                                ->required()
                                ->email(),
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
                                ->numeric()
                                ->disabled()
                                ->dehydrated(),
                            TextInput::make('condition_medical')
                                ->label('Condicion Medica'),
                            DatePicker::make('initial_date')
                                ->label('Fecha de Ingreso a la Empresa')
                                ->format('d/m/Y'),
                            TextInput::make('position_company')
                                ->label('Condicion Medica'),
                            TextInput::make('full_name_emergency')
                                ->label('Contacto de Emergencia'),
                            TextInput::make('phone_emergency')
                                ->label('Telefono de Emergencia'),
                            Textarea::make('address')
                                ->label('Direccion')
                                ->columnSpanFull()
                                ->required()
                                ->autosize(),
                
                            Fieldset::make('Plan de afiliación')
                            ->schema([
                                Select::make('plan_id')
                                    ->options(function ($record) {
                                        return Plan::all()->pluck('description', 'id');
                                    })
                                    ->label('Planes')
                                    ->required()
                                    ->live()  
                                    ->validationMessages([
                                        'required'  => 'Campo Obligatorio',
                                    ])
                                    ->preload()
                                    ->placeholder('Seleccione plan(es)'),

                                Select::make('age_range_id')
                                    ->label('Rango de edad')
                                    ->options(function (Get $get, $state) {
                                        Log::info($get('plan_id'));
                                        return AgeRange::where('plan_id', intval($get('plan_id')))->get()->pluck('range', 'id');
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
                                TextInput::make('payment_frequency')
                                    ->label('Frecuencia de pago')
                                    ->live()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->disabled()
                                    ->dehydrated()   
                                    ->default(function () {
                                        return $this->getOwnerRecord()->payment_frequency;
                                    }),
                                Hidden::make('created_by')->default(Auth::user()->name),
                            ])->columnSpanFull()->columns(2),

                    ])->columnSpanFull()->columns(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('AFILIADOS')
            ->description('Lista de empleados afiliados')
            ->recordTitleAttribute('affiliation_corporate_id')
            ->columns([
                TextColumn::make('first_name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label('Apellido')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Fecha de nacimiento')
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->label('C.I.'),
                TextColumn::make('email')
                    ->label('Email'),
                TextColumn::make('age')
                    ->label('Edad')
                    ->searchable(),
                TextColumn::make('sex')
                    ->label('Sexo'),
                TextColumn::make('phone')
                    ->label('Telefono'),
                TextColumn::make('condition_medical')
                    ->label('Condicion Medica'),
                TextColumn::make('initial_date')
                    ->label('Fecha de Ingreso'),
                TextColumn::make('address')
                    ->label('Direccion'),
                TextColumn::make('full_name_emergency')
                    ->label('Contacto de Emergencia'),
                TextColumn::make('phone_emergency')
                    ->label('Telefono de Emergencia'),
                TextColumn::make('phone_emergency')
                    ->label('Telefono de Emergencia'),
                TextColumn::make('plan.description')
                    ->label('Plan'),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->numeric()
                    ->suffix(' US$'),
                TextColumn::make('payment_frequency')
                    ->alignCenter()
                    ->label('Frecuencia de Pago'),
                TextColumn::make('fee')
                    ->label('Tarifa')
                    ->numeric()
                    ->suffix(' US$'),
                TextColumn::make('subtotal_anual')
                    ->label('Pago Anual')
                    ->numeric()
                    ->alignCenter()
                    ->suffix(' US$'),
                TextColumn::make('subtotal_payment_frequency')
                    ->label('Monto por Frecuencia de Pago')
                    ->numeric()
                    ->alignCenter()
                    ->suffix(' US$'),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'PRE-AFILIADO'  => 'warning',
                            'ACTIVO'        => 'success',
                            'EXCLUIDO'      => 'danger',
                            'INACTIVO'      => 'danger',
                            default         => 'azul',
                        };
                    }),
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
            ->headerActions([
                CreateAction::make()
                    ->label('Crear Afiliado')
                    ->color('success')
                    ->createAnother(false)
                    ->icon(Heroicon::Plus)
                    ->before(function (array $data, CreateAction $action) {
                        $plans = $this->getOwnerRecord()->affiliationCorporatePlans->pluck('plan_id')->toArray();
                        if (!in_array($data['plan_id'], $plans)) {
                            Notification::make()
                                ->title('Error')
                                ->danger()
                                ->icon(Heroicon::ExclamationCircle)
                                ->body('El plan seleccionado no se encuentra en la lista de planes afiliados. Por favor, seleccione un plan que pertenece a la afiliación corporativa')
                                ->send();
                                
                            $action->halt();
                        }
                    })
                    ->using(function (array $data) {
                        
                        $addAffiliate = AffiliateCorporateController::addAffiliate($data, $this->getOwnerRecord());

                        if ($addAffiliate) {
                            Notification::make()
                                ->title('Afiliado creado')
                                ->success()
                                ->icon(Heroicon::CheckCircle)
                                ->body('El afiliado se ha creado correctamente')
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Error')
                                ->danger()
                                ->icon(Heroicon::ExclamationCircle)
                                ->body('Ha ocurrido un error al crear el afiliado')
                                ->send();
                        }

                    })
                    ->hidden(fn() => Auth::user()->is_business_admin != 1),
                    
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('upload_info_ils')
                        ->label('Vaucher ILS')
                        ->color('warning')
                        ->icon('heroicon-o-paper-clip')
                        ->requiresConfirmation()
                        ->modalWidth(Width::ExtraLarge)
                        ->modalHeading('Activar afiliacion')
                        ->form([
                            Section::make('ACTIVAR VAUCHER ILS')
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
                                            ->directory('vauches')
                                            ->required(),
                                    ])
                                ])
                        ])
                        ->action(function (AffiliateCorporate $record, array $data): void {

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
                        ->hidden(function (AffiliateCorporate $record): bool {
                            if ($record->vaucherIls != null) {
                                return true;
                            }
                            return false;
                        }),
                    Action::make('changet_status')
                        ->label('Dar de Baja')
                        ->icon('heroicon-s-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (AffiliateCorporate $record): void {

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
                ])->hidden(fn ($record) => $record->status == 'INACTIVO' || $record->status == 'EXCLUIDO' || Auth::user()->is_business_admin != 1),
                

        ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('INTEGRACORP eliminar el/los registros seleccionados!')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->icon(Heroicon::Trash),

                ]),
            ])->striped()->poll('5s');
    }
}