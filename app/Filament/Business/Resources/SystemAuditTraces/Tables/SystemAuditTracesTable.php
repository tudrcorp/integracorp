<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\SystemAuditTraces\Tables;

use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SystemAuditTracesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Trazabilidad de Seguridad')
            ->description('Monitoreo de acciones clave: ventas, vouchers, aprobaciones, regeneración de PDF y compensación.')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn ($record): ?string => $record->created_at?->diffForHumans())
                    ->sortable(),
                TextColumn::make('action')
                    ->label('Acción')
                    ->badge()
                    ->color(fn (string $state): string => str_contains($state, 'FAILED')
                        ? 'danger'
                        : (str_contains($state, 'APPROVED')
                            ? 'success'
                            : (str_contains($state, 'REGISTERED')
                                ? 'info'
                                : (str_contains($state, 'UPLOADED') ? 'warning' : 'primary'))))
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Sistema')
                    ->searchable(),
                TextColumn::make('trace')
                    ->label('Trace ID')
                    ->state(fn ($record): string => self::traceIdFromResponse((string) ($record->response ?? '')))
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('route')
                    ->label('Módulo / Ruta')
                    ->limit(55)
                    ->tooltip(fn (string $state): string => $state)
                    ->searchable(),
                TextColumn::make('ip')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('details')
                    ->label('Resumen')
                    ->state(fn ($record): string => self::detailsSummary((string) ($record->response ?? '')))
                    ->limit(80),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Categoría')
                    ->options([
                        'sales' => 'Registro de ventas',
                        'voucher' => 'Carga de voucher',
                        'approval' => 'Aprobación de pagos',
                        'pdf' => 'Regeneración PDF',
                        'suppliers' => 'Proveedores (Operaciones)',
                        'tdev' => 'Compensación TDEV',
                        'failed' => 'Eventos fallidos',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'sales' => $query->where('action', 'like', '%SALE%'),
                            'voucher' => $query->where('action', 'like', '%VOUCHER%'),
                            'approval' => $query->where('action', 'like', '%APPROV%'),
                            'pdf' => $query->where('action', 'like', '%PDF%'),
                            'suppliers' => $query->where('action', 'like', 'AUDIT_OPERATIONS_SUPPLIER_%'),
                            'tdev' => $query->where('action', 'like', 'TDEV_COMPENSACION_%'),
                            'failed' => $query->where('action', 'like', '%FAILED%'),
                            default => $query,
                        };
                    }),
                Filter::make('created_at')
                    ->label('Rango de fecha')
                    ->form([
                        DatePicker::make('desde'),
                        DatePicker::make('hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $builder, string $date): Builder => $builder->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $builder, string $date): Builder => $builder->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (filled($data['desde'] ?? null)) {
                            $indicators['desde'] = 'Desde '.Carbon::parse($data['desde'])->format('d/m/Y');
                        }
                        if (filled($data['hasta'] ?? null)) {
                            $indicators['hasta'] = 'Hasta '.Carbon::parse($data['hasta'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                Action::make('view_trace')
                    ->label('Ver traza')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->slideOver()
                    ->modalWidth('4xl')
                    ->modalHeading('Detalle de traza')
                    ->modalDescription('Contexto técnico y de auditoría para revisiones de seguridad.')
                    ->modalSubmitAction(false)
                    ->modalContent(function ($record) {
                        $payload = self::decodeResponse((string) ($record->response ?? ''));

                        return view('filament.business.system-audit-traces.trace-details', [
                            'record' => $record,
                            'payload' => $payload,
                        ]);
                    }),
            ])
            ->toolbarActions([]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeResponse(string $response): array
    {
        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function traceIdFromResponse(string $response): string
    {
        $decoded = self::decodeResponse($response);

        return (string) ($decoded['trace_id'] ?? 'N/A');
    }

    private static function detailsSummary(string $response): string
    {
        $decoded = self::decodeResponse($response);
        $details = $decoded['details'] ?? null;
        if (! is_array($details) || $details === []) {
            return 'Sin metadatos adicionales';
        }

        $parts = [];
        foreach (array_slice($details, 0, 3, true) as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $parts[] = (string) $key.': '.(string) $value;
            }
        }

        return $parts === [] ? 'Detalles disponibles en modal' : implode(' | ', $parts);
    }
}
