<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Users\Tables;

use App\Http\Controllers\UtilsController;
use App\Models\Rol;
use App\Models\User;
use App\Support\Filament\UserRoleProfiles;
use App\Support\Filament\UserTableUi;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Usuarios INTEGRACORP')
            ->description('Gestiona accesos, módulos y perfiles. Edita un usuario para cambiar permisos o credenciales.')
            ->defaultSort('name', 'asc')
            ->recordTitleAttribute('name')
            ->searchPlaceholder('Buscar por nombre, correo o teléfono…')
            ->emptyStateHeading('No hay usuarios registrados')
            ->emptyStateDescription('Crea el primer usuario para comenzar a asignar módulos y permisos.')
            ->emptyStateIcon(Heroicon::OutlinedUserGroup)
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                ColumnGroup::make('Usuario', [
                    ImageColumn::make('avatar')
                        ->label('')
                        ->state(fn (User $record): string => $record->getFilamentAvatarUrl())
                        ->circular()
                        ->imageSize(40)
                        ->checkFileExistence(false)
                        ->extraHeaderAttributes(['class' => 'w-12']),
                    TextColumn::make('name')
                        ->label('Nombre')
                        ->icon(Heroicon::OutlinedUser)
                        ->weight(FontWeight::SemiBold)
                        ->searchable()
                        ->sortable()
                        ->wrap()
                        ->lineClamp(2)
                        ->description(fn (User $record): string => (string) $record->email),
                    TextColumn::make('email')
                        ->label('Correo')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->searchable()
                        ->sortable()
                        ->copyable()
                        ->copyMessage('Correo copiado')
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('identity_card')
                        ->label('Documento')
                        ->icon(Heroicon::OutlinedIdentification)
                        ->searchable()
                        ->copyable()
                        ->copyMessage('Documento copiado')
                        ->placeholder('—')
                        ->badge()
                        ->color('gray'),
                    TextColumn::make('phone')
                        ->label('Teléfono')
                        ->icon(Heroicon::OutlinedPhone)
                        ->searchable()
                        ->copyable()
                        ->copyMessage('Teléfono copiado')
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('birth_date')
                        ->label('Nacimiento')
                        ->icon(Heroicon::OutlinedCake)
                        ->date('d/m/Y')
                        ->placeholder('—')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ]),
                ColumnGroup::make('Acceso', [
                    TextColumn::make('status')
                        ->label('Estado')
                        ->badge()
                        ->sortable()
                        ->searchable()
                        ->color(fn (?string $state): string => UserTableUi::statusBadgeColor($state))
                        ->icon(fn (?string $state): Heroicon => match (strtoupper(trim((string) ($state ?? '')))) {
                            'ACTIVO' => Heroicon::OutlinedCheckCircle,
                            'INACTIVO' => Heroicon::OutlinedXCircle,
                            default => Heroicon::OutlinedQuestionMarkCircle,
                        }),
                    TextColumn::make('modules_display')
                        ->label('Módulos')
                        ->badge()
                        ->state(fn (User $record): array => UserTableUi::moduleBadgeLabels($record->departament))
                        ->color('info')
                        ->wrap(),
                    TextColumn::make('roles_summary')
                        ->label('Perfiles')
                        ->badge()
                        ->state(fn (User $record): array => UserRoleProfiles::activeRoleLabels($record))
                        ->color(fn (User $record): string => UserRoleProfiles::activeCount($record) > 0 ? 'success' : 'gray')
                        ->wrap()
                        ->toggleable(),
                ]),
                ColumnGroup::make('Comercial', [
                    TextColumn::make('commercial_summary')
                        ->label('Vínculo comercial')
                        ->icon(Heroicon::OutlinedBuildingStorefront)
                        ->state(fn (User $record): ?string => UserTableUi::commercialSummary($record))
                        ->placeholder('—')
                        ->wrap()
                        ->toggleable(),
                    TextColumn::make('code_agency')
                        ->label('UID agencia')
                        ->badge()
                        ->color('primary')
                        ->placeholder('—')
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('code_agent')
                        ->label('UID agente')
                        ->badge()
                        ->color('gray')
                        ->placeholder('—')
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('agency_type')
                        ->label('Tipo agencia')
                        ->badge()
                        ->color('warning')
                        ->placeholder('—')
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ]),
                ColumnGroup::make('Auditoría', [
                    TextColumn::make('created_at')
                        ->label('Registro')
                        ->dateTime('d/m/Y H:i')
                        ->description(fn (User $record): string => $record->created_at?->diffForHumans() ?? '')
                        ->sortable()
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('updated_at')
                        ->label('Actualización')
                        ->dateTime('d/m/Y H:i')
                        ->description(fn (User $record): string => $record->updated_at?->diffForHumans() ?? '')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('updated_by')
                        ->label('Actualizado por')
                        ->placeholder('—')
                        ->toggleable(isToggledHiddenByDefault: true),
                ]),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'ACTIVO' => 'Activo',
                        'INACTIVO' => 'Inactivo',
                    ])
                    ->native(false),
                SelectFilter::make('module')
                    ->label('Módulo')
                    ->options(fn (): array => Rol::query()->orderBy('name')->pluck('name', 'name')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return $query->whereJsonContains('departament', $value);
                    })
                    ->native(false),
                SelectFilter::make('profile')
                    ->label('Perfil')
                    ->options([
                        'agent' => 'Agente',
                        'subagent' => 'Subagente',
                        'agency' => 'Agencia',
                        'account_manager' => 'Administrador de cuentas',
                        'admin' => 'Administrador',
                        'super_admin' => 'Super administrador',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'agent' => $query->where('is_agent', true),
                            'subagent' => $query->where('is_subagent', true),
                            'agency' => $query->where('is_agency', true),
                            'account_manager' => $query->where('is_accountManagers', true),
                            'admin' => $query->where('is_admin', true),
                            'super_admin' => $query->where('is_superAdmin', true),
                            default => $query,
                        };
                    })
                    ->native(false),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver usuario'),
                    EditAction::make()
                        ->label('Editar usuario'),
                    Action::make('logoutUser')
                        ->label('Forzar cierre de sesión')
                        ->icon(Heroicon::OutlinedArrowRightOnRectangle)
                        ->requiresConfirmation()
                        ->modalHeading('Forzar cierre de sesión')
                        ->modalDescription('El usuario deberá volver a iniciar sesión en todos sus dispositivos.')
                        ->color('warning')
                        ->action(function (User $record): void {
                            UtilsController::logoutUser($record);
                        })
                        ->successNotificationTitle('Sesión cerrada. Solicite al usuario que ingrese nuevamente.')
                        ->failureNotificationTitle('No se pudo cerrar la sesión'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('resetPassword')
                        ->label('Resetear contraseña')
                        ->icon(Heroicon::OutlinedKey)
                        ->requiresConfirmation()
                        ->modalHeading('Resetear contraseña')
                        ->modalDescription('Se asignará la contraseña temporal 12345678 a los usuarios seleccionados.')
                        ->deselectRecordsAfterCompletion()
                        ->color('warning')
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                $record->password = Hash::make('12345678');
                                $record->updated_by = Auth::user()->name;
                                $record->save();

                                Log::info("Usuario: ID {$record->id} contraseña reseteada");
                            }

                            Notification::make()
                                ->success()
                                ->title('Contraseña reseteada')
                                ->send();
                        }),
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                ]),
            ])
            ->striped()
            ->deferFilters(false);
    }
}
