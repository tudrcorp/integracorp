<?php

namespace App\Filament\Business\Resources\Agents\Tables;

use App\Filament\Business\Resources\Helpdesks\Actions\HelpdeskTicketModalActions;
use App\Http\Controllers\AgencyController;
use App\Http\Controllers\AgentExportCsvController;
use App\Http\Controllers\NotificationController;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Agency;
use App\Models\AgencyType;
use App\Models\Agent;
use App\Models\AgentNoteBlog;
use App\Models\CorporateQuote;
use App\Models\IndividualQuote;
use App\Models\User;
use App\Support\AgentActivity\AgentActivityQuery;
use App\Support\HelpdeskObservationHtmlRenderer;
use App\Support\SecurityAudit;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AgentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(function (Builder $query) {
                if (Auth::user()->is_accountManagers) {
                    // dd(Auth::user()->id);
                    return AgentActivityQuery::applyToAgentsQuery(
                        Agent::query()->where('ownerAccountManagers', Auth::user()->id)
                    );
                }

                return AgentActivityQuery::applyToAgentsQuery(Agent::query());
            })
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->heading('Agentes')
            ->description('Listado de agentes registrados en el sistema. Todas las columnas están visibles por defecto; puedes reorganizarlas desde el selector de columnas.')
            ->columns([
                TextColumn::make('last_interaction_at')
                    ->label('Días de inactividad')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (Agent $record): string => self::trafficLightColor($record))
                    ->state(function (Agent $record): string {
                        $days = self::daysSinceLastInteraction($record);

                        return $days === null ? '—' : (string) $days.' dias';
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('last_interaction_at', $direction);
                    }),
                TextColumn::make('technical_status')
                    ->label('Estatus técnico')
                    ->alignCenter()
                    ->badge()
                    ->icon(fn (Agent $record): string => self::trafficLightIcon($record))
                    ->color(fn (Agent $record): string => self::trafficLightColor($record))
                    ->state(fn (Agent $record): string => self::trafficLightLabel($record)),
                TextColumn::make('technical_action')
                    ->label('Acción')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (Agent $record): string => self::trafficLightLabel($record) === 'Inactivo' ? 'danger' : 'gray')
                    ->state(fn (Agent $record): string => self::trafficLightLabel($record) === 'Inactivo' ? 'ALERTA GERENCIA' : 'No requiere acción'),
                TextColumn::make('owner_code')
                    ->label('Pertenece a')
                    ->prefix(function ($record) {
                        $agency_type = Agency::select('agency_type_id')
                            ->where('code', $record->owner_code)
                            ->with('typeAgency')
                            ->first();

                        return isset($agency_type) ? $agency_type->typeAgency->definition.' - ' : 'MASTER - ';
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-s-building-library')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('accountManager.full_name')
                    ->label('Account Manager')
                    ->icon('heroicon-o-shield-check')
                    ->badge()
                    ->color('warning')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('id')
                    ->label('Código de agente')
                    ->prefix('AGT-000')
                    ->alignCenter()
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable()
                    ->sortable()
                    ->action(self::makeAgentCommandCenterAction()),
                TextColumn::make('typeAgent.definition')
                    ->label('Tipo de agente')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('verde')
                    ->placeholder('—'),
                TextColumn::make('name')
                    ->label('Razón social')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('verde')
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('ci')
                    ->label('CI')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('verde')
                    ->placeholder('—'),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-envelope')
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('user_instagram')
                    ->label('Instagram')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('commission_tdec')
                    ->label('(%) TDEC')
                    ->suffix('%')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (Agent $record): string => ($record->commission_tdec ?? 0) > 0 ? 'success' : 'warning')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('commission_tdec_renewal')
                    ->label('(%) TDEC renovación')
                    ->suffix('%')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (Agent $record): string => ($record->commission_tdec_renewal ?? 0) > 0 ? 'success' : 'warning')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('commission_tdev')
                    ->label('(%) TDEV')
                    ->suffix('%')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (Agent $record): string => ($record->commission_tdev ?? 0) > 0 ? 'success' : 'warning')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('commission_tdev_renewal')
                    ->label('(%) TDEV renovación')
                    ->suffix('%')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (Agent $record): string => ($record->commission_tdev_renewal ?? 0) > 0 ? 'success' : 'warning')
                    ->numeric(2)
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
                            default => 'gray',
                        };
                    })
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('created_at')
                    ->label('Fecha de creación')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (Agent $record) => $record->created_at->diffForHumans())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('updated_at')
                    ->label('Última modificación')
                    ->description(fn (Agent $record) => $record->updated_at->diffForHumans())
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                ToggleColumn::make('activate_monthly_frequency')
                    ->label('Frecuencia mensual')
                    ->alignCenter(),

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
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Venta desde '.Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Venta hasta '.Carbon::parse($data['hasta'])->toFormattedDateString();
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
                fn (Action $action) => $action
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
                                $user = new User;
                                $user->name = $record->name;
                                $user->email = $record->email;
                                $user->password = \Illuminate\Support\Facades\Hash::make('12345678'); // Considerar generar una clave aleatoria segura
                                $user->is_agent = true;
                                $user->code_agency = $record->code_agency;
                                $user->code_agent = 'AGT-000'.$record->id;

                                // Generación segura del link de agente
                                $encryptedCode = \Illuminate\Support\Facades\Crypt::encryptString($record->code_agent);
                                $user->link_agent = config('app.url').'/at/lk/'.$encryptedCode;

                                $user->agent_id = $record->id;
                                $user->status = 'ACTIVO';
                                $user->save();

                                // 3. Notificación por Correo (CARTA DE BIENVENIDA)
                                if ($record->role === 'EJECUTIVO') {
                                    $record->sendCartaBienvenidaEjecutivo($record->id, $record->name, $record->email);
                                } else {
                                    $record->sendCartaBienvenida($record->id, $record->name, $record->email);
                                }

                                SecurityAudit::log('AUDIT_BUSINESS_AGENT_WELCOME_EMAIL_SENT', 'business.agents.activate.send-welcome-email', [
                                    'agent_id' => $record->id,
                                    'agent_name' => $record->name,
                                    'agent_email' => $record->email,
                                    'agent_role' => $record->role,
                                ]);

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

                                SecurityAudit::log('AUDIT_BUSINESS_AGENT_ACTIVATED', 'business.agents.activate', [
                                    'agent_id' => $record->id,
                                    'agent_name' => $record->name,
                                    'agent_email' => $record->email,
                                    'created_user_id' => $user->id,
                                    'notification_success' => (bool) $notificationSuccess,
                                ]);

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

                                SecurityAudit::log('AUDIT_BUSINESS_AGENT_ACTIVATE_FAILED', 'business.agents.activate', [
                                    'agent_id' => $record->id,
                                    'agent_name' => $record->name,
                                    'agent_email' => $record->email,
                                    'error' => $th->getMessage(),
                                ]);

                                SecurityAudit::log('AUDIT_BUSINESS_AGENT_WELCOME_EMAIL_FAILED', 'business.agents.activate.send-welcome-email', [
                                    'agent_id' => $record->id,
                                    'agent_name' => $record->name,
                                    'agent_email' => $record->email,
                                    'error' => $th->getMessage(),
                                ]);

                                // Registro profesional del error para el administrador
                                \Illuminate\Support\Facades\Log::error("Fallo crítico al activar agente ID: {$record->id}", [
                                    'error' => $th->getMessage(),
                                    'file' => $th->getFile(),
                                    'line' => $th->getLine(),
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
                            ])->columnSpanFull()->hidden(fn (Get $get) => $get('type_agency') == 3 ? false : true),
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
                                        'numeric' => 'Campo tipo numerico.',
                                    ]),
                                TextInput::make('commission_tdec_renewal')
                                    ->label('Comisión Renovacion TDEC US$')
                                    ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                                    ->prefix('%')
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => 'Campo tipo numerico.',
                                    ]),
                                TextInput::make('commission_tdev')
                                    ->label('Comisión TDEV US$')
                                    ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                                    ->prefix('%')
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => 'Campo tipo numerico.',
                                    ]),
                                TextInput::make('commission_tdev_renewal')
                                    ->label('Comisión Renovacion TDEV US$')
                                    ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                                    ->prefix('%')
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => 'Campo tipo numerico.',
                                    ]),
                            ])->columnSpanFull(),

                        ])
                        ->action(function (Agent $record, array $data) {
                            // dd($data, $record, env('APP_URL'));
                            try {

                                if ($record->status != 'ACTIVO') {
                                    SecurityAudit::log('AUDIT_BUSINESS_AGENT_HIERARCHY_UPDATE_FAILED', 'business.agents.edit-hierarchy', [
                                        'agent_id' => $record->id,
                                        'agent_name' => $record->name,
                                        'reason' => 'agent_not_active',
                                        'requested_type_agency' => $data['type_agency'] ?? null,
                                    ]);

                                    Notification::make()
                                        ->title('EXCEPCIÓN DE ACTUALIZACIÓN')
                                        ->body('El agente no se encuentra activo. Por favor debe activar el agente primero.')
                                        ->icon('heroicon-s-x-circle')
                                        ->iconColor('danger')
                                        ->color('danger')
                                        ->send();

                                    return;
                                }

                                // Si el agente asiende a agencia master
                                if ($data['type_agency'] == 1) {

                                    // 1. Busco la informacion del agente en la tabla de usuario para actualizar la informacion
                                    // para que el agente acceda con el mismo usuario como agencia master
                                    $user = User::where('email', $record->email)->first();
                                    if ($user != null) {
                                        $user->name = $data['name_corporative'] != null ? $data['name_corporative'] : $record->name;
                                        $user->agent_id = null;
                                        $user->code_agency = AgencyController::generate_code_agency();
                                        $user->agency_type = 'MASTER';
                                        // Bloqueo la entrada al modulo del agente
                                        $user->is_agent = false;
                                        // permito la entrada al modulo de agencia master
                                        $user->is_agency = true;
                                        // Asigno el link de acceso para que la agencia master cree su estructura (agencias generales y agentes)
                                        $user->link_agency = env('APP_URL').'/m/o/c/'.Crypt::encryptString($user->code_agency);
                                        $user->status = 'ACTIVO';
                                        $user->save();

                                        // CREO LA INFORMACON DE LA NUEVA AGENCIA EN LA TABLA DE AGENCIAS
                                        $agency = new Agency;
                                        $agency->code = $user->code_agency;
                                        $agency->name_corporative = $data['name_corporative'] != null ? $data['name_corporative'] : $record->name;
                                        $agency->owner_code = $user->code_agency;
                                        $agency->agency_type_id = $data['type_agency'];
                                        $agency->email = $user->email;
                                        $agency->status = 'ACTIVO';

                                        // Informacion de moneda local
                                        $agency->local_beneficiary_name = $record->local_beneficiary_name != null ? $record->local_beneficiary_name : null;
                                        $agency->local_beneficiary_rif = $record->local_beneficiary_rif != null ? $record->local_beneficiary_rif : null;
                                        $agency->local_beneficiary_account_number = $record->local_beneficiary_account_number != null ? $record->local_beneficiary_account_number : null;
                                        $agency->local_beneficiary_account_bank = $record->local_beneficiary_account_bank != null ? $record->local_beneficiary_account_bank : null;
                                        $agency->local_beneficiary_account_type = $record->local_beneficiary_account_type != null ? $record->local_beneficiary_account_type : null;
                                        $agency->local_beneficiary_phone_pm = $record->local_beneficiary_phone_pm != null ? $record->local_beneficiary_phone_pm : null;
                                        $agency->local_beneficiary_account_number_mon_inter = $record->local_beneficiary_account_number_mon_inter != null ? $record->local_beneficiary_account_number_mon_inter : null;
                                        $agency->local_beneficiary_account_bank_mon_inter = $record->local_beneficiary_account_bank_mon_inter != null ? $record->local_beneficiary_account_bank_mon_inter : null;
                                        $agency->local_beneficiary_account_type_mon_inter = $record->local_beneficiary_account_type_mon_inter != null ? $record->local_beneficiary_account_type_mon_inter : null;

                                        // Informacion de moneda internacional
                                        $agency->extra_beneficiary_name = $record->extra_beneficiary_name != null ? $record->extra_beneficiary_name : null;
                                        $agency->extra_beneficiary_ci_rif = $record->extra_beneficiary_ci_rif != null ? $record->extra_beneficiary_ci_rif : null;
                                        $agency->extra_beneficiary_account_number = $record->extra_beneficiary_account_number != null ? $record->extra_beneficiary_account_number : null;
                                        $agency->extra_beneficiary_account_bank = $record->extra_beneficiary_account_bank != null ? $record->extra_beneficiary_account_bank : null;
                                        $agency->extra_beneficiary_account_type = $record->extra_beneficiary_account_type != null ? $record->extra_beneficiary_account_type : null;
                                        $agency->extra_beneficiary_route = $record->extra_beneficiary_route != null ? $record->extra_beneficiary_route : null;
                                        $agency->extra_beneficiary_zelle = $record->extra_beneficiary_zelle != null ? $record->extra_beneficiary_zelle : null;
                                        $agency->extra_beneficiary_ach = $record->extra_beneficiary_ach != null ? $record->extra_beneficiary_ach : null;
                                        $agency->extra_beneficiary_swift = $record->extra_beneficiary_swift != null ? $record->extra_beneficiary_swift : null;
                                        $agency->extra_beneficiary_aba = $record->extra_beneficiary_aba != null ? $record->extra_beneficiary_aba : null;
                                        $agency->extra_beneficiary_address = $record->extra_beneficiary_address != null ? $record->extra_beneficiary_address : null;
                                        $agency->save();

                                        // 2. Busco en la tabla de COTIZACIONES INDIVIDUALES los registros asociados al agentes y actualizo la informacion
                                        // para migrar la informacion del agente a la agencia master
                                        $individualQuote = IndividualQuote::where('agent_id', $record->id)->get();
                                        foreach ($individualQuote as $quote) {
                                            $quote->agent_id = null;
                                            // Si la gencia es master el code_agency == owner_code
                                            $quote->owner_code = $user->code_agency;
                                            $quote->code_agency = $user->code_agency;
                                            $quote->save();
                                        }

                                        // 3. Busco en la tabla de COTIZACIONES CORPOTAIVAS los registros asociados al agentes y actualizo la informacion
                                        // para migrar la informacion del agente a la agencia master
                                        $corporateQuote = CorporateQuote::where('agent_id', $record->id)->get();
                                        foreach ($corporateQuote as $corpquote) {
                                            $corpquote->agent_id = null;
                                            // Si la gencia es master el code_agency == owner_code
                                            $corpquote->owner_code = $user->code_agency;
                                            $corpquote->code_agency = $user->code_agency;
                                            $corpquote->save();
                                        }

                                        // 4. Busco en la tabla de AFILIACIONES INDIVIDUALES los registros asociados al agentes y actualizo la informacion
                                        // para migrar la informacion del agente a la agencia master
                                        $afiliacionIndividual = Affiliation::where('agent_id', $record->id)->get();
                                        foreach ($afiliacionIndividual as $afiInvidual) {
                                            $afiInvidual->agent_id = null;
                                            // Si la gencia es master el code_agency == owner_code
                                            $afiInvidual->owner_code = $user->code_agency;
                                            $afiInvidual->code_agency = $user->code_agency;
                                            $afiInvidual->save();
                                        }

                                        // 5. Busco en la tabla de AFILIACIONES CORPOTAIVAS los registros asociados al agentes y actualizo la informacion
                                        // para migrar la informacion del agente a la agencia master
                                        $afiliacionCorporativa = AffiliationCorporate::where('agent_id', $record->id)->get();
                                        foreach ($afiliacionCorporativa as $corp) {
                                            $corp->agent_id = null;
                                            // Si la gencia es master el code_agency == owner_code
                                            $corp->owner_code = $user->code_agency;
                                            $corp->code_agency = $user->code_agency;
                                            $corp->save();
                                        }

                                        SecurityAudit::log('AUDIT_BUSINESS_AGENT_HIERARCHY_UPDATED', 'business.agents.edit-hierarchy', [
                                            'agent_id' => $record->id,
                                            'agent_name' => $record->name,
                                            'target_agency_type' => $data['type_agency'] ?? null,
                                            'owner_code' => $user->code_agency,
                                            'new_user_id' => $user->id,
                                        ]);

                                    } else {
                                        SecurityAudit::log('AUDIT_BUSINESS_AGENT_HIERARCHY_UPDATE_FAILED', 'business.agents.edit-hierarchy', [
                                            'agent_id' => $record->id,
                                            'agent_name' => $record->name,
                                            'reason' => 'user_not_found_by_email',
                                            'agent_email' => $record->email,
                                        ]);

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

                                // Si el agente asiende a agencia master
                                if ($data['type_agency'] == 3) {

                                    // 1. Busco la informacion del agente en la tabla de usuario para actualizar la informacion
                                    // para que el agente acceda con el mismo usuario como agencia master
                                    $user = User::where('email', $record->email)->first();
                                    if ($user != null) {
                                        $user->name = $data['name_corporative'] != null ? $data['name_corporative'] : $record->name;
                                        $user->agent_id = null;
                                        $user->code_agency = AgencyController::generate_code_agency();
                                        $user->agency_type = 'GENERAL';
                                        // Bloqueo la entrada al modulo del agente
                                        $user->is_agent = false;
                                        // permito la entrada al modulo de agencia master
                                        $user->is_agency = true;
                                        // Asigno el link de acceso para que la agencia master cree su estructura (agencias generales y agentes)
                                        $user->link_agency = env('APP_URL').'/agent/c/'.Crypt::encryptString($user->code_agency);
                                        $user->status = 'ACTIVO';
                                        $user->save();

                                        // CREO LA INFORMACON DE LA NUEVA AGENCIA EN LA TABLA DE AGENCIAS
                                        $agency = new Agency;
                                        $agency->code = $user->code_agency;
                                        $agency->name_corporative = $data['name_corporative'] != null ? $data['name_corporative'] : $record->name;
                                        $agency->owner_code = $data['owner_code'];
                                        $agency->agency_type_id = $data['type_agency'];
                                        $agency->email = $user->email;
                                        $agency->status = 'ACTIVO';

                                        // Informacion de moneda local
                                        $agency->local_beneficiary_name = $record->local_beneficiary_name != null ? $record->local_beneficiary_name : null;
                                        $agency->local_beneficiary_rif = $record->local_beneficiary_rif != null ? $record->local_beneficiary_rif : null;
                                        $agency->local_beneficiary_account_number = $record->local_beneficiary_account_number != null ? $record->local_beneficiary_account_number : null;
                                        $agency->local_beneficiary_account_bank = $record->local_beneficiary_account_bank != null ? $record->local_beneficiary_account_bank : null;
                                        $agency->local_beneficiary_account_type = $record->local_beneficiary_account_type != null ? $record->local_beneficiary_account_type : null;
                                        $agency->local_beneficiary_phone_pm = $record->local_beneficiary_phone_pm != null ? $record->local_beneficiary_phone_pm : null;
                                        $agency->local_beneficiary_account_number_mon_inter = $record->local_beneficiary_account_number_mon_inter != null ? $record->local_beneficiary_account_number_mon_inter : null;
                                        $agency->local_beneficiary_account_bank_mon_inter = $record->local_beneficiary_account_bank_mon_inter != null ? $record->local_beneficiary_account_bank_mon_inter : null;
                                        $agency->local_beneficiary_account_type_mon_inter = $record->local_beneficiary_account_type_mon_inter != null ? $record->local_beneficiary_account_type_mon_inter : null;

                                        // Informacion de moneda internacional
                                        $agency->extra_beneficiary_name = $record->extra_beneficiary_name != null ? $record->extra_beneficiary_name : null;
                                        $agency->extra_beneficiary_ci_rif = $record->extra_beneficiary_ci_rif != null ? $record->extra_beneficiary_ci_rif : null;
                                        $agency->extra_beneficiary_account_number = $record->extra_beneficiary_account_number != null ? $record->extra_beneficiary_account_number : null;
                                        $agency->extra_beneficiary_account_bank = $record->extra_beneficiary_account_bank != null ? $record->extra_beneficiary_account_bank : null;
                                        $agency->extra_beneficiary_account_type = $record->extra_beneficiary_account_type != null ? $record->extra_beneficiary_account_type : null;
                                        $agency->extra_beneficiary_route = $record->extra_beneficiary_route != null ? $record->extra_beneficiary_route : null;
                                        $agency->extra_beneficiary_zelle = $record->extra_beneficiary_zelle != null ? $record->extra_beneficiary_zelle : null;
                                        $agency->extra_beneficiary_ach = $record->extra_beneficiary_ach != null ? $record->extra_beneficiary_ach : null;
                                        $agency->extra_beneficiary_swift = $record->extra_beneficiary_swift != null ? $record->extra_beneficiary_swift : null;
                                        $agency->extra_beneficiary_aba = $record->extra_beneficiary_aba != null ? $record->extra_beneficiary_aba : null;
                                        $agency->extra_beneficiary_address = $record->extra_beneficiary_address != null ? $record->extra_beneficiary_address : null;
                                        $agency->save();

                                        // 2. Busco en la tabla de COTIZACIONES INDIVIDUALES los registros asociados al agentes y actualizo la informacion
                                        // para migrar la informacion del agente a la agencia master
                                        $individualQuote = IndividualQuote::where('agent_id', $record->id)->get();
                                        foreach ($individualQuote as $quote) {
                                            $quote->agent_id = null;
                                            // Si la gencia es master el code_agency == owner_code
                                            $quote->owner_code = $data['owner_code'];
                                            $quote->code_agency = $user->code_agency;
                                            $quote->save();
                                        }

                                        // 3. Busco en la tabla de COTIZACIONES CORPOTAIVAS los registros asociados al agentes y actualizo la informacion
                                        // para migrar la informacion del agente a la agencia master
                                        $corporateQuote = CorporateQuote::where('agent_id', $record->id)->get();
                                        foreach ($corporateQuote as $corpquote) {
                                            $corpquote->agent_id = null;
                                            // Si la gencia es master el code_agency == owner_code
                                            $corpquote->owner_code = $data['owner_code'];
                                            $corpquote->code_agency = $user->code_agency;
                                            $corpquote->save();
                                        }

                                        // 4. Busco en la tabla de AFILIACIONES INDIVIDUALES los registros asociados al agentes y actualizo la informacion
                                        // para migrar la informacion del agente a la agencia master
                                        $afiliacionIndividual = Affiliation::where('agent_id', $record->id)->get();
                                        foreach ($afiliacionIndividual as $afiInvidual) {
                                            $afiInvidual->agent_id = null;
                                            // Si la gencia es master el code_agency == owner_code
                                            $afiInvidual->owner_code = $data['owner_code'];
                                            $afiInvidual->code_agency = $user->code_agency;
                                            $afiInvidual->save();
                                        }

                                        // 5. Busco en la tabla de AFILIACIONES CORPOTAIVAS los registros asociados al agentes y actualizo la informacion
                                        // para migrar la informacion del agente a la agencia master
                                        $afiliacionCorporativa = AffiliationCorporate::where('agent_id', $record->id)->get();
                                        foreach ($afiliacionCorporativa as $corp) {
                                            $corp->agent_id = null;
                                            // Si la gencia es master el code_agency == owner_code
                                            $corp->owner_code = $data['owner_code'];
                                            $corp->code_agency = $user->code_agency;
                                            $corp->save();
                                        }

                                        SecurityAudit::log('AUDIT_BUSINESS_AGENT_HIERARCHY_UPDATED', 'business.agents.edit-hierarchy', [
                                            'agent_id' => $record->id,
                                            'agent_name' => $record->name,
                                            'target_agency_type' => $data['type_agency'] ?? null,
                                            'owner_code' => $data['owner_code'] ?? null,
                                            'new_user_id' => $user->id,
                                        ]);
                                    } else {
                                        SecurityAudit::log('AUDIT_BUSINESS_AGENT_HIERARCHY_UPDATE_FAILED', 'business.agents.edit-hierarchy', [
                                            'agent_id' => $record->id,
                                            'agent_name' => $record->name,
                                            'reason' => 'user_not_found_by_email',
                                            'agent_email' => $record->email,
                                        ]);

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

                                // elimino al agente
                                $record->delete();

                                SecurityAudit::log('AUDIT_BUSINESS_AGENT_PROMOTED_AND_REMOVED', 'business.agents.edit-hierarchy', [
                                    'agent_id' => $record->id,
                                    'agent_name' => $record->name,
                                    'target_agency_type' => $data['type_agency'] ?? null,
                                ]);

                                Notification::make()
                                    ->title('ASCENSO EXITOSO')
                                    ->body('El agente acaba de ser ascendido al rol de agencias de forma exitosa.')
                                    ->icon('heroicon-s-check-circle')
                                    ->iconColor('success')
                                    ->color('success')
                                    ->send();

                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENT_HIERARCHY_UPDATE_FAILED', 'business.agents.edit-hierarchy', [
                                    'agent_id' => $record->id,
                                    'agent_name' => $record->name,
                                    'requested_type_agency' => $data['type_agency'] ?? null,
                                    'error' => $th->getMessage(),
                                ]);

                                Notification::make()
                                    ->title('EXCEPCION')
                                    ->body('Falla al realizar la activacion. Por favor comuniquese con el administrador.')
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('error')
                                    ->color('error')
                                    ->send();
                            }
                        })
                        ->hidden(fn () => ! in_array('SUPERADMIN', auth()->user()->departament)),
                    Action::make('Inactivate')
                        ->label('Inactivar')
                        ->requiresConfirmation()
                        ->action(function (Agent $record): void {
                            try {
                                $record->update(['status' => 'INACTIVO']);

                                SecurityAudit::log('AUDIT_BUSINESS_AGENT_INACTIVATED', 'business.agents.inactivate', [
                                    'agent_id' => $record->id,
                                    'agent_name' => $record->name,
                                    'agent_email' => $record->email,
                                ]);
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENT_INACTIVATE_FAILED', 'business.agents.inactivate', [
                                    'agent_id' => $record->id,
                                    'agent_name' => $record->name,
                                    'agent_email' => $record->email,
                                    'error' => $th->getMessage(),
                                ]);

                                Notification::make()
                                    ->title('EXCEPCION')
                                    ->body('No se pudo inactivar el agente.')
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->color('danger')
                                    ->send();
                            }
                        })
                        ->icon('heroicon-s-x-circle')
                        ->color('danger')
                        ->hidden(fn () => ! in_array('SUPERADMIN', auth()->user()->departament)),
                    ...(self::agentNoteBlogsTableExists() ? [
                        Action::make('add_agent_observation')
                            ->label('Registrar nota u observación')
                            ->icon('heroicon-o-pencil-square')
                            ->color('info')
                            ->modalHeading('Nota u observación del agente')
                            ->modalWidth(Width::Large)
                            ->form([
                                Section::make()
                                    ->schema([
                                        Textarea::make('note')
                                            ->label('Nota u observación')
                                            ->required()
                                            ->rows(5)
                                            ->maxLength(255)
                                            ->helperText('Texto interno de seguimiento (máx. 255 caracteres).'),
                                    ]),
                            ])
                            ->action(function (Agent $record, array $data): void {
                                try {
                                    $note = Str::limit(trim($data['note'] ?? ''), 255, '');

                                    AgentNoteBlog::create([
                                        'agent_id' => $record->id,
                                        'note' => $note,
                                        'created_by' => Auth::user()->name ?? (string) Auth::id(),
                                    ]);

                                    SecurityAudit::log('AUDIT_BUSINESS_AGENT_OBSERVATION_ADDED', 'business.agents.add-observation', [
                                        'agent_id' => $record->id,
                                        'agent_name' => $record->name,
                                        'note_length' => strlen($note),
                                    ]);

                                    Notification::make()
                                        ->title('Nota registrada')
                                        ->body('La observación quedó guardada en el historial del agente.')
                                        ->success()
                                        ->send();
                                } catch (\Throwable $th) {
                                    SecurityAudit::log('AUDIT_BUSINESS_AGENT_OBSERVATION_ADD_FAILED', 'business.agents.add-observation', [
                                        'agent_id' => $record->id,
                                        'agent_name' => $record->name,
                                        'error' => $th->getMessage(),
                                    ]);

                                    Notification::make()
                                        ->title('No se pudo guardar la nota')
                                        ->body('Intente de nuevo o contacte a soporte si el problema continúa.')
                                        ->danger()
                                        ->send();
                                }
                            }),
                    ] : []),
                    DeleteAction::make()
                        ->action(function (Agent $record): void {
                            try {
                                $record->delete();

                                SecurityAudit::log('AUDIT_BUSINESS_AGENT_DELETED', 'business.agents.delete', [
                                    'agent_id' => $record->id,
                                    'agent_name' => $record->name,
                                    'agent_email' => $record->email,
                                ]);
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENT_DELETE_FAILED', 'business.agents.delete', [
                                    'agent_id' => $record->id,
                                    'agent_name' => $record->name,
                                    'agent_email' => $record->email,
                                    'error' => $th->getMessage(),
                                ]);

                                throw $th;
                            }
                        })
                        ->color('danger')
                        ->label('Eliminar')
                        ->hidden(fn () => ! in_array('SUPERADMIN', auth()->user()->departament)),
                ])->icon('heroicon-c-ellipsis-vertical')->color('azulOscuro'),
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

                            $recordIds = $records->pluck('id')->values()->all();
                            $records = $records->toArray();

                            try {

                                for ($i = 0; $i < count($records); $i++) {

                                    // Agencias Tipo Master
                                    if ($records[$i]['agent_type_id'] == 2) {

                                        if ($records[$i]['status'] == 'INACTIVO' || $records[$i]['status'] == 'POR REVISION') {
                                            throw new \Exception('No se puede asignar un coordinador a un agente "INACTIVO" o en estatus "POR REVISION"');
                                        }

                                        // actualizo la ionformacion del agente y le asigno al administrador de negocios
                                        Agent::where('status', 'ACTIVO')->where('id', $records[$i]['id'])->first()
                                            ->update([
                                                'ownerAccountManagers' => $data['ownerAccountManagers'],
                                            ]);

                                        // Busco si el agente tiene subagente asignados a el
                                        // varificamos las agencias generales y los agentes asociados a ella
                                        $subAgents = Agent::where('status', 'ACTIVO')
                                            ->where('agent_type_id', 3)
                                            ->where('owner_agent', $records[$i]['id'])
                                            ->get();

                                        // Si la agencia master tiene agencias generales activas
                                        if (count($subAgents) > 0) {

                                            for ($j = 0; $j < count($subAgents); $j++) {
                                                // actualizo el valor del coordinador
                                                $subAgents[$j]->ownerAccountManagers = $data['ownerAccountManagers'];
                                                $subAgents[$j]->save();
                                            }
                                        }

                                    }

                                    // Agencias Tipo General
                                    if ($records[$i]['agent_type_id'] == 3) {

                                        if ($records[$i]['status'] == 'INACTIVO' || $records[$i]['status'] == 'POR REVISION') {
                                            throw new \Exception('No se puede asignar un coordinador a un agente "INACTIVO" o en estatus "POR REVISION"');
                                        }

                                        // Busco los agentes que pertenecen a la agencia master
                                        $agentes = Agent::where('status', 'ACTIVO')->where('owner_code', $records[0]['owner_code'])->get();

                                        // Si la agencia master tiene agentes activos
                                        if (count($agentes) > 0) {

                                            for ($k = 0; $k < count($agentes); $k++) {
                                                // actualizo el valor del coordinador
                                                $agentes[$k]->update([
                                                    'ownerAccountManagers' => $data['ownerAccountManagers'],
                                                ]);
                                            }
                                        }
                                    }
                                }

                                SecurityAudit::log('AUDIT_BUSINESS_AGENTS_ACCOUNT_MANAGER_ASSIGNED', 'business.agents.bulk-assign-account-manager', [
                                    'agents_ids' => $recordIds,
                                    'agents_count' => count($recordIds),
                                    'owner_account_manager_id' => $data['ownerAccountManagers'] ?? null,
                                ]);
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENTS_ACCOUNT_MANAGER_ASSIGN_FAILED', 'business.agents.bulk-assign-account-manager', [
                                    'agents_ids' => $recordIds,
                                    'agents_count' => count($recordIds),
                                    'owner_account_manager_id' => $data['ownerAccountManagers'] ?? null,
                                    'error' => $th->getMessage(),
                                ]);

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
                        ->hidden(fn () => ! in_array('SUPERADMIN', auth()->user()->departament)),
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
                                $recordIds = $records->pluck('id')->values()->all();

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
                                    if (! empty($record->id)) {
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

                                SecurityAudit::log('AUDIT_BUSINESS_AGENTS_BULK_DELETED', 'business.agents.bulk-delete', [
                                    'agents_ids' => $recordIds,
                                    'agents_count' => count($recordIds),
                                ]);

                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENTS_BULK_DELETE_FAILED', 'business.agents.bulk-delete', [
                                    'error' => $th->getMessage(),
                                ]);

                                Log::error('Error en eliminación masiva de agentes', [
                                    'message' => $th->getMessage(),
                                    'trace' => $th->getTraceAsString(),
                                ]);

                                Notification::make()
                                    ->title('Error de sistema')
                                    ->body('Ocurrió un problema al intentar eliminar los registros. El equipo técnico ha sido notificado.')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->hidden(fn () => ! in_array('SUPERADMIN', auth()->user()->departament)),
                    BulkAction::make('exportCsvController')
                        ->label('Exportar CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (Collection $records) {
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Selecciona al menos un agente')
                                    ->body('Marca los registros que deseas exportar o usa «Seleccionar todos» en la tabla.')
                                    ->send();

                                return;
                            }

                            $ids = $records->pluck('id')->all();
                            $token = AgentExportCsvController::storeIdsAndGetToken($ids);

                            return redirect()->route('business.agents.export-csv', ['token' => $token]);
                        }),
                ]),
            ])
            ->striped();
    }

    /**
     * En bases sin migración aplicada (p. ej. respaldos antiguos) la tabla puede no existir.
     */
    private static function agentNoteBlogsTableExists(): bool
    {
        try {
            return Schema::hasTable((new AgentNoteBlog)->getTable());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{events: list<array<string, mixed>>, total: int, loaded: int, limited: bool, max_id: int}
     */
    private static function agentNoteTimelinePayload(int $agentId): array
    {
        $limit = 100;
        $base = AgentNoteBlog::query()->where('agent_id', $agentId);
        $total = (clone $base)->count();
        $maxId = (int) ((clone $base)->max('id') ?? 0);
        $notes = (clone $base)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->sortBy(function (AgentNoteBlog $n): float {
                $ts = $n->created_at?->getTimestamp() ?? 0;

                return (float) $ts + ($n->id / 1_000_000);
            })
            ->values();

        $tz = (string) config('app.timezone');
        $events = [];
        foreach ($notes as $index => $n) {
            $at = $n->created_at?->timezone($tz);
            $noteText = (string) ($n->note ?? '');
            $events[] = [
                'side' => $index % 2 === 0 ? 'left' : 'right',
                'type' => 'note',
                'title' => 'Nota interna del agente',
                'summary' => Str::limit(trim(str_replace(["\r\n", "\r", "\n"], ' ', strip_tags($noteText))), 160, '…'),
                'display_name' => $n->created_by ?? '—',
                'actor' => $n->created_by,
                'initials' => self::initialsForAgentNoteAuthor($n->created_by),
                'avatar_url' => null,
                'datetime_full' => $at
                    ? $at->format('d/m/Y \a \l\a\s H:i').' ('.$tz.')'
                    : '—',
                'relative' => $at?->diffForHumans() ?? '—',
                'body_html' => HelpdeskObservationHtmlRenderer::render($noteText),
            ];
        }

        return [
            'events' => $events,
            'total' => $total,
            'loaded' => $notes->count(),
            'limited' => $total > $notes->count(),
            'max_id' => $maxId,
        ];
    }

    private static function initialsForAgentNoteAuthor(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/u', $name) ?: [];
        $parts = array_values(array_filter($parts, fn (string $p): bool => $p !== ''));
        if (count($parts) >= 2) {
            return Str::upper(Str::substr($parts[0], 0, 1).Str::substr($parts[1], 0, 1));
        }

        return Str::upper(Str::substr($name, 0, min(2, Str::length($name))));
    }

    private static function makeAgentCommandCenterAction(): Action
    {
        return Action::make('agentCommandCenter')
            ->label('Centro de acciones')
            ->icon('heroicon-m-squares-2x2')
            ->slideOver()
            ->formWrapper(false)
            ->modalWidth(Width::FiveExtraLarge)
            ->extraModalWindowAttributes([
                'class' => 'fi-agent-command-center-window',
            ])
            ->modalHeading(fn (Agent $record): string => 'Gestión rápida · '.$record->name)
            ->modalDescription(fn (Agent $record): string => 'Código AGT-000'.$record->id.' · Acciones y notas internas.')
            ->modalContent(function (Agent $record) {
                SecurityAudit::log('AUDIT_BUSINESS_AGENT_COMMAND_CENTER_OPENED', 'business.agents.command-center.open', [
                    'agent_id' => $record->id,
                    'agent_name' => $record->name,
                    'agent_code' => 'AGT-000'.$record->id,
                ]);

                $record->loadMissing(['affiliations.plan', 'typeAgent']);

                $sortedAffiliations = $record->affiliations->sortByDesc(function ($aff): int {
                    return $aff->created_at?->getTimestamp() ?? (int) $aff->getKey();
                })->values();
                $record->setRelation('affiliations', $sortedAffiliations);

                $noteTimeline = self::agentNoteBlogsTableExists()
                    ? self::agentNoteTimelinePayload($record->id)
                    : null;

                $isSuperadmin = in_array('SUPERADMIN', Auth::user()->departament ?? []);

                return view('filament.business.agents.agent-command-center', [
                    'record' => $record,
                    'noteTimeline' => $noteTimeline,
                    'canActivate' => $record->status !== 'ACTIVO',
                    'canEditHierarchy' => $isSuperadmin,
                    'canInactivate' => $isSuperadmin,
                    'canDelete' => $isSuperadmin,
                    'canAddObservation' => self::agentNoteBlogsTableExists(),
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->label('Cerrar')
                    ->extraAttributes([
                        'class' => HelpdeskTicketModalActions::IOS_GRAY_BTN,
                    ]),
            )
            ->action(fn (): null => null);
    }

    private static function daysSinceLastInteraction(Agent $record): ?int
    {
        $raw = $record->last_interaction_at ?? null;
        if ($raw === null) {
            return null;
        }

        try {
            return Carbon::parse($raw)->diffInDays(now());
        } catch (\Throwable) {
            return null;
        }
    }

    private static function daysSinceLastSale(Agent $record): ?int
    {
        $raw = $record->last_sale_at ?? null;
        if ($raw === null) {
            return null;
        }

        try {
            return Carbon::parse($raw)->diffInDays(now());
        } catch (\Throwable) {
            return null;
        }
    }

    private static function trafficLightLabel(Agent $record): string
    {
        $daysInteraction = self::daysSinceLastInteraction($record);
        $daysSale = self::daysSinceLastSale($record);

        // 🟢 Activo: cotización o venta en últimos 30 días.
        if ($daysInteraction !== null && $daysInteraction <= 30) {
            return 'Activo';
        }

        // 🔴 Inactivo: > 91 días sin producción (ventas) y sin interacción.
        $interactionIsStale = $daysInteraction === null || $daysInteraction >= 91;
        $saleIsStale = $daysSale === null || $daysSale >= 91;
        if ($interactionIsStale && $saleIsStale) {
            return 'Inactivo';
        }

        // 🟡 En Riesgo: sin ventas registradas entre 31 y 90 días.
        // Si no está activo y no cumple inactividad, lo consideramos en riesgo.
        return 'En Riesgo';
    }

    private static function trafficLightColor(Agent $record): string
    {
        return match (self::trafficLightLabel($record)) {
            'Activo' => 'success',
            'En Riesgo' => 'warning',
            'Inactivo' => 'danger',
            default => 'gray',
        };
    }

    private static function trafficLightIcon(Agent $record): string
    {
        return match (self::trafficLightLabel($record)) {
            'Activo' => 'heroicon-m-check-circle',
            'En Riesgo' => 'heroicon-m-exclamation-triangle',
            'Inactivo' => 'heroicon-m-x-circle',
            default => 'heroicon-m-minus-circle',
        };
    }
}
