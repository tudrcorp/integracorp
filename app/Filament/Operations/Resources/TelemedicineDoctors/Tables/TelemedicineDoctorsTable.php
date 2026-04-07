<?php

namespace App\Filament\Operations\Resources\TelemedicineDoctors\Tables;

use App\Models\TelemedicineDoctor;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelemedicineDoctorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('full_name', 'asc')
            ->heading('Directorio médico')
            ->description('Datos de contacto, identificación profesional y especialidad. Use «Editar» para actualizar ficha o firma.')
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->circular()
                    ->size(44)
                    ->defaultImageUrl(fn (TelemedicineDoctor $record): string => 'https://ui-avatars.com/api/?name='.urlencode(Str::limit($record->full_name ?? 'M', 40)).'&color=FFFFFF&background=1d4ed8')
                    ->extraImgAttributes(['class' => 'ring-2 ring-gray-200 dark:ring-gray-700'])
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('full_name')
                    ->label('Nombre')
                    ->icon('heroicon-o-user-circle')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->description(fn (TelemedicineDoctor $record): string => $record->email ?? '')
                    ->wrap(),
                TextColumn::make('nro_identificacion')
                    ->label('Identificación')
                    ->icon('heroicon-o-identification')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Número copiado')
                    ->fontFamily(FontFamily::Mono)
                    ->toggleable(),
                TextColumn::make('specialty')
                    ->label('Especialidad')
                    ->icon('heroicon-o-academic-cap')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->icon('heroicon-o-envelope')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->limit(28)
                    ->tooltip(fn (TelemedicineDoctor $record): ?string => strlen((string) $record->email) > 28 ? $record->email : null),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon('heroicon-o-phone')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('code_cm')
                    ->label('CM')
                    ->icon('heroicon-o-hashtag')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('code_mpps')
                    ->label('MPPS')
                    ->icon('heroicon-o-hashtag')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->icon('heroicon-o-map-pin')
                    ->searchable()
                    ->limit(36)
                    ->tooltip(fn (TelemedicineDoctor $record): ?string => strlen((string) ($record->address ?? '')) > 36 ? $record->address : null)
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Registro')
                    ->icon('heroicon-o-calendar-days')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn (TelemedicineDoctor $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('specialty')
                    ->label('Especialidad')
                    ->options(fn (): array => TelemedicineDoctor::query()
                        ->whereNotNull('specialty')
                        ->where('specialty', '!=', '')
                        ->distinct()
                        ->orderBy('specialty')
                        ->pluck('specialty', 'specialty')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->native(false),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filtros')
                    ->icon('heroicon-o-funnel'),
            )
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->color('danger')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar médicos')
                        ->modalDescription('Se eliminarán los médicos seleccionados y los usuarios de sistema asociados por correo. Esta acción no se puede deshacer.')
                        ->modalSubmitActionLabel('Sí, eliminar')
                        ->modalCancelActionLabel('Cancelar')
                        ->successNotificationTitle('Registros eliminados')
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                Log::info('OPERACIONES: El usuario '.Auth::user()->name.' eliminó al doctor: '.$record->full_name);
                                Log::info('OPERACIONES: El usuario '.Auth::user()->name.' eliminó usuario de sistema: '.$record->email);
                                User::query()->where('email', $record->email)->delete();
                                $record->delete();
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('No hay médicos registrados')
            ->emptyStateDescription('Agregue un profesional con el botón «Crear Nuevo Doctor» para verlo en esta lista.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
