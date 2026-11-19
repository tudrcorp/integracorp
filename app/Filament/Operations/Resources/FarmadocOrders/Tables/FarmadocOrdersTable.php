<?php

namespace App\Filament\Operations\Resources\FarmadocOrders\Tables;

use App\Filament\Operations\Resources\FarmadocOrders\Actions\FarmadocOrderActions;
use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceItems;
use App\Models\FarmadocOrder;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class FarmadocOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Pedidos a Farmadoc')
            ->description('Pedidos de medicamentos enviados por los analistas. El estatus se consulta en Farmadoc cada 30 minutos.')
            ->defaultSort('created_at', 'desc')
            ->poll('60s')
            ->searchPlaceholder('Buscar por pedido, orden de servicio, coordinación, paciente o analista')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Enviado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('order_number')
                    ->label('Pedido')
                    ->weight(FontWeight::SemiBold)
                    ->copyable()
                    ->copyMessage('Número copiado')
                    ->searchable(),
                TextColumn::make('service_order_number')
                    ->label('Orden de servicio')
                    ->placeholder('—')
                    ->copyable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('coordination_reference')
                    ->label('Coordinación')
                    ->description(fn (FarmadocOrder $record): ?string => $record->patient_name)
                    ->placeholder('Sin coordinación')
                    ->url(fn (FarmadocOrder $record): ?string => $record->operation_coordination_service_id !== null
                        ? ManageCoordinationServiceItems::getUrl(['record' => $record->operation_coordination_service_id])
                        : null)
                    ->searchable(['coordination_reference', 'patient_name'])
                    ->wrap(),
                TextColumn::make('items_count')
                    ->label('Medicamentos')
                    ->description(fn (FarmadocOrder $record): string => $record->units_count.' '.($record->units_count === 1 ? 'unidad' : 'unidades'))
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('created_by_name')
                    ->label('Analista')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => FarmadocOrder::statusLabel($state))
                    ->color(fn (?string $state): string => FarmadocOrder::statusColor($state))
                    ->sortable(),
                TextColumn::make('status_checked_at')
                    ->label('Consultado')
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i')
                    ->description(fn (FarmadocOrder $record): ?string => $record->status_check_error)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options(FarmadocOrder::statusOptions()),
                Filter::make('mine')
                    ->label('Solo mis pedidos')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('created_by_user_id', Auth::id())),
                Filter::make('sent_between')
                    ->label('Fecha de envío')
                    ->schema([
                        DatePicker::make('from')->label('Desde')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label('Hasta')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['from'] ?? null)) {
                            $indicators[] = 'Desde '.date('d/m/Y', strtotime((string) $data['from']));
                        }

                        if (filled($data['until'] ?? null)) {
                            $indicators[] = 'Hasta '.date('d/m/Y', strtotime((string) $data['until']));
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
                FarmadocOrderActions::refreshStatus(),
            ])
            ->emptyStateHeading('Aún no hay pedidos a Farmadoc')
            ->emptyStateDescription('Se registran al enviar un pedido desde «Hacer pedido al proveedor» en la gestión de una coordinación.');
    }
}
