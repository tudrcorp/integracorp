<?php

namespace App\Filament\Business\Resources\Agencies\Tables;

use App\Filament\Business\Resources\Helpdesks\Actions\HelpdeskTicketModalActions;
use App\Filament\Exports\AgencyExporter;
use App\Http\Controllers\NotificationController;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Agency;
use App\Models\AgencyNoteBlog;
use App\Models\AgencyType;
use App\Models\Agent;
use App\Models\CorporateQuote;
use App\Models\IndividualQuote;
use App\Models\User;
use App\Support\HelpdeskObservationHtmlRenderer;
use App\Support\SecurityAudit;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AgenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(function (Builder $query) {
                if (Auth::user()->is_accountManagers) {
                    return Agency::query()->where('ownerAccountManagers', Auth::user()->id);
                }

                return Agency::query();
            })
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->heading('Agencias')
            ->description('Listado de agencias registradas en el sistema. Todas las columnas están visibles por defecto; puedes reorganizarlas desde el selector de columnas.')
            ->columns([
                TextColumn::make('owner_code')
                    ->label('Pertenece a')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-s-building-library')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-o-building-office-2')
                    ->prefix(function (Agency $record): string {
                        $definition = AgencyType::query()
                            ->where('id', $record->agency_type_id)
                            ->value('definition');

                        return filled($definition) ? $definition.' - ' : '';
                    })
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->action(self::makeAgencyCommandCenterAction()),
                TextColumn::make('accountManager.full_name')
                    ->label('Account Manager')
                    ->icon('heroicon-o-shield-check')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->color('warning')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('typeAgency.definition')
                    ->label('Tipo de agencia')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('azulOscuro')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('name_corporative')
                    ->label('Razón social')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('verde')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('rif')
                    ->label('RIF')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('verde')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('ci_responsable')
                    ->label('Cédula del responsable')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('verde')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-envelope')
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('commission_tdec')
                    ->label('(%) TDEC')
                    ->suffix('%')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (Agency $record): string => ($record->commission_tdec ?? 0) > 0 ? 'success' : 'warning')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('commission_tdec_renewal')
                    ->label('(%) TDEC renovación')
                    ->suffix('%')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (Agency $record): string => ($record->commission_tdec_renewal ?? 0) > 0 ? 'success' : 'warning')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('commission_tdev')
                    ->label('(%) TDEV')
                    ->suffix('%')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (Agency $record): string => ($record->commission_tdev ?? 0) > 0 ? 'success' : 'warning')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('commission_tdev_renewal')
                    ->label('(%) TDEV renovación')
                    ->suffix('%')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (Agency $record): string => ($record->commission_tdev_renewal ?? 0) > 0 ? 'success' : 'warning')
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
                    ->description(fn (Agency $record) => $record->created_at->diffForHumans())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('updated_at')
                    ->label('Última modificación')
                    ->description(fn (Agency $record) => $record->updated_at->diffForHumans())
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
                        ->action(function (Agency $record) {

                            try {

                                // 1. creamos el usuario en la tabla users para la agencia tipo master o general
                                $user = new User;
                                $user->name = $record->name_corporative;
                                $user->email = $record->email;
                                $user->password = Hash::make('12345678');
                                $user->is_agency = true;
                                $user->code_agency = $record->code;
                                $user->agency_type = $record->agency_type_id == 1 ? 'MASTER' : 'GENERAL';
                                $user->link_agency = env('APP_URL').'/ay/lk/'.Crypt::encryptString($record->code);
                                $user->status = 'ACTIVO';
                                $user->save();

                                if ($user->save()) {
                                    $record->update(['status' => 'ACTIVO']);
                                }

                                /**
                                 * Notificacion por whatsapp
                                 *
                                 * @param  Agency  $record
                                 */
                                $phone = $record->phone;
                                $email = $record->email;
                                $nofitication = NotificationController::agency_activated($record->code, $phone, $email, $record->agency_type_id == 1 ? config('parameters.PATH_MASTER') : config('parameters.PATH_GENERAL'));

                                if ($nofitication) {

                                    Notification::make()
                                        ->title('ACTIVACION DE AGENCIA')
                                        ->body('Se ha activado la agencia correctamente.')
                                        ->icon('heroicon-s-check-circle')
                                        ->iconColor('success')
                                        ->color('success')
                                        ->send();

                                }

                                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_ACTIVATED', 'business.agencies.activate', [
                                    'agency_id' => $record->id,
                                    'agency_code' => $record->code,
                                    'agency_name' => $record->name_corporative,
                                    'agency_email' => $record->email,
                                    'created_user_id' => $user->id,
                                    'notification_sent' => (bool) $nofitication,
                                ]);

                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_ACTIVATE_FAILED', 'business.agencies.activate', [
                                    'agency_id' => $record->id,
                                    'agency_code' => $record->code,
                                    'agency_name' => $record->name_corporative,
                                    'agency_email' => $record->email,
                                    'error' => $th->getMessage(),
                                ]);

                                Log::error($th->getMessage());
                                Notification::make()
                                    ->title('EXCEPCION')
                                    ->body('Falla al realizar la activacion. Por favor comuniquese con el administrador.')
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('error')
                                    ->color('error')
                                    ->send();
                            }
                        })
                        ->icon('heroicon-s-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->hidden(fn (Agency $record) => $record->status == 'ACTIVO'),
                    Action::make('edit_jerarquia')
                        ->requiresConfirmation()
                        ->label('Editar Jerarquía')
                        ->icon('heroicon-s-cog')
                        ->color('warning')
                        ->modalWidth(Width::ThreeExtraLarge)
                        ->action(function (Agency $record) {

                            try {

                                // 1. Busco la informacion del agente en la tabla de usuario para actualizar la informacion
                                // para que el agente acceda con el mismo usuario como agencia master
                                $user = User::where('email', $record->email)->first()->update([
                                    'agency_type' => 'MASTER',
                                ]);

                                // 2. Busco en la tabla de COTIZACIONES INDIVIDUALES los registros asociados al agentes y actualizo la informacion
                                // para migrar la informacion del agente a la agencia master
                                $individualQuote = IndividualQuote::where('code_agency', $record->code)->get();
                                foreach ($individualQuote as $quote) {
                                    $quote->owner_code = $record->code;
                                    $quote->save();
                                }

                                // 3. Busco en la tabla de COTIZACIONES CORPOTAIVAS los registros asociados al agentes y actualizo la informacion
                                // para migrar la informacion del agente a la agencia master
                                $corporateQuote = CorporateQuote::where('code_agency', $record->code)->get();
                                foreach ($corporateQuote as $corpquote) {
                                    $corpquote->owner_code = $record->code;
                                    $corpquote->save();
                                }

                                // 4. Busco en la tabla de AFILIACIONES INDIVIDUALES los registros asociados al agentes y actualizo la informacion
                                // para migrar la informacion del agente a la agencia master
                                $afiliacionIndividual = Affiliation::where('code_agency', $record->code)->get();
                                foreach ($afiliacionIndividual as $afiInvidual) {
                                    $afiInvidual->owner_code = $record->code;
                                    $afiInvidual->save();
                                }

                                // 5. Busco en la tabla de AFILIACIONES CORPOTAIVAS los registros asociados al agentes y actualizo la informacion
                                // para migrar la informacion del agente a la agencia master
                                $afiliacionCorporativa = AffiliationCorporate::where('code_agency', $record->code)->get();
                                foreach ($afiliacionCorporativa as $corp) {
                                    $corp->owner_code = $record->code;
                                    $corp->save();
                                }

                                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_HIERARCHY_UPDATED', 'business.agencies.edit-hierarchy', [
                                    'agency_id' => $record->id,
                                    'agency_code' => $record->code,
                                    'agency_name' => $record->name_corporative,
                                    'updated_user_agency_type_to' => 'MASTER',
                                    'updated_related_counts' => [
                                        'individual_quotes' => count($individualQuote),
                                        'corporate_quotes' => count($corporateQuote),
                                        'affiliations' => count($afiliacionIndividual),
                                        'affiliation_corporates' => count($afiliacionCorporativa),
                                    ],
                                ]);

                                Notification::make()
                                    ->title('ASCENSO EXITOSO')
                                    ->icon('heroicon-s-check-circle')
                                    ->iconColor('success')
                                    ->color('success')
                                    ->send();

                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_HIERARCHY_UPDATE_FAILED', 'business.agencies.edit-hierarchy', [
                                    'agency_id' => $record->id,
                                    'agency_code' => $record->code,
                                    'agency_name' => $record->name_corporative,
                                    'error' => $th->getMessage(),
                                ]);

                                Notification::make()
                                    ->title('EXCEPCION')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('error')
                                    ->color('error')
                                    ->send();
                            }
                        })
                        ->hidden(fn () => ! in_array('SUPERADMIN', auth()->user()->departament)),
                    Action::make('resend_welcome_letter')
                        ->label('Reenviar carta de bienvenida')
                        ->icon('heroicon-o-envelope')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Reenviar carta de bienvenida')
                        ->modalDescription('Se enviará nuevamente la carta de bienvenida al correo principal de la agencia.')
                        ->action(function (Agency $record): void {
                            try {
                                if (! filled($record->email)) {
                                    Notification::make()
                                        ->title('Sin correo configurado')
                                        ->body('La agencia no tiene un correo principal registrado.')
                                        ->icon('heroicon-s-x-circle')
                                        ->iconColor('warning')
                                        ->color('warning')
                                        ->send();

                                    return;
                                }

                                $record->sendCartaBienvenida($record->code, $record->name_corporative, $record->email);

                                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_WELCOME_LETTER_RESENT', 'business.agencies.resend-welcome-letter', [
                                    'agency_id' => $record->id,
                                    'agency_code' => $record->code,
                                    'agency_name' => $record->name_corporative,
                                    'agency_email' => $record->email,
                                ]);

                                Notification::make()
                                    ->title('Carta de bienvenida reenviada')
                                    ->body('Se despachó el envío al correo '.$record->email.'.')
                                    ->icon('heroicon-s-check-circle')
                                    ->iconColor('success')
                                    ->color('success')
                                    ->send();
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_WELCOME_LETTER_RESEND_FAILED', 'business.agencies.resend-welcome-letter', [
                                    'agency_id' => $record->id,
                                    'agency_code' => $record->code,
                                    'agency_name' => $record->name_corporative,
                                    'agency_email' => $record->email,
                                    'error' => $th->getMessage(),
                                ]);

                                Notification::make()
                                    ->title('No se pudo reenviar la carta')
                                    ->body('Ocurrió un error al despachar el envío. Intente nuevamente.')
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('error')
                                    ->color('error')
                                    ->send();
                            }
                        }),
                    Action::make('Inactivate')
                        ->label('Inactivar')
                        ->action(function (Agency $record): void {
                            try {
                                $record->update(['status' => 'INACTIVO']);

                                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_INACTIVATED', 'business.agencies.inactivate', [
                                    'agency_id' => $record->id,
                                    'agency_code' => $record->code,
                                    'agency_name' => $record->name_corporative,
                                    'agency_email' => $record->email,
                                ]);
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_INACTIVATE_FAILED', 'business.agencies.inactivate', [
                                    'agency_id' => $record->id,
                                    'agency_code' => $record->code,
                                    'agency_name' => $record->name_corporative,
                                    'agency_email' => $record->email,
                                    'error' => $th->getMessage(),
                                ]);

                                Notification::make()
                                    ->title('EXCEPCION')
                                    ->body('No se pudo inactivar la agencia.')
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->color('danger')
                                    ->send();
                            }
                        })
                        ->icon('heroicon-s-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->hidden(fn () => ! in_array('SUPERADMIN', auth()->user()->departament)),
                    ...(self::agencyNoteBlogsTableExists() ? [
                        Action::make('add_agency_observation')
                            ->label('Registrar nota u observación')
                            ->icon('heroicon-o-pencil-square')
                            ->color('info')
                            ->modalHeading('Nota u observación de la agencia')
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
                            ->action(function (Agency $record, array $data): void {
                                try {
                                    $note = Str::limit(trim($data['note'] ?? ''), 255, '');

                                    AgencyNoteBlog::create([
                                        'agency_id' => $record->id,
                                        'note' => $note,
                                        'created_by' => Auth::user()->name ?? (string) Auth::id(),
                                    ]);

                                    SecurityAudit::log('AUDIT_BUSINESS_AGENCY_OBSERVATION_ADDED', 'business.agencies.add-observation', [
                                        'agency_id' => $record->id,
                                        'agency_code' => $record->code,
                                        'note_length' => strlen($note),
                                    ]);

                                    Notification::make()
                                        ->title('Nota registrada')
                                        ->body('La observación quedó guardada en el historial de la agencia.')
                                        ->success()
                                        ->send();
                                } catch (\Throwable $th) {
                                    SecurityAudit::log('AUDIT_BUSINESS_AGENCY_OBSERVATION_ADD_FAILED', 'business.agencies.add-observation', [
                                        'agency_id' => $record->id,
                                        'agency_code' => $record->code,
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
                        ->action(function (Agency $record): void {
                            try {
                                $record->delete();

                                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_DELETED', 'business.agencies.delete', [
                                    'agency_id' => $record->id,
                                    'agency_code' => $record->code,
                                    'agency_name' => $record->name_corporative,
                                    'agency_email' => $record->email,
                                ]);
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_DELETE_FAILED', 'business.agencies.delete', [
                                    'agency_id' => $record->id,
                                    'agency_code' => $record->code,
                                    'agency_name' => $record->name_corporative,
                                    'agency_email' => $record->email,
                                    'error' => $th->getMessage(),
                                ]);

                                throw $th;
                            }
                        })
                        ->color('danger')
                        ->label('Eliminar')
                        ->hidden(fn () => ! in_array('SUPERADMIN', auth()->user()->departament)),
                ])
                    ->icon('heroicon-c-ellipsis-vertical')
                    ->color('azulOscuro'),
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
                            // dd($records);
                            try {

                                for ($i = 0; $i < count($records); $i++) {

                                    // Agencias Tipo Master
                                    if ($records[$i]['agency_type_id'] == 1) {

                                        if ($records[$i]['status'] == 'INACTIVO' || $records[$i]['status'] == 'POR REVISION') {
                                            throw new \Exception('No se puede asignar un coordinador a un agencia  en estatus"INACTIVO" o "POR REVISION"');
                                        }

                                        // actualizo la agencia master
                                        Agency::where('status', 'ACTIVO')
                                            ->where('id', $records[$i]['id'])
                                            ->where('code', $records[$i]['code'])
                                            ->first()
                                            ->update([
                                                'ownerAccountManagers' => $data['ownerAccountManagers'],
                                            ]);

                                        // Busco la agencia y validamos la estructura de la agebcia
                                        // varificamos las agencias generales y los agentes asociados a ella
                                        $agencyGenerals = Agency::where('status', 'ACTIVO')
                                            ->where('agency_type_id', 3)
                                            ->where('owner_code', $records[0]['owner_code'])
                                            ->get();

                                        // Si la agencia master tiene agencias generales activas
                                        if (count($agencyGenerals) > 0) {

                                            for ($j = 0; $j < count($agencyGenerals); $j++) {
                                                // actualizo el valor del coordinador
                                                $agencyGenerals[$j]->ownerAccountManagers = $data['ownerAccountManagers'];
                                                $agencyGenerals[$j]->save();

                                            }

                                        }

                                        // Busco los agentes que pertenecen a la agencia master
                                        $agentes = Agent::where('status', 'ACTIVO')
                                            ->where('owner_code', $records[0]['owner_code'])
                                            ->get();

                                        // Si la agencia master tiene agentes activos
                                        if (count($agentes) > 0) {

                                            for ($k = 0; $k < count($agentes); $k++) {
                                                // actualizo el valor del coordinador
                                                $agentes[$k]->ownerAccountManagers = $data['ownerAccountManagers'];
                                                $agentes[$k]->save();

                                                // Busco si el agente tiene subagente asignados a el
                                                // varificamos las agencias generales y los agentes asociados a ella
                                                $subAgents = Agent::where('status', 'ACTIVO')
                                                    ->where('agent_type_id', 3)
                                                    ->where('owner_agent', $agentes[$k]['id'])
                                                    ->get();

                                                // Si la agencia master tiene agencias generales activas
                                                if (count($subAgents) > 0) {

                                                    for ($l = 0; $l < count($subAgents); $l++) {
                                                        // actualizo el valor del coordinador
                                                        $subAgents[$l]->ownerAccountManagers = $data['ownerAccountManagers'];
                                                        $subAgents[$l]->save();
                                                    }
                                                }
                                            }
                                        }

                                    }

                                    // Agencias Tipo General
                                    if ($records[$i]['agency_type_id'] == 3) {

                                        if ($records[$i]['status'] == 'INACTIVO' || $records[$i]['status'] == 'POR REVISION') {
                                            throw new \Exception('No se puede asignar un coordinador a un agencia  en estatus"INACTIVO" o "POR REVISION"');
                                        }

                                        // actualizo la agencia general
                                        Agency::where('status', 'ACTIVO')
                                            ->where('id', $records[$i]['id'])
                                            ->where('code', $records[$i]['code'])
                                            ->first()
                                            ->update([
                                                'ownerAccountManagers' => $data['ownerAccountManagers'],
                                            ]);

                                        // Busco los agentes que pertenecen a la agencia master
                                        $agentes = Agent::where('status', 'ACTIVO')
                                            ->where('owner_code', $records[0]['owner_code'])
                                            ->get();

                                        // Si la agencia master tiene agentes activos
                                        if (count($agentes) > 0) {

                                            for ($k = 0; $k < count($agentes); $k++) {
                                                // actualizo el valor del coordinador
                                                $agentes[$k]->update([
                                                    'ownerAccountManagers' => $data['ownerAccountManagers'],
                                                ]);

                                                // Busco si el agente tiene subagente asignados a el
                                                // varificamos las agencias generales y los agentes asociados a ella
                                                $subAgents = Agent::where('status', 'ACTIVO')
                                                    ->where('agent_type_id', 3)
                                                    ->where('owner_agent', $agentes[$k]['id'])
                                                    ->get();

                                                // Si la agencia master tiene agencias generales activas
                                                if (count($subAgents) > 0) {

                                                    for ($l = 0; $l < count($subAgents); $l++) {
                                                        // actualizo el valor del coordinador
                                                        $subAgents[$l]->ownerAccountManagers = $data['ownerAccountManagers'];
                                                        $subAgents[$l]->save();
                                                    }
                                                }
                                            }
                                        }
                                    }

                                }

                                SecurityAudit::log('AUDIT_BUSINESS_AGENCIES_ACCOUNT_MANAGER_ASSIGNED', 'business.agencies.bulk-assign-account-manager', [
                                    'agencies_ids' => $recordIds,
                                    'agencies_count' => count($recordIds),
                                    'owner_account_manager_id' => $data['ownerAccountManagers'] ?? null,
                                ]);

                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENCIES_ACCOUNT_MANAGER_ASSIGN_FAILED', 'business.agencies.bulk-assign-account-manager', [
                                    'agencies_ids' => $recordIds,
                                    'agencies_count' => count($recordIds),
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
                        ->action(function (Collection $records): void {
                            $recordIds = $records->pluck('id')->values()->all();

                            try {
                                foreach ($records as $record) {
                                    $record->delete();
                                }

                                SecurityAudit::log('AUDIT_BUSINESS_AGENCIES_BULK_DELETED', 'business.agencies.bulk-delete', [
                                    'agencies_ids' => $recordIds,
                                    'agencies_count' => count($recordIds),
                                ]);
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENCIES_BULK_DELETE_FAILED', 'business.agencies.bulk-delete', [
                                    'agencies_ids' => $recordIds,
                                    'agencies_count' => count($recordIds),
                                    'error' => $th->getMessage(),
                                ]);

                                throw $th;
                            }
                        })
                        ->hidden(fn () => ! in_array('SUPERADMIN', auth()->user()->departament)),
                    ExportBulkAction::make()->exporter(AgencyExporter::class)->label('Exportar XLS')->color('warning')->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->striped();
    }

    /**
     * @return array{events: list<array<string, mixed>>, total: int, loaded: int, limited: bool, max_id: int}
     */
    private static function agencyNoteTimelinePayload(int $agencyId): array
    {
        $limit = 100;
        $base = AgencyNoteBlog::query()->where('agency_id', $agencyId);
        $total = (clone $base)->count();
        $maxId = (int) ((clone $base)->max('id') ?? 0);
        $notes = (clone $base)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->sortBy(function (AgencyNoteBlog $n): float {
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
                'title' => 'Nota interna de la agencia',
                'summary' => Str::limit(trim(str_replace(["\r\n", "\r", "\n"], ' ', strip_tags($noteText))), 160, '…'),
                'display_name' => $n->created_by ?? '—',
                'actor' => $n->created_by,
                'initials' => self::initialsForAgencyNoteAuthor($n->created_by),
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

    private static function initialsForAgencyNoteAuthor(?string $name): string
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

    private static function agencyNoteBlogsTableExists(): bool
    {
        try {
            return Schema::hasTable((new AgencyNoteBlog)->getTable());
        } catch (\Throwable) {
            return false;
        }
    }

    private static function makeAgencyCommandCenterAction(): Action
    {
        return Action::make('agencyCommandCenter')
            ->label('Centro de acciones')
            ->icon('heroicon-m-squares-2x2')
            ->slideOver()
            ->formWrapper(false)
            ->modalWidth(Width::FiveExtraLarge)
            ->extraModalWindowAttributes([
                'class' => 'fi-agency-command-center-window',
            ])
            ->modalHeading(fn (Agency $record): string => 'Gestión rápida · '.$record->name_corporative)
            ->modalDescription(fn (Agency $record): string => 'Código '.$record->code.' · Datos de la agencia y notas internas.')
            ->modalContent(function (Agency $record) {
                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_COMMAND_CENTER_OPENED', 'business.agencies.command-center.open', [
                    'agency_id' => $record->id,
                    'agency_code' => $record->code,
                    'agency_name' => $record->name_corporative,
                ]);

                $record->loadMissing(['typeAgency']);

                $noteTimeline = self::agencyNoteBlogsTableExists()
                    ? self::agencyNoteTimelinePayload($record->id)
                    : null;

                return view('filament.business.agencies.agency-command-center', [
                    'record' => $record,
                    'noteTimeline' => $noteTimeline,
                    'canAddObservation' => self::agencyNoteBlogsTableExists(),
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
}
