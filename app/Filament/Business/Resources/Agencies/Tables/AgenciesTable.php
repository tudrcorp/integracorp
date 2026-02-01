<?php

namespace App\Filament\Business\Resources\Agencies\Tables;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Agent;
use App\Models\Agency;
use App\Models\AgencyType;
use Filament\Tables\Table;
use App\Models\Affiliation;
use Filament\Actions\Action;
use App\Models\CorporateQuote;
use App\Models\IndividualQuote;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Collection;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Log;
use App\Models\AffiliationCorporate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Crypt;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportBulkAction;
use App\Http\Controllers\LogController;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use App\Filament\Exports\AgencyExporter;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Controllers\AgencyController;
use App\Http\Controllers\NotificationController;

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
            ->heading('AGENCIAS')
            ->description('Lista de agencias registradas en el sistema')
            ->columns([
                TextColumn::make('owner_code')
                    ->label('Pertenece a:')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-s-building-library')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-o-building-office-2')
                    ->prefix(function ($record) {
                        $agency_type = AgencyType::select('definition')
                            ->where('id', $record->agency_type_id)
                            ->first()
                            ->definition;

                        return $agency_type . ' - ';
                    })
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('accountManager.full_name')
                    ->label('Account Manager')
                    ->icon('heroicon-o-shield-check')
                    ->badge()
                    ->searchable()
                    ->color('warning'),
                TextColumn::make('typeAgency.definition')
                    ->label('Tipo agencia')
                    ->searchable()
                    ->badge()
                    ->color('azulOscuro')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('name_corporative')
                    ->label('Razon social')
                    ->searchable()
                    ->badge()
                    ->color('verde')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('rif')
                    ->label('RIF:')
                    ->searchable()
                    ->badge()
                    ->color('verde')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('ci_responsable')
                    ->label('Cedula del responsable:')
                    ->searchable()
                    ->badge()
                    ->color('verde')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('address')
                    ->label('Direccion')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('phone')
                    ->label('Número de Teléfono')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
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
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('Activate')
                        ->label('Activar')
                        ->action(function (Agency $record) {

                            try {

                                //1. creamos el usuario en la tabla users para la agencia tipo master o general
                                $user = new User();
                                $user->name = $record->name_corporative;
                                $user->email = $record->email;
                                $user->password = Hash::make('12345678');
                                $user->is_agency = true;
                                $user->code_agency = $record->code;
                                $user->agency_type = $record->agency_type_id == 1 ? 'MASTER' : 'GENERAL';
                                $user->link_agency = env('APP_URL') . '/ay/lk/' . Crypt::encryptString($record->code);
                                $user->status = 'ACTIVO';
                                $user->save();

                                if ($user->save()) {
                                    $record->update(['status' => 'ACTIVO']);
                                }

                                /**
                                 * Notificacion por whatsapp
                                 * @param Agency $record
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

                            } catch (\Throwable $th) {
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
                        ->hidden(fn(Agency $record) => $record->status == 'ACTIVO'),
                    Action::make('edit_jerarquia')
                        ->requiresConfirmation()
                        ->label('Editar Jerarquía')
                        ->icon('heroicon-s-cog')
                        ->color('warning')
                        ->modalWidth(Width::ThreeExtraLarge)
                        ->action(function (Agency $record) {
                            
                            try {

                                //1. Busco la informacion del agente en la tabla de usuario para actualizar la informacion
                                // para que el agente acceda con el mismo usuario como agencia master
                                $user = User::where('email', $record->email)->first()->update([
                                    'agency_type' => 'MASTER',
                                ]);

                                //2. Busco en la tabla de COTIZACIONES INDIVIDUALES los registros asociados al agentes y actualizo la informacion
                                // para migrar la informacion del agente a la agencia master
                                $individualQuote = IndividualQuote::where('code_agency', $record->code)->get();
                                foreach ($individualQuote as $quote) {
                                    $quote->owner_code  = $record->code;
                                    $quote->save();
                                }

                                //3. Busco en la tabla de COTIZACIONES CORPOTAIVAS los registros asociados al agentes y actualizo la informacion
                                // para migrar la informacion del agente a la agencia master
                                $corporateQuote = CorporateQuote::where('code_agency', $record->code)->get();
                                foreach ($corporateQuote as $corpquote) {
                                    $corpquote->owner_code  = $record->code;
                                    $corpquote->save();
                                }

                                //4. Busco en la tabla de AFILIACIONES INDIVIDUALES los registros asociados al agentes y actualizo la informacion
                                // para migrar la informacion del agente a la agencia master
                                $afiliacionIndividual = Affiliation::where('code_agency', $record->code)->get();
                                foreach ($afiliacionIndividual as $afiInvidual) {
                                    $afiInvidual->owner_code  = $record->code;
                                    $afiInvidual->save();
                                }

                                //5. Busco en la tabla de AFILIACIONES CORPOTAIVAS los registros asociados al agentes y actualizo la informacion
                                // para migrar la informacion del agente a la agencia master
                                $afiliacionCorporativa = AffiliationCorporate::where('code_agency', $record->code)->get();
                                foreach ($afiliacionCorporativa as $corp) {
                                    $corp->owner_code  = $record->code;
                                    $corp->save();
                                }


                                Notification::make()
                                    ->title('ASCENSO EXITOSO')
                                    ->icon('heroicon-s-check-circle')
                                    ->iconColor('success')
                                    ->color('success')
                                    ->send();
                                    
                            } catch (\Throwable $th) {

                                Notification::make()
                                    ->title('EXCEPCION')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('error')
                                    ->color('error')
                                    ->send();
                            }
                        })
                        ->hidden(fn() => !in_array('SUPERADMIN', auth()->user()->departament)),
                    Action::make('Inactivate')
                        ->label('Inactivar')
                        ->action(fn(Agency $record) => $record->update(['status' => 'INACTIVO']))
                        ->icon('heroicon-s-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->hidden(fn() => !in_array('SUPERADMIN', auth()->user()->departament)),
                    DeleteAction::make()
                        ->color('danger')
                        ->label('Eliminar')
                        ->hidden(fn() => !in_array('SUPERADMIN', auth()->user()->departament)),
                ])
                    ->icon('heroicon-c-ellipsis-vertical')
                    ->color('azulOscuro')
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
                            // dd($records);
                            try {

                                for ($i = 0; $i < count($records); $i++) {
                                    
                                    //Agencias Tipo Master
                                    if ($records[$i]['agency_type_id'] == 1) {
                                        
                                        if ($records[$i]['status'] == 'INACTIVO' || $records[$i]['status'] == 'POR REVISION') {
                                            throw new \Exception('No se puede asignar un coordinador a un agencia  en estatus"INACTIVO" o "POR REVISION"');
                                        }

                                        //actualizo la agencia master
                                        Agency::where('status', 'ACTIVO')
                                        ->where('id', $records[$i]['id'])
                                        ->where('code', $records[$i]['code'])
                                        ->first()
                                        ->update([
                                            'ownerAccountManagers' => $data['ownerAccountManagers']
                                        ]);
                                        
                                        //Busco la agencia y validamos la estructura de la agebcia
                                        //varificamos las agencias generales y los agentes asociados a ella
                                        $agencyGenerals = Agency::where('status', 'ACTIVO')
                                        ->where('agency_type_id', 3)
                                        ->where('owner_code', $records[0]['owner_code'])
                                        ->get();

                                        //Si la agencia master tiene agencias generales activas
                                        if (count($agencyGenerals) > 0) {
                                            
                                            for ($j = 0; $j < count($agencyGenerals); $j++) {
                                                //actualizo el valor del coordinador
                                                $agencyGenerals[$j]->ownerAccountManagers = $data['ownerAccountManagers'];
                                                $agencyGenerals[$j]->save();

                                            }
            
                                        }

                                        //Busco los agentes que pertenecen a la agencia master
                                        $agentes = Agent::where('status', 'ACTIVO')
                                        ->where('owner_code', $records[0]['owner_code'])
                                        ->get();

                                        //Si la agencia master tiene agentes activos
                                        if (count($agentes) > 0) {

                                            for ($k = 0; $k < count($agentes); $k++) {
                                                //actualizo el valor del coordinador
                                                $agentes[$k]->ownerAccountManagers = $data['ownerAccountManagers'];
                                                $agentes[$k]->save();

                                                //Busco si el agente tiene subagente asignados a el
                                                //varificamos las agencias generales y los agentes asociados a ella
                                                $subAgents = Agent::where('status', 'ACTIVO')
                                                    ->where('agent_type_id', 3)
                                                    ->where('owner_agent', $agentes[$k]['id'])
                                                    ->get();

                                                //Si la agencia master tiene agencias generales activas
                                                if (count($subAgents) > 0) {

                                                    for ($l = 0; $l < count($subAgents); $l++) {
                                                        //actualizo el valor del coordinador
                                                        $subAgents[$l]->ownerAccountManagers = $data['ownerAccountManagers'];
                                                        $subAgents[$l]->save();
                                                    }
                                                }
                                            }
                                        }

                                    }

                                    //Agencias Tipo General
                                    if ($records[$i]['agency_type_id'] == 3) {

                                        if ($records[$i]['status'] == 'INACTIVO' || $records[$i]['status'] == 'POR REVISION') {
                                            throw new \Exception('No se puede asignar un coordinador a un agencia  en estatus"INACTIVO" o "POR REVISION"');
                                        }

                                        //actualizo la agencia general
                                        Agency::where('status', 'ACTIVO')
                                            ->where('id', $records[$i]['id'])
                                            ->where('code', $records[$i]['code'])
                                            ->first()
                                            ->update([
                                                'ownerAccountManagers' => $data['ownerAccountManagers']
                                            ]);
                                        
                                        //Busco los agentes que pertenecen a la agencia master
                                        $agentes = Agent::where('status', 'ACTIVO')
                                        ->where('owner_code', $records[0]['owner_code'])
                                        ->get();

                                        //Si la agencia master tiene agentes activos
                                        if (count($agentes) > 0) {

                                            for ($k = 0; $k < count($agentes); $k++) {
                                                //actualizo el valor del coordinador
                                                $agentes[$k]->update([
                                                    'ownerAccountManagers' => $data['ownerAccountManagers']
                                                ]);

                                                //Busco si el agente tiene subagente asignados a el
                                                //varificamos las agencias generales y los agentes asociados a ella
                                                $subAgents = Agent::where('status', 'ACTIVO')
                                                    ->where('agent_type_id', 3)
                                                    ->where('owner_agent', $agentes[$k]['id'])
                                                    ->get();

                                                //Si la agencia master tiene agencias generales activas
                                                if (count($subAgents) > 0) {

                                                    for ($l = 0; $l < count($subAgents); $l++) {
                                                        //actualizo el valor del coordinador
                                                        $subAgents[$l]->ownerAccountManagers = $data['ownerAccountManagers'];
                                                        $subAgents[$l]->save();
                                                    }
                                                }
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
                        ->hidden(fn() => !in_array('SUPERADMIN', auth()->user()->departament)),
                    ExportBulkAction::make()->exporter(AgencyExporter::class)->label('Exportar XLS')->color('warning')->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->striped();
    }
}