<?php

namespace App\Filament\Agents\Resources\Agents\Tables;

use App\Models\User;
use App\Models\Agent;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Filament\Actions\BulkActionGroup;
use Filament\Support\Enums\Alignment;
use Filament\Actions\DeleteBulkAction;
use App\Http\Controllers\LogController;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\NotificationController;

class AgentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(Agent::query()->where('owner_agent', Auth::user()->agent_id))
            ->heading('SUB-AGENTES')
            ->description('Tabla que contiene la informacion general de los subagentes')
            ->columns([

                TextColumn::make('id')
                    ->label('Código')
                    ->prefix('AGT-000')
                    ->alignCenter()
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('owner_code')
                    ->label('Gerarquia')
                    ->badge()
                    ->color('warning')
                    ->searchable(),

                TextColumn::make('code_agent')
                    ->label('Código agente')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('typeAgent.definition')
                    ->label('Tipo de Agente')
                    ->searchable()
                    ->badge()
                    ->color('azulOscuro')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('name')
                    ->label('Razon Social')
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
                    ->label('Email Corporativo')
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
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('local_beneficiary_rif')
                    ->label('Rif del Beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('local_beneficiary_account_number')
                    ->label('Nro de Cuenta del Beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('local_beneficiary_account_bank')
                    ->label('Banco del Beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('local_beneficiary_account_type')
                    ->label('Tipo de Cuenta del Beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('local_beneficiary_phone_pm')
                    ->label('Telefono Pago Movil')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                /**
                 * datos bancarios moneda extrangera
                 */
                TextColumn::make('extra_beneficiary_name')
                    ->label('MonEx-Nombre del Beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_ci_rif')
                    ->label('MonEx-Rif del Beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_account_number')
                    ->label('MonEx-Nro de Cuenta del Beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_account_bank')
                    ->label('MonEx-Banco del Beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_account_type')
                    ->label('MonEx-Tipo de Cuenta del Beneficiario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_route')
                    ->label('MonEx-Ruta')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_zelle')
                    ->label('MonEx-Zelle')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_ach')
                    ->label('MonEx-ACH')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_swift')
                    ->label('MonEx-Swift')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_aba')
                    ->label('MonEx-ABA')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_address')
                    ->label('MonEx-Direccion')
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


                IconColumn::make('fir_dig_agent')
                    ->alignment(Alignment::Center)
                    ->label('FD-Agencia')
                    ->icon(function ($record) {
                        // Muestra un ícono si la imagen existe
                        return $record->fir_dig_agent
                            ? 'heroicon-o-check-circle' // Ícono de "check" si la imagen existe
                            : 'heroicon-o-x-circle';   // Ícono de "x" si no existe
                    })
                    // ->iconPosition(IconPosition::After), // Posición del ícono
                    ->color(function ($record) {
                        // Color del ícono basado en la existencia de la imagen
                        return $record->fir_dig_agent
                            ? 'success' // Verde si la imagen existe
                            : 'danger'; // Rojo si no existe
                    })
                    ->url(function ($record) {
                        return asset('storage/' . $record->fir_dig_agent);
                    })
                    ->openUrlInNewTab(),
                IconColumn::make('fir_dig_agency')
                    ->alignment(Alignment::Center)
                    ->label('FD-Agencia Master')
                    ->icon(function ($record) {
                        // Muestra un ícono si la imagen existe
                        return $record->fir_dig_agency
                            ? 'heroicon-o-check-circle' // Ícono de "check" si la imagen existe
                            : 'heroicon-o-x-circle';   // Ícono de "x" si no existe
                    })
                    // ->iconPosition(IconPosition::After), // Posición del ícono
                    ->color(function ($record) {
                        // Color del ícono basado en la existencia de la imagen
                        return $record->fir_dig_agency
                            ? 'success' // Verde si la imagen existe
                            : 'danger'; // Rojo si no existe
                    })
                    ->url(function ($record) {
                        return asset('storage/' . $record->fir_dig_agency);
                    })
                    ->openUrlInNewTab(),
                IconColumn::make('file_ci_rif')
                    ->alignment(Alignment::Center)
                    ->label('CI/RIF')
                    ->icon(function ($record) {
                        // Muestra un ícono si la imagen existe
                        return $record->file_ci_rif
                            ? 'heroicon-o-check-circle' // Ícono de "check" si la imagen existe
                            : 'heroicon-o-x-circle';   // Ícono de "x" si no existe
                    })
                    // ->iconPosition(IconPosition::After), // Posición del ícono
                    ->color(function ($record) {
                        // Color del ícono basado en la existencia de la imagen
                        return $record->file_ci_rif
                            ? 'success' // Verde si la imagen existe
                            : 'danger'; // Rojo si no existe
                    })
                    //descargar imagen con un click
                    ->url(function ($record) {
                        return asset('storage/' . $record->file_ci_rif);
                    })
                    ->openUrlInNewTab(),
                IconColumn::make('file_w8_w9')
                    ->alignment(Alignment::Center)
                    ->label('W8/W9')
                    ->icon(function ($record) {
                        // Muestra un ícono si la imagen existe
                        return $record->file_w8_w9
                            ? 'heroicon-o-check-circle' // Ícono de "check" si la imagen existe
                            : 'heroicon-o-x-circle';   // Ícono de "x" si no existe
                    })
                    // ->iconPosition(IconPosition::After), // Posición del ícono
                    ->color(function ($record) {
                        // Color del ícono basado en la existencia de la imagen
                        return $record->file_w8_w9
                            ? 'success' // Verde si la imagen existe
                            : 'danger'; // Rojo si no existe
                    })
                    ->url(function ($record) {
                        return asset('storage/' . $record->file_w8_w9);
                    })
                    ->openUrlInNewTab(),
                IconColumn::make('file_account_usd')
                    ->alignment(Alignment::Center)
                    ->label('Cta. US$')
                    ->icon(function ($record) {
                        // Muestra un ícono si la imagen existe
                        return $record->file_account_usd
                            ? 'heroicon-o-check-circle' // Ícono de "check" si la imagen existe
                            : 'heroicon-o-x-circle';   // Ícono de "x" si no existe
                    })
                    // ->iconPosition(IconPosition::After), // Posición del ícono
                    ->color(function ($record) {
                        // Color del ícono basado en la existencia de la imagen
                        return $record->file_account_usd
                            ? 'success' // Verde si la imagen existe
                            : 'danger'; // Rojo si no existe
                    })
                    ->url(function ($record) {
                        return asset('storage/' . $record->file_account_usd);
                    })
                    ->openUrlInNewTab(),
                IconColumn::make('file_account_bsd')
                    ->alignment(Alignment::Center)
                    ->label('Cta.VES(Bs.)')
                    ->icon(function ($record) {
                        // Muestra un ícono si la imagen existe
                        return $record->file_account_bsd
                            ? 'heroicon-o-check-circle' // Ícono de "check" si la imagen existe
                            : 'heroicon-o-x-circle';   // Ícono de "x" si no existe
                    })
                    // ->iconPosition(IconPosition::After), // Posición del ícono
                    ->color(function ($record) {
                        // Color del ícono basado en la existencia de la imagen
                        return $record->file_account_bsd
                            ? 'success' // Verde si la imagen existe
                            : 'danger'; // Rojo si no existe
                    })
                    ->url(function ($record) {
                        return asset('storage/' . $record->file_account_bsd);
                    })
                    ->openUrlInNewTab(),
                IconColumn::make('file_account_zelle')
                    ->alignment(Alignment::Center)
                    ->label('Zelle')
                    ->icon(function ($record) {
                        // Muestra un ícono si la imagen existe
                        return $record->file_account_zelle
                            ? 'heroicon-o-check-circle' // Ícono de "check" si la imagen existe
                            : 'heroicon-o-x-circle';   // Ícono de "x" si no existe
                    })
                    // ->iconPosition(IconPosition::After), // Posición del ícono
                    ->color(function ($record) {
                        // Color del ícono basado en la existencia de la imagen
                        return $record->file_account_zelle
                            ? 'success' // Verde si la imagen existe
                            : 'danger'; // Rojo si no existe
                    })
                    ->url(function ($record) {
                        return asset('storage/' . $record->file_account_zelle);
                    })
                    ->openUrlInNewTab(),

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
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('Activate')
                        ->action(function (Agent $record) {

                            if ($record->status == 'ACTIVO') {
                                Notification::make()
                                    ->title('AGENTE YA ACTIVADO')
                                    ->body('El agente ya se encuentra activo.')
                                    ->color('danger')
                                    ->send();

                                return true;
                            }

                            //Generamos la parte entera del codigo
                            $integer_code_agent = str_replace('TDG-', '', Auth::user()->code_agent);
                            // dd($integer_code_agent);

                            $last_code = Agent::select('code_agent', 'id')->whereLike('code_agent', $integer_code_agent . '-%')->orderBy('id', 'desc')->first();
                            // dd($last_code);

                            /**
                             * Logica para generar el codigo del agente despues de su activacion
                             * -------------------------------------------------------------------
                             * @param $last_code
                             */
                            if (isset($last_code)) {
                                $code_agent = AgentController::generate_code_agent($last_code->code_agent);
                            } else {
                                $code_agent = $integer_code_agent . '-1';
                            }

                            // dd($code_agent);

                            $record->code_agent = $code_agent;
                            $record->status = 'ACTIVO';
                            $record->save();
                            LogController::log(Auth::user()->id, 'ACTIVACION DE AGENTE', 'AgentResource:Action:Activate()', $record->save());

                            //4. creamos el usuario en la tabla users (SUBAGENTES)
                            $user = new User();
                            $user->name = $record->name;
                            $user->email = $record->email;
                            $user->password = Hash::make('12345678');
                            $user->is_subagent = true;
                            $user->code_agent =  $record->code_agent;
                            $user->agent_id = $record->id;
                            $user->status = 'ACTIVO';
                            $user->save();

                            //Envio de NOtificaciones por WHATSAAP
                            $message = "Hola, Usted ha sido activado(a). \n\n" .
                                "Nombre: " . $record->name . "\n" .
                                "RIF: " . $record->ci ? $record->ci : $record->rif . "\n" .
                                "Teléfono: " . $record->phone . "\n" .
                                "Email: " . $record->email . "\n\n" .
                                "Saludos, \n";

                            $phone = $record->phone;

                            $send = NotificationController::agent_activated($phone, $message);
                            if ($send['success']) {
                                Notification::make()
                                    ->title('NOTIFICACION ENVIADA')
                                    ->body($send['message'])
                                    ->color($send['color'])
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('ERROR EN EL ENVIO')
                                    ->body('La notificación no fue enviada.')
                                    ->body($send['message'])
                                    ->color($send['color'])
                                    ->send();
                            }
                        })
                        ->icon('heroicon-s-check-circle')
                        ->color('success')
                        ->requiresConfirmation(),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}