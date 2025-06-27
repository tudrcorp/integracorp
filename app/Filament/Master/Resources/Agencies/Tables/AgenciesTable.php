<?php

namespace App\Filament\Master\Resources\Agencies\Tables;

use App\Models\User;
use App\Models\Agency;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Filament\Actions\BulkActionGroup;
use Illuminate\Support\Facades\Crypt;
use Filament\Actions\DeleteBulkAction;
use App\Http\Controllers\LogController;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use App\Http\Controllers\NotificationController;

class AgenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->query(Agency::query()->where('owner_code', Auth::user()->code_agency))
            ->heading('PERFIL DE LA AGENCIA')
            ->description('Información principal de la agencia')
            ->columns([

                TextColumn::make('code')
                    ->label('Codigo')
                    // ->prefix(fn(Agency $record) => Agency::where('code', $record->code)->with('typeAgency')->first()->typeAgency->definition . ' - ')
                    ->badge()
                    ->icon('heroicon-s-building-library')
                    ->color('warning')
                    ->searchable(),
                TextColumn::make('typeAgency.definition')
                    ->label('Tipo')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('name_corporative')
                    ->label('Razon social')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('rif')
                    ->label('Rif')
                    ->prefix('J-')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('ci_responsable')
                    ->label('CI. Responsable')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Direccion')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('phone')
                    ->label('Nro. de teléfono')
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
                    ->label('Contacto secundario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email_contact_2')
                    ->label('Email secundario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone_contact_2')
                    ->label('Telefono secundario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('local_beneficiary_name')
                    ->label('Nombre beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('local_beneficiary_rif')
                    ->label('Rif beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('local_beneficiary_account_number')
                    ->label('Nro. Cta. beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('local_beneficiary_account_bank')
                    ->label('Banco beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('local_beneficiary_account_type')
                    ->label('Tipo de Cuenta beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('local_beneficiary_phone_pm')
                    ->label('Nro. Pago movil')
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
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('commission_tdec_renewal')
                    ->label('Comisión TDEC Renovacion')
                    ->alignCenter()
                    ->suffix('%')
                    ->badge()
                    ->color('verde')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('commission_tdev')
                    ->alignCenter()
                    ->label('Comisión TDEV %')
                    ->suffix('%')
                    ->badge()
                    ->color('verde')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('commission_tdev_renewal')
                    ->alignCenter()
                    ->label('Comisión TDEV Renovacion')
                    ->suffix('%')
                    ->badge()
                    ->color('verde')
                    ->numeric()
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
            ])
            ->filters([
                //
            ])
            ->recordActions([
            ActionGroup::make([
                EditAction::make()
                    ->color('warning'),
                Action::make('Activate')
                    ->hidden(function (Agency $record) {
                        return $record->status == 'ACTIVO';
                    })
                    ->action(function (Agency $record) {

                        try {

                            if ($record->status == 'ACTIVO') {
                                Notification::make()
                                    ->title('AGENCIA YA ACTIVADA')
                                    ->body('La agencia ya se encuentra activa.')
                                    ->color('danger')
                                    ->icon('heroicon-o-x-circle')
                                    ->iconColor('danger')
                                    ->send();

                                return true;
                            }

                            $record->status = 'ACTIVO';
                            $record->save();

                            //3. Guardamos los cambios en la tabla logs
                            LogController::log(Auth::user()->id, 'ACTIVACION DE AGENCIA', 'AgencyResource:Action:Activate()', $record->save());

                            //4. creamos el usuario en la tabla users
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

                            /**
                             * Notificacion por whatsapp
                             * @param Agency $record
                             */
                            $phone = $record->phone;
                            $email = $record->email;
                            $nofitication = NotificationController::agency_activated($record->code, $phone, $email, $record->agency_type_id == 1 ? config('parameters.PATH_MASTER') : config('parameters.PATH_GENERAL'));

                            /**
                             * Notificacion por correo electronico
                             * CARTA DE BIENVENIDA
                             * @param Agency $record
                             */
                            $record->sendCartaBienvenida($record->code, $record->name, $record->email);

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
                    ->icon('heroicon-s-check-circle')
                    ->color('success')
                    ->requiresConfirmation(),
                Action::make('Inactivate')
                    ->hidden(function (Agency $record) {
                        return $record->status == 'INACTIVO';
                    })
                    ->action(fn(Agency $record) => $record->update(['status' => 'INACTIVO']))
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