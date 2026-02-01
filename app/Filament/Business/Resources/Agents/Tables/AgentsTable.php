<?php

namespace App\Filament\Business\Resources\Agents\Tables;


use App\Filament\Exports\AgentExporter;
use App\Http\Controllers\AgencyController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UtilsController;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Agency;
use App\Models\AgencyType;
use App\Models\Agent;
use App\Models\CorporateQuote;
use App\Models\IndividualQuote;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AgentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(function (Builder $query) {
                if (Auth::user()->is_accountManagers) {
                    return Agent::query()->where('ownerAccountManagers', Auth::user()->id);
                }
                return Agent::query();
            })
            ->defaultSort('created_at', 'desc')
            ->heading('AGENTES')
            ->description('Lista de agentes registrados en el sistema')
            ->columns([
                TextColumn::make('owner_code')
                    ->label('Pertenece a:')
                    ->prefix(function ($record) {
                        $agency_type = Agency::select('agency_type_id')
                            ->where('code', $record->owner_code)
                            ->with('typeAgency')
                            ->first();

                        return isset($agency_type) ? $agency_type->typeAgency->definition . ' - ' : 'MASTER - ';
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-s-building-library')
                    ->searchable(),
                TextColumn::make('accountManager.full_name')
                    ->label('Account Manager')
                    ->icon('heroicon-o-shield-check')
                    ->badge()
                    ->color('warning'),
                TextColumn::make('id')
                    ->label('Código de agente')
                    ->prefix('AGT-000')
                    ->alignCenter()
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('typeAgent.definition')
                    ->label('Tipo de Agente')
                    ->searchable()
                    ->badge()
                    ->color('verde'),
                TextColumn::make('name')
                    ->label('Razon Social')
                    ->searchable()
                    ->badge()
                    ->color('verde'),
                TextColumn::make('ci')
                    ->label('CI:')
                    ->searchable()
                    ->badge()
                    ->color('verde'),
                TextColumn::make('address')
                    ->label('Direccion')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Número de Teléfono')
                    ->searchable(),
                TextColumn::make('user_instagram')
                    ->label('Usuario de Instagram')
                    ->searchable(),
                TextColumn::make('commission_tdec')
                    ->label('(%) TDEC')
                    ->suffix('%')
                    ->badge()
                    ->color(function ($record): string {

                        if ($record->commission_tdec > 0) {
                            return 'success';
                        }
                        return 'warning';
                    })
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('commission_tdec_renewal')
                    ->label('(%) TDEC Renovacion')
                    ->suffix('%')
                    ->badge()
                    ->color(function ($record): string {

                        if ($record->commission_tdec > 0) {
                            return 'success';
                        }
                        return 'warning';
                    })
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('commission_tdev')
                    ->label('(%) TDEV')
                    ->suffix('%')
                    ->badge()
                    ->color(function ($record): string {

                        if ($record->commission_tdec > 0) {
                            return 'success';
                        }
                        return 'warning';
                    })
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('commission_tdev_renewal')
                    ->label('(%) TDEV Renovacion')
                    ->suffix('%')
                    ->badge()
                    ->color(function ($record): string {

                        if ($record->commission_tdec > 0) {
                            return 'success';
                        }
                        return 'warning';
                    })
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (mixed $state): string {
                        return match ($state) {
                            'ACTIVO' => 'success',
                            'INACTIVO' => 'danger',
                            'POR REVISION' => 'warning',
                        };
                    })
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('created_by')
                    ->label('Creado Por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Fecha de Modificación')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('activate_monthly_frequency')
                    ->label('Frecuencia Mensual')

            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('desde'),
                        DatePicker::make('hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Venta desde ' . Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Venta hasta ' . Carbon::parse($data['hasta'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
                SelectFilter::make('type_agent')
                    ->label('Tipo agente')
                    ->relationship('typeAgent', 'definition')
                    ->attribute('agent_type_id'),
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'ACTIVO' => 'ACTIVO',
                        'INACTIVO' => 'INACTIVO',
                        'POR REVISION' => 'POR REVISION',
                    ]),
            ])
            ->filtersTriggerAction(
                fn(Action $action) => $action
                    ->button()
                    ->label('Filtros'),
            )
            ->recordActions([
                ActionGroup::make([
                    Action::make('Activate')
                        ->label('Activar')
                        ->action(function (Agent $record) {

                    try {
                        // Iniciamos transacción para asegurar la integridad de los datos
                        \Illuminate\Support\Facades\DB::beginTransaction();

                        // 1. Actualización del estado del Agente
                        $record->status = 'ACTIVO';
                        $record->save();

                        // 2. Creación del Usuario asociado
                        $user = new User();
                        $user->name = $record->name;
                        $user->email = $record->email;
                        $user->password = \Illuminate\Support\Facades\Hash::make('12345678'); // Considerar generar una clave aleatoria segura
                        $user->is_agent = true;
                        $user->code_agency = $record->code_agency;
                        $user->code_agent = 'AGT-000' . $record->id;

                        // Generación segura del link de agente
                        $encryptedCode = \Illuminate\Support\Facades\Crypt::encryptString($record->code_agent);
                        $user->link_agent = config('app.url') . '/at/lk/' . $encryptedCode;

                        $user->agent_id = $record->id;
                        $user->status = 'ACTIVO';
                        $user->save();

                        // 3. Notificación por Correo (CARTA DE BIENVENIDA)
                        if ($record->role === 'EJECUTIVO') {
                            $record->sendCartaBienvenidaEjecutivo($record->id, $record->name, $record->email);
                        } else {
                            $record->sendCartaBienvenida($record->id, $record->name, $record->email);
                        }

                        // 4. Notificación de WhatsApp/SMS vía Controller
                        $path = ($record->agent_type_id == 2)
                            ? config('parameters.PATH_AGENT')
                            : config('parameters.PATH_SUBAGENT');

                        $notificationSuccess = NotificationController::agent_activated(
                            $record->phone,
                            $record->email,
                            $path
                        );

                        // Confirmamos los cambios en la base de datos
                        \Illuminate\Support\Facades\DB::commit();

                        // 5. Notificación visual al usuario en la interfaz
                        Notification::make()
                            ->title('¡AGENTE ACTIVADO!')
                            ->body("El agente {$record->name} ha sido procesado y su cuenta de usuario creada.")
                            ->icon('heroicon-s-check-circle')
                            ->success()
                            ->send();
                    } catch (\Throwable $th) {
                        // Revertimos cualquier cambio en la BD si algo falló
                        \Illuminate\Support\Facades\DB::rollBack();

                        // Registro profesional del error para el administrador
                        \Illuminate\Support\Facades\Log::error("Fallo crítico al activar agente ID: {$record->id}", [
                            'error' => $th->getMessage(),
                            'file'  => $th->getFile(),
                            'line'  => $th->getLine()
                        ]);

                        Notification::make()
                            ->title('ERROR DE ACTIVACIÓN')
                            ->body('No se pudo completar la activación. Los cambios han sido revertidos y el error reportado.')
                            ->icon('heroicon-s-x-circle')
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                        })
                        ->icon('heroicon-s-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->hidden(fn (Agent $record): bool => $record->status == 'ACTIVO'),
                    Action::make('edit_jerarquia')
                        ->requiresConfirmation()
                        ->label('Editar Jerarquía')
                        ->icon('heroicon-s-cog')
                        ->color('warning')
                        ->modalWidth(Width::ThreeExtraLarge)
                        ->form([
                            Fieldset::make('Tipo Agencia')->schema([
                                Select::make('type_agency')
                                    ->label('Tipo de agencia')
                                    ->options(AgencyType::all()->pluck('definition', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->live()
                                    ->preload(),
                            ])->columnSpanFull(),
                            Fieldset::make('Jerarquía')->schema([
                                Select::make('owner_code')
                                    ->label('Agencia Master')
                                    ->helperText('Si la agencia pertenece a TuDrEnCasa(TDEC) debe dejar este campo en blanco. De lo contrario, debe seleccionar la agencia master.')
                                    ->options(Agency::where('agency_type_id', 1)->where('status', 'ACTIVO')->get()->pluck('name_corporative', 'code'))
                                    ->searchable()
                                    ->preload(),
                            ])->columnSpanFull()->hidden(fn (Get $get) => $get('type_agency') == 3  ? false : true),
                            Fieldset::make('Razón Social')->schema([
                                TextInput::make('name_corporative')
                                    ->label('Razon social')
                                    ->required()
                                    ->afterStateUpdatedJs(<<<'JS'
                                        $set('name_corporative', $state.toUpperCase());
                                    JS),
                            ])->columnSpanFull(),
                            Fieldset::make('Comisiones')->schema([
                                TextInput::make('commission_tdec')
                                    ->label('Comisión TDEC US$')
                                    ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                                    ->prefix('%')
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric'   => 'Campo tipo numerico.',
                                    ]),
                                TextInput::make('commission_tdec_renewal')
                                    ->label('Comisión Renovacion TDEC US$')
                                    ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                                    ->prefix('%')
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric'   => 'Campo tipo numerico.',
                                    ]),
                                TextInput::make('commission_tdev')
                                    ->label('Comisión TDEV US$')
                                    ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                                    ->prefix('%')
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric'   => 'Campo tipo numerico.',
                                    ]),
                                TextInput::make('commission_tdev_renewal')
                                    ->label('Comisión Renovacion TDEV US$')
                                    ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                                    ->prefix('%')
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric'   => 'Campo tipo numerico.',
                                    ]),
                            ])->columnSpanFull(),

                        ])
                        ->action(function (Agent $record, array $data) {
                            // dd($data, $record, env('APP_URL'));
                            try {

                                if($record->status != 'ACTIVO') {
                                    Notification::make()
                                        ->title('EXCEPCIÓN DE ACTUALIZACIÓN')
                                        ->body('El agente no se encuentra activo. Por favor debe activar el agente primero.')
                                        ->icon('heroicon-s-x-circle')
                                        ->iconColor('danger')
                                        ->color('danger')
                                        ->send();
                                    return;
                                }

                                //Si el agente asiende a agencia master
                                if ($data['type_agency'] == 1) {
                                    
                                    //1. Busco la informacion del agente en la tabla de usuario para actualizar la informacion
                                    // para que el agente acceda con el mismo usuario como agencia master
                                    $user = User::where('email', $record->email)->first();
                                    if ($user != null) {
                                        $user->name         = $data['name_corporative'] != null ? $data['name_corporative'] : $record->name;
                                        $user->agent_id     = NULL;
                                        $user->code_agency  = AgencyController::generate_code_agency();
                                        $user->agency_type  = 'MASTER';
                                        //Bloqueo la entrada al modulo del agente
                                        $user->is_agent     = false;
                                        //permito la entrada al modulo de agencia master
                                        $user->is_agency    = true;
                                        //Asigno el link de acceso para que la agencia master cree su estructura (agencias generales y agentes)
                                        $user->link_agency  = env('APP_URL') . '/m/o/c/' . Crypt::encryptString($user->code_agency);
                                        $user->status       = 'ACTIVO';
                                        $user->save();

                                        //CREO LA INFORMACON DE LA NUEVA AGENCIA EN LA TABLA DE AGENCIAS
                                        $agency = new Agency();
                                        $agency->code               = $user->code_agency;
                                        $agency->name_corporative   = $data['name_corporative'] != null ? $data['name_corporative'] : $record->name;
                                        $agency->owner_code         = $user->code_agency;
                                        $agency->agency_type_id     = $data['type_agency'];
                                        $agency->email              = $user->email;
                                        $agency->status             = 'ACTIVO';
                                        
                                        //Informacion de moneda local
                                        $agency->local_beneficiary_name                     = $record->local_beneficiary_name != null ? $record->local_beneficiary_name : NULL;
                                        $agency->local_beneficiary_rif                      = $record->local_beneficiary_rif != null ? $record->local_beneficiary_rif : NULL;
                                        $agency->local_beneficiary_account_number           = $record->local_beneficiary_account_number != null ? $record->local_beneficiary_account_number : NULL;
                                        $agency->local_beneficiary_account_bank             = $record->local_beneficiary_account_bank != null ? $record->local_beneficiary_account_bank : NULL;
                                        $agency->local_beneficiary_account_type             = $record->local_beneficiary_account_type != null ? $record->local_beneficiary_account_type : NULL;
                                        $agency->local_beneficiary_phone_pm                 = $record->local_beneficiary_phone_pm != null ? $record->local_beneficiary_phone_pm : NULL;
                                        $agency->local_beneficiary_account_number_mon_inter = $record->local_beneficiary_account_number_mon_inter != null ? $record->local_beneficiary_account_number_mon_inter : NULL;
                                        $agency->local_beneficiary_account_bank_mon_inter   = $record->local_beneficiary_account_bank_mon_inter != null ? $record->local_beneficiary_account_bank_mon_inter : NULL;
                                        $agency->local_beneficiary_account_type_mon_inter   = $record->local_beneficiary_account_type_mon_inter != null ? $record->local_beneficiary_account_type_mon_inter : NULL;
                                        
                                        //Informacion de moneda internacional
                                        $agency->extra_beneficiary_name                     = $record->extra_beneficiary_name != null ? $record->extra_beneficiary_name : NULL;
                                        $agency->extra_beneficiary_ci_rif                   = $record->extra_beneficiary_ci_rif != null ? $record->extra_beneficiary_ci_rif : NULL;
                                        $agency->extra_beneficiary_account_number           = $record->extra_beneficiary_account_number != null ? $record->extra_beneficiary_account_number : NULL;
                                        $agency->extra_beneficiary_account_bank             = $record->extra_beneficiary_account_bank != null ? $record->extra_beneficiary_account_bank : NULL;
                                        $agency->extra_beneficiary_account_type             = $record->extra_beneficiary_account_type != null ? $record->extra_beneficiary_account_type : NULL;
                                        $agency->extra_beneficiary_route                    = $record->extra_beneficiary_route != null ? $record->extra_beneficiary_route : NULL;
                                        $agency->extra_beneficiary_zelle                    = $record->extra_beneficiary_zelle != null ? $record->extra_beneficiary_zelle : NULL;
                                        $agency->extra_beneficiary_ach                      = $record->extra_beneficiary_ach != null ? $record->extra_beneficiary_ach : NULL;
                                        $agency->extra_beneficiary_swift                    = $record->extra_beneficiary_swift != null ? $record->extra_beneficiary_swift : NULL;
                                        $agency->extra_beneficiary_aba                      = $record->extra_beneficiary_aba != null ? $record->extra_beneficiary_aba : NULL;
                                        $agency->extra_beneficiary_address                  = $record->extra_beneficiary_address != null ? $record->extra_beneficiary_address : NULL;
                                        $agency->save();

                                        //2. Busco en la tabla de COTIZACIONES INDIVIDUALES los registros asociados al agentes y actualizo la informacion
                                        // para migrar la informacion del agente a la agencia master
                                        $individualQuote = IndividualQuote::where('agent_id', $record->id)->get();
                                        foreach ($individualQuote as $quote) {
                                            $quote->agent_id    = NULL;
                                            //Si la gencia es master el code_agency == owner_code
                                            $quote->owner_code  = $user->code_agency;
                                            $quote->code_agency = $user->code_agency;
                                            $quote->save();
                                        }

                                        //3. Busco en la tabla de COTIZACIONES CORPOTAIVAS los registros asociados al agentes y actualizo la informacion
                                        // para migrar la informacion del agente a la agencia master
                                        $corporateQuote = CorporateQuote::where('agent_id', $record->id)->get();
                                        foreach ($corporateQuote as $corpquote) {
                                            $corpquote->agent_id    = NULL;
                                            //Si la gencia es master el code_agency == owner_code
                                            $corpquote->owner_code  = $user->code_agency;
                                            $corpquote->code_agency = $user->code_agency;
                                            $corpquote->save();
                                        }

                                        //4. Busco en la tabla de AFILIACIONES INDIVIDUALES los registros asociados al agentes y actualizo la informacion
                                        // para migrar la informacion del agente a la agencia master
                                        $afiliacionIndividual = Affiliation::where('agent_id', $record->id)->get();
                                        foreach ($afiliacionIndividual as $afiInvidual) {
                                            $afiInvidual->agent_id    = NULL;
                                            //Si la gencia es master el code_agency == owner_code
                                            $afiInvidual->owner_code  = $user->code_agency;
                                            $afiInvidual->code_agency = $user->code_agency;
                                            $afiInvidual->save();
                                        }

                                        //5. Busco en la tabla de AFILIACIONES CORPOTAIVAS los registros asociados al agentes y actualizo la informacion
                                        // para migrar la informacion del agente a la agencia master
                                        $afiliacionCorporativa = AffiliationCorporate::where('agent_id', $record->id)->get();
                                        foreach ($afiliacionCorporativa as $corp) {
                                            $corp->agent_id    = NULL;
                                            //Si la gencia es master el code_agency == owner_code
                                            $corp->owner_code  = $user->code_agency;
                                            $corp->code_agency = $user->code_agency;
                                            $corp->save();
                                        }
                                        
                                    }else{
                                        Notification::make()
                                            ->title('EXCEPCION')
                                            ->body('El correo del agente es diferente al correo del usuario. Por favor comuníquese con el administrador.')
                                            ->icon('heroicon-s-x-circle')
                                            ->iconColor('error')
                                            ->color('error')
                                            ->send();
                                        
                                        return;
                                    }
                                }

                                //Si el agente asiende a agencia master
                                if ($data['type_agency'] == 3) {

                                    //1. Busco la informacion del agente en la tabla de usuario para actualizar la informacion
                                    // para que el agente acceda con el mismo usuario como agencia master
                                    $user = User::where('email', $record->email)->first();
                                    if ($user != null) {
                                        $user->name         = $data['name_corporative'] != null ? $data['name_corporative'] : $record->name;
                                        $user->agent_id     = NULL;
                                        $user->code_agency  = AgencyController::generate_code_agency();
                                        $user->agency_type  = 'GENERAL';
                                        //Bloqueo la entrada al modulo del agente
                                        $user->is_agent     = false;
                                        //permito la entrada al modulo de agencia master
                                        $user->is_agency    = true;
                                        //Asigno el link de acceso para que la agencia master cree su estructura (agencias generales y agentes)
                                        $user->link_agency  = env('APP_URL') . '/agent/c/' . Crypt::encryptString($user->code_agency);
                                        $user->status       = 'ACTIVO';
                                        $user->save();

                                        //CREO LA INFORMACON DE LA NUEVA AGENCIA EN LA TABLA DE AGENCIAS
                                        $agency = new Agency();
                                        $agency->code               = $user->code_agency;
                                        $agency->name_corporative   = $data['name_corporative'] != null ? $data['name_corporative'] : $record->name;
                                        $agency->owner_code         = $data['owner_code'];
                                        $agency->agency_type_id     = $data['type_agency'];
                                        $agency->email              = $user->email;
                                        $agency->status             = 'ACTIVO';

                                        //Informacion de moneda local
                                        $agency->local_beneficiary_name                     = $record->local_beneficiary_name != null ? $record->local_beneficiary_name : NULL;
                                        $agency->local_beneficiary_rif                      = $record->local_beneficiary_rif != null ? $record->local_beneficiary_rif : NULL;
                                        $agency->local_beneficiary_account_number           = $record->local_beneficiary_account_number != null ? $record->local_beneficiary_account_number : NULL;
                                        $agency->local_beneficiary_account_bank             = $record->local_beneficiary_account_bank != null ? $record->local_beneficiary_account_bank : NULL;
                                        $agency->local_beneficiary_account_type             = $record->local_beneficiary_account_type != null ? $record->local_beneficiary_account_type : NULL;
                                        $agency->local_beneficiary_phone_pm                 = $record->local_beneficiary_phone_pm != null ? $record->local_beneficiary_phone_pm : NULL;
                                        $agency->local_beneficiary_account_number_mon_inter = $record->local_beneficiary_account_number_mon_inter != null ? $record->local_beneficiary_account_number_mon_inter : NULL;
                                        $agency->local_beneficiary_account_bank_mon_inter   = $record->local_beneficiary_account_bank_mon_inter != null ? $record->local_beneficiary_account_bank_mon_inter : NULL;
                                        $agency->local_beneficiary_account_type_mon_inter   = $record->local_beneficiary_account_type_mon_inter != null ? $record->local_beneficiary_account_type_mon_inter : NULL;

                                        //Informacion de moneda internacional
                                        $agency->extra_beneficiary_name                     = $record->extra_beneficiary_name != null ? $record->extra_beneficiary_name : NULL;
                                        $agency->extra_beneficiary_ci_rif                   = $record->extra_beneficiary_ci_rif != null ? $record->extra_beneficiary_ci_rif : NULL;
                                        $agency->extra_beneficiary_account_number           = $record->extra_beneficiary_account_number != null ? $record->extra_beneficiary_account_number : NULL;
                                        $agency->extra_beneficiary_account_bank             = $record->extra_beneficiary_account_bank != null ? $record->extra_beneficiary_account_bank : NULL;
                                        $agency->extra_beneficiary_account_type             = $record->extra_beneficiary_account_type != null ? $record->extra_beneficiary_account_type : NULL;
                                        $agency->extra_beneficiary_route                    = $record->extra_beneficiary_route != null ? $record->extra_beneficiary_route : NULL;
                                        $agency->extra_beneficiary_zelle                    = $record->extra_beneficiary_zelle != null ? $record->extra_beneficiary_zelle : NULL;
                                        $agency->extra_beneficiary_ach                      = $record->extra_beneficiary_ach != null ? $record->extra_beneficiary_ach : NULL;
                                        $agency->extra_beneficiary_swift                     = $record->extra_beneficiary_swift != null ? $record->extra_beneficiary_swift : NULL;
                                        $agency->extra_beneficiary_aba                      = $record->extra_beneficiary_aba != null ? $record->extra_beneficiary_aba : NULL;
                                        $agency->extra_beneficiary_address                  = $record->extra_beneficiary_address != null ? $record->extra_beneficiary_address : NULL;
                                        $agency->save();

                                        //2. Busco en la tabla de COTIZACIONES INDIVIDUALES los registros asociados al agentes y actualizo la informacion
                                        // para migrar la informacion del agente a la agencia master
                                        $individualQuote = IndividualQuote::where('agent_id', $record->id)->get();
                                        foreach ($individualQuote as $quote) {
                                            $quote->agent_id    = NULL;
                                            //Si la gencia es master el code_agency == owner_code
                                            $quote->owner_code  = $data['owner_code'];
                                            $quote->code_agency = $user->code_agency;
                                            $quote->save();
                                        }

                                        //3. Busco en la tabla de COTIZACIONES CORPOTAIVAS los registros asociados al agentes y actualizo la informacion
                                        // para migrar la informacion del agente a la agencia master
                                        $corporateQuote = CorporateQuote::where('agent_id', $record->id)->get();
                                        foreach ($corporateQuote as $corpquote) {
                                            $corpquote->agent_id    = NULL;
                                            //Si la gencia es master el code_agency == owner_code
                                            $corpquote->owner_code  = $data['owner_code'];
                                            $corpquote->code_agency = $user->code_agency;
                                            $corpquote->save();
                                        }

                                        //4. Busco en la tabla de AFILIACIONES INDIVIDUALES los registros asociados al agentes y actualizo la informacion
                                        // para migrar la informacion del agente a la agencia master
                                        $afiliacionIndividual = Affiliation::where('agent_id', $record->id)->get();
                                        foreach ($afiliacionIndividual as $afiInvidual) {
                                            $afiInvidual->agent_id    = NULL;
                                            //Si la gencia es master el code_agency == owner_code
                                            $afiInvidual->owner_code  = $data['owner_code'];
                                            $afiInvidual->code_agency = $user->code_agency;
                                            $afiInvidual->save();
                                        }

                                        //5. Busco en la tabla de AFILIACIONES CORPOTAIVAS los registros asociados al agentes y actualizo la informacion
                                        // para migrar la informacion del agente a la agencia master
                                        $afiliacionCorporativa = AffiliationCorporate::where('agent_id', $record->id)->get();
                                        foreach ($afiliacionCorporativa as $corp) {
                                            $corp->agent_id    = NULL;
                                            //Si la gencia es master el code_agency == owner_code
                                            $corp->owner_code  = $data['owner_code'];
                                            $corp->code_agency = $user->code_agency;
                                            $corp->save();
                                        }
                                    } else {
                                        Notification::make()
                                            ->title('EXCEPCION')
                                            ->body('El correo del agente es diferente al correo del usuario. Por favor comuníquese con el administrador.')
                                            ->icon('heroicon-s-x-circle')
                                            ->iconColor('error')
                                            ->color('error')
                                            ->send();

                                        return;
                                    }
                                }

                                //elimino al agente
                                $record->delete();

                                Notification::make()
                                    ->title('ASCENSO EXITOSO')
                                    ->body('El agente acaba de ser ascendido al rol de agencias de forma exitosa.')
                                    ->icon('heroicon-s-check-circle')
                                    ->iconColor('success')
                                    ->color('success')
                                    ->send();

                                
                            } catch (\Throwable $th) {
                                dd($th);
                                Notification::make()
                                    ->title('EXCEPCION')
                                    ->body('Falla al realizar la activacion. Por favor comuniquese con el administrador.')
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('error')
                                    ->color('error')
                                    ->send();
                            }
                        })
                        ->hidden(fn() => !in_array('SUPERADMIN', auth()->user()->departament)),
                    Action::make('Inactivate')
                        ->label('Inactivar')
                        ->requiresConfirmation()
                        ->action(fn(Agent $record) => $record->update(['status' => 'INACTIVO']))
                        ->icon('heroicon-s-x-circle')
                        ->color('danger')
                        ->hidden(fn() => !in_array('SUPERADMIN', auth()->user()->departament)),
                    DeleteAction::make()
                        ->color('danger')
                        ->label('Eliminar')
                        ->hidden(fn() => !in_array('SUPERADMIN', auth()->user()->departament)),
                ])->icon('heroicon-c-ellipsis-vertical')->color('azulOscuro')
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assignAccountManager')
                        ->label('Asignar Coordinador')
                        ->icon('heroicon-s-user')
                        ->color('success')
                        ->modalWidth(Width::ExtraLarge)
                        ->form([
                            Fieldset::make('Asignación masiva de coordinadores')
                                ->schema([
                                    Select::make('ownerAccountManagers')
                                        ->options(User::where('is_accountManagers', true)->where('status', 'ACTIVO')->pluck('name', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                ])->columnSpanFull()->columns(1),
                        ])
                        ->action(function (Collection $records, array $data) {

                            $records = $records->toArray();

                            try {

                                for ($i = 0; $i < count($records); $i++) {

                                    //Agencias Tipo Master
                                    if ($records[$i]['agent_type_id'] == 2) {

                                        if($records[$i]['status'] == 'INACTIVO' || $records[$i]['status'] == 'POR REVISION'){
                                            Throw new \Exception('No se puede asignar un coordinador a un agente "INACTIVO" o en estatus "POR REVISION"');
                                        }

                                        //actualizo la ionformacion del agente y le asigno al administrador de negocios
                                        Agent::where('status', 'ACTIVO')->where('id', $records[$i]['id'])->first()
                                        ->update([
                                            'ownerAccountManagers' => $data['ownerAccountManagers']
                                        ]);

                                        //Busco si el agente tiene subagente asignados a el
                                        //varificamos las agencias generales y los agentes asociados a ella
                                        $subAgents = Agent::where('status', 'ACTIVO')
                                            ->where('agent_type_id', 3)
                                            ->where('owner_agent', $records[$i]['id'])
                                            ->get();

                                        //Si la agencia master tiene agencias generales activas
                                        if (count($subAgents) > 0) {

                                            for ($j = 0; $j < count($subAgents); $j++) {
                                                //actualizo el valor del coordinador
                                                $subAgents[$j]->ownerAccountManagers = $data['ownerAccountManagers'];
                                                $subAgents[$j]->save();
                                            }
                                        }

                                    }

                                    //Agencias Tipo General
                                    if ($records[$i]['agent_type_id'] == 3) {

                                        if ($records[$i]['status'] == 'INACTIVO' || $records[$i]['status'] == 'POR REVISION') {
                                            throw new \Exception('No se puede asignar un coordinador a un agente "INACTIVO" o en estatus "POR REVISION"');
                                        }

                                        //Busco los agentes que pertenecen a la agencia master
                                        $agentes = Agent::where('status', 'ACTIVO')->where('owner_code', $records[0]['owner_code'])->get();

                                        //Si la agencia master tiene agentes activos
                                        if (count($agentes) > 0) {

                                            for ($k = 0; $k < count($agentes); $k++) {
                                                //actualizo el valor del coordinador
                                                $agentes[$k]->update([
                                                    'ownerAccountManagers' => $data['ownerAccountManagers']
                                                ]);
                                            }
                                        }
                                    }
                                }
                            } catch (\Throwable $th) {
                                Notification::make()
                                    ->title('EXCEPCION')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->color('danger')
                                    ->send();
                            }
                        })
                        ->requiresConfirmation()
                        ->hidden(fn() => !in_array('SUPERADMIN', auth()->user()->departament)),
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Agentes del Sistema')
                        ->modalDescription('¿Estas seguro de eliminar los agentes seleccionados?, esta accion no se puede reversar')
                        ->modalSubmitActionLabel('Eliminar')
                        ->label('Eliminar Agentes')
                        ->icon('heroicon-s-trash')
                        ->color('danger')
                        ->action(function (Collection $records) {
                            try {

                                if (empty($records)) {
                                    return;
                                }

                                foreach ($records as $record) {
                                    // Eliminamos el agente
                                    $agent = Agent::find($record->id);
                                    if ($agent) {
                                        $agent->delete();
                                    }

                                    // Eliminamos el usuario asociado
                                    if (!empty($record->id)) {
                                        $user = User::where('agent_id', $record->id)->first();
                                        if ($user) {
                                            $user->delete();
                                        }
                                    }
                                }

                                Notification::make()
                                    ->title('Proceso completado')
                                    ->body('Los agentes y sus cuentas de usuario han sido eliminados correctamente.')
                                    ->icon('heroicon-s-check-circle')
                                    ->success()
                                    ->send();

                            } catch (\Throwable $th) {
                                Log::error("Error en eliminación masiva de agentes", [
                                    'message' => $th->getMessage(),
                                    'trace' => $th->getTraceAsString()
                                ]);

                                Notification::make()
                                    ->title('Error de sistema')
                                    ->body('Ocurrió un problema al intentar eliminar los registros. El equipo técnico ha sido notificado.')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->hidden(fn() => !in_array('SUPERADMIN', auth()->user()->departament)),
                    ExportBulkAction::make()->exporter(AgentExporter::class)->label('Exportar XLS')->color('warning')->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->striped();
    }
}