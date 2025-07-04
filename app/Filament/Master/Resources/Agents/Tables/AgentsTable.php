<?php

namespace App\Filament\Master\Resources\Agents\Tables;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Agent;
use App\Models\Agency;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Filament\Actions\BulkActionGroup;
use Illuminate\Support\Facades\Crypt;
use Filament\Actions\DeleteBulkAction;
use App\Http\Controllers\LogController;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Controllers\NotificationController;

class AgentsTable
{
    public static function configure(Table $table): Table
    {
        /**
         * Logica para que la agencia master pueda ver todos los agentes registrados en su estructuta
         * Ve los agentes debajo de ella y los agentes debajo de sus agencia generales
         */
        $array_agency = Agency::select('code')->where('owner_code', Auth::user()->code_agency)->get()->toArray();
        $agency_list = [];
        for ($i = 0; $i < count($array_agency); $i++) {
            $agency_list[$i] = $array_agency[$i]['code'];
        }
        array_push($agency_list, Auth::user()->code_agency);
        
        return $table
        ->query(Agent::query()->whereIn('owner_code', $agency_list))
            ->defaultSort('id', 'desc')
            ->heading('AGENTES')
            ->description('Lista de Agentes registrados en el sistema.')
            ->columns([

                TextColumn::make('id')
                    ->label('Código')
                    ->prefix('AGT-000')
                    ->suffix(function (Agent $record) {
                        $agency_type = Agency::where('code', $record->owner_code)->with('typeAgency')->first()->typeAgency->definition;
                        if ($agency_type == 'GENERAL') {
                            return ' -> GENERAL-' . $record->owner_code;
                        }
                        return;
                    })
                    ->badge()
                    ->icon('heroicon-s-identification')
                    ->color('warning')
                    ->searchable(),
                TextColumn::make('typeAgent.definition')
                    ->label('Tipo')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('name')
                    ->label('Nombre completo')
                    ->searchable()
                    ->badge()
                    ->color('verde')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('ci')
                    ->label('CI:')
                    ->searchable()
                    ->badge()
                    ->color('verde')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('address')
                    ->label('Direccion')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('phone')
                    ->label('Número de Teléfono')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('user_instagram')
                    ->label('Usuario de Instagram')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('country.name')
                    ->label('País')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('city.definition')
                    ->label('Ciudad')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('region')
                    ->label('Región')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('name_contact_2')
                    ->label('Contacto Secundario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email_contact_2')
                    ->label('Email Secundario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone_contact_2')
                    ->label('Telefono Secundario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('local_beneficiary_name')
                    ->label('Nombre del Beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('local_beneficiary_rif')
                    ->label('Rif del Beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('local_beneficiary_account_number')
                    ->label('Nro de Cuenta del Beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('local_beneficiary_account_bank')
                    ->label('Banco del Beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('local_beneficiary_account_type')
                    ->label('Tipo de Cuenta del Beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('local_beneficiary_phone_pm')
                    ->label('Telefono Pago Movil')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                /**
                 * datos bancarios moneda extrangera
                 */
                TextColumn::make('extra_beneficiary_name')
                    ->label('MoEx. beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_ci_rif')
                    ->label('MoEx. Rif beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_account_number')
                    ->label('MoEx. Nro. Cta. beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_account_bank')
                    ->label('MoEx. Banco beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_account_type')
                    ->label('MoEx. Tipo de Cuenta beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_route')
                    ->label('MoEx. Ruta')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_zelle')
                    ->label('MoEx. Zelle')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_ach')
                    ->label('MoEx. ACH')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_swift')
                    ->label('MoEx. Swift')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_aba')
                    ->label('MoEx. ABA')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_address')
                    ->label('MoEx. Direccion')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),



                IconColumn::make('tdec')
                    ->label('TDEC')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: false),
                IconColumn::make('tdev')
                    ->label('TDEV')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('commission_tdec')
                    ->label('Comisión TDEC %')
                    ->alignCenter()
                    ->suffix('%')
                    ->badge()
                    ->color('verde')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('commission_tdec_renewal')
                    ->label('Comisión TDEC Renovacion')
                    ->alignCenter()
                    ->suffix('%')
                    ->badge()
                    ->color('verde')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('commission_tdev')
                    ->label('Comisión TDEV %')
                    ->alignCenter()
                    ->suffix('%')
                    ->badge()
                    ->color('verde')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('commission_tdev_renewal')
                    ->label('Comisión TDEV Renovacion')
                    ->alignCenter()
                    ->suffix('%')
                    ->badge()
                    ->color('verde')
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
            ->filtersTriggerAction(
                fn(Action $action) => $action
                    ->button()
                    ->label('Filtros'),
            )
            ->recordActions([
            ActionGroup::make([
                EditAction::make()
                    ->color('warning'),
                Action::make('Activate')
                    ->hidden(function (Agent $record) {
                        return $record->status == 'ACTIVO';
                    })
                    ->action(function (Agent $record) {

                        try {

                            if ($record->status == 'ACTIVO') {
                                Notification::make()
                                    ->title('AGENTE YA ACTIVADO')
                                    ->body('El agente ya se encuentra activo.')
                                    ->color('danger')
                                    ->icon('heroicon-o-x-circle')
                                    ->iconColor('danger')
                                    ->send();

                                return true;
                            }

                            $record->status = 'ACTIVO';
                            $record->save();

                            LogController::log(Auth::user()->id, 'ACTIVACION DE AGENTE', 'AgentResource:Action:Activate()', $record->save());

                            //4. creamos el usuario en la tabla users (AGENTES ASOCIADOS A LA AGENCIA GENERAL)
                            $user = new User();
                            $user->agent_id = $record->id;
                            $user->name = $record->name;
                            $user->email = $record->email;
                            $user->password = Hash::make('12345678');
                            $user->is_agent = true;
                            $user->code_agency = Auth::user()->code_agency;
                            $user->code_agent = 'AGT-000' . $record->id;
                            $user->link_agent = env('APP_URL') . '/at/lk/' . Crypt::encryptString($record->code_agent);
                            $user->status = 'ACTIVO';
                            $user->save();

                            /**
                             * Notificacion por correo electronico
                             * CARTA DE BIENVENIDA
                             * @param Agent $record
                             */
                            $record->sendCartaBienvenida($record->id, $record->name, $record->email);


                            $phone = $record->phone;
                            $email = $record->email;
                            $nofitication = NotificationController::agent_activated($phone, $email);

                            if ($nofitication['success'] == true) {
                                Notification::make()
                                    ->title('AGENTE ACTIVADO')
                                    ->body('Notificacion de activacion enviada con exito.')
                                    ->icon('heroicon-s-check-circle')
                                    ->iconColor('success')
                                    ->color('success')
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('AGENTE ACTIVADO')
                                    ->body('La notificacion de activacion no pudo ser enviada.')
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('warning')
                                    ->color('warning')
                                    ->send();
                            }
                        } catch (\Throwable $th) {
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
                    ->requiresConfirmation(),
                Action::make('Inactivate')
                    ->hidden(function (Agent $record) {
                        return $record->status == 'INACTIVO';
                    })
                    ->action(fn(Agent $record) => $record->update(['status' => 'INACTIVO']))
                    ->icon('heroicon-s-x-circle')
                    ->color('danger'),
            ])
                ->icon('heroicon-c-ellipsis-vertical')
                ->color('azulOscuro')
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}