<?php

namespace App\Filament\Administration\Resources\Agents\Tables;

use App\Filament\Exports\AgentExporter;
use App\Http\Controllers\LogController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UtilsController;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;

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
            ->heading('AGENTES')
            ->description('Lista de agentes registrados en el sistema')
            ->columns([
                TextColumn::make('owner_code')
                    ->label('Jerarquía')
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
                    ->searchable(),
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
                    ->color('verde')
                    ->tooltip('Ver perfil del agente')
                    ->extraAttributes([
                        'class' => 'cursor-pointer',
                    ])
                    ->action(
                        Action::make('view_agent_profile')
                            ->label('Ver perfil')
                            ->icon('heroicon-o-eye')
                            ->color('primary')
                            ->modalHeading('Perfil del Agente')
                            ->modalDescription('Vista principal del agente con estilo iOS.')
                            ->modalWidth('4xl')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Cerrar')
                            ->modalContent(function (Agent $record): ViewContract {
                                return View::make('filament.administration.agents.agent-quick-profile', [
                                    'agent' => $record->loadMissing(['typeAgent', 'agency', 'country', 'state', 'city']),
                                ]);
                            }),
                    ),
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
                    ->formatStateUsing(fn (mixed $state): HtmlString => self::iosStatusPill((string) $state))
                    ->html()
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

                                // 4. creamos el usuario en la tabla users (AGENTES)
                                $user = new User;
                                $user->name = $record->name;
                                $user->email = $record->email;
                                $user->password = Hash::make('12345678');
                                $user->is_agent = true;
                                $user->code_agency = $record->code_agency;
                                $user->code_agent = 'AGT-000'.$record->id;
                                $user->link_agent = env('APP_URL').'/at/lk/'.Crypt::encryptString($record->code_agent);
                                $user->agent_id = $record->id;
                                $user->status = 'ACTIVO';
                                $user->save();

                                /**
                                 * Notificacion por correo electronico
                                 * CARTA DE BIENVENIDA
                                 *
                                 * @param  Agent  $record
                                 */
                                $record->sendCartaBienvenida($record->id, $record->name, $record->email);

                                $phone = $record->phone;
                                $email = $record->email;
                                $nofitication = NotificationController::agent_activated($phone, $email, $record->agent_type_id == 2 ? config('parameters.PATH_AGENT') : config('parameters.PATH_SUBAGENT'));

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
                        ->action(fn (Agent $record) => $record->update(['status' => 'INACTIVO']))
                        ->icon('heroicon-s-x-circle')
                        ->color('danger'),
                    DeleteAction::make()
                        ->color('danger'),
                ])->icon('heroicon-c-ellipsis-vertical')->color('azulOscuro'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('format_phone')
                        ->label('Formatear Teléfonos')
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                $record->phone = UtilsController::normalizeVenezuelanPhone($record->phone);
                                $record->save();
                            }
                        })
                        ->requiresConfirmation()
                        ->color('azulOscuro'),

                    DeleteBulkAction::make(),
                    ExportBulkAction::make()->exporter(AgentExporter::class)->label('Exportar XLS')->color('warning')->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    private static function iosStatusPill(string $state): HtmlString
    {
        $normalized = strtoupper(trim($state));

        [$wrapperClass, $dotClass] = match ($normalized) {
            'ACTIVO' => [
                'border-emerald-500/35 bg-emerald-500/15 text-emerald-300 shadow-[0_0_0_1px_rgba(16,185,129,.18),0_10px_18px_-12px_rgba(16,185,129,.85)]',
                'bg-emerald-300',
            ],
            'INACTIVO' => [
                'border-rose-500/35 bg-rose-500/15 text-rose-300 shadow-[0_0_0_1px_rgba(244,63,94,.18),0_10px_18px_-12px_rgba(244,63,94,.85)]',
                'bg-rose-300',
            ],
            default => [
                'border-amber-500/35 bg-amber-500/15 text-amber-300 shadow-[0_0_0_1px_rgba(245,158,11,.18),0_10px_18px_-12px_rgba(245,158,11,.85)]',
                'bg-amber-300',
            ],
        };

        $label = $normalized !== '' ? e($normalized) : 'SIN ESTATUS';

        return new HtmlString(
            '<span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold tracking-wide '.$wrapperClass.'">'.
                '<span class="h-1.5 w-1.5 rounded-full '.$dotClass.'"></span>'.
                '<span>'.$label.'</span>'.
            '</span>'
        );
    }
}
