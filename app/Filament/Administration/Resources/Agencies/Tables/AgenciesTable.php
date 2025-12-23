<?php

namespace App\Filament\Administration\Resources\Agencies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Agency;
use App\Models\AgencyType;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use App\Http\Controllers\LogController;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
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
                    ->label('De:')
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
                    EditAction::make()
                        ->color('warning'),
                    Action::make('Activate')
                        ->action(function (Agency $record) {

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

                                if (Agency::where('email', $record->email)->exists()) {
                                    Notification::make()
                                        ->title('AGENTE YA REGISTRADO')
                                        ->body('El correo electronico del agente ya se encuentra registrado.')
                                        ->color('danger')
                                        ->send();

                                    return true;
                                }

                                // //1. Generamos el codigo y la activamos, cambiado el estatus
                                // $code = AgencyController::generate_code_agency();

                                // //2. Guardamos los cambios en la tabla agencies
                                // $record->code = $code;
                                // $record->status = 'ACTIVO';
                                // $record->save();

                                // //3. Guardamos los cambios en la tabla logs
                                // LogController::log(Auth::user()->id, 'ACTIVACION DE AGENTE', 'AgencyResource:Action:Activate()', $record->save());

                                // //4. creamos el usuario en la tabla users para la agencia tipo master o general
                                // $user = new User();
                                // $user->name = $record->name_corporative;
                                // $user->email = $record->email;
                                // $user->password = Hash::make('12345678');
                                // $user->is_agency = true;
                                // $user->code_agency = $record->code;
                                // $user->agency_type = $record->agency_type_id == 1 ? 'MASTER' : 'GENERAL';
                                // $user->link_agency = env('APP_URL') . '/ay/lk/' . Crypt::encryptString($record->code);
                                // $user->status = 'ACTIVO';
                                // $user->save();

                                // /**
                                //  * Notificacion por whatsapp
                                //  * @param Agency $record
                                //  */
                                // $phone = $record->phone;
                                // $email = $record->email;
                                // $nofitication = NotificationController::agency_activated($record->code, $phone, $email, $record->agency_type_id == 1 ? config('parameters.PATH_MASTER') : config('parameters.PATH_GENERAL'));

                                /**
                                 * Notificacion por correo electronico
                                 * CARTA DE BIENVENIDA
                                 * @param Agency $record
                                 */
                                // $record->sendCartaBienvenida($record->code, $record->name, $record->email);

                                // if ($nofitication['success'] == true) {
                                //     Notification::make()
                                //         ->title('AGENTE ACTIVADO')
                                //         ->body('Notificacion de activacion enviada con exito.')
                                //         ->icon('heroicon-s-check-circle')
                                //         ->iconColor('success')
                                //         ->color('success')
                                //         ->send();
                                // } else {
                                //     Notification::make()
                                //         ->title('AGENTE ACTIVADO')
                                //         ->body('La notificacion de activacion no pudo ser enviada.')
                                //         ->icon('heroicon-s-x-circle')
                                //         ->iconColor('warning')
                                //         ->color('warning')
                                //         ->send();
                                // }
                            } catch (\Throwable $th) {
                                LogController::log(Auth::user()->id, 'EXCEPCION', 'AgencyResource:Tables\Actions\Action::make(Activate)', $th->getMessage());
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