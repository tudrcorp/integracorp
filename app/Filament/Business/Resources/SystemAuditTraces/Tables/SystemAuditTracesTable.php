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
            ->description('Monitoreo centralizado por módulo y tipo de evento para acciones clave del sistema.')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn ($record): ?string => $record->created_at?->diffForHumans())
                    ->sortable(),
                TextColumn::make('module')
                    ->label('Módulo')
                    ->state(fn ($record): string => self::resolveModuleLabel((string) ($record->action ?? '')))
                    ->badge()
                    ->color(fn ($record): string => self::resolveModuleColor((string) ($record->action ?? '')))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('action', $direction);
                    }),
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
                TextColumn::make('event_kind')
                    ->label('Evento')
                    ->state(fn ($record): string => self::resolveEventKindLabel((string) ($record->action ?? '')))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Fallido' => 'danger',
                        'Envío de correo' => 'info',
                        'Carga' => 'warning',
                        'Descarga' => 'gray',
                        'Visualización' => 'gray',
                        default => 'primary',
                    }),
                TextColumn::make('severity')
                    ->label('Severidad')
                    ->state(fn ($record): string => self::resolveSeverityLabel((string) ($record->action ?? '')))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Error' => 'danger',
                        'Advertencia' => 'warning',
                        default => 'success',
                    }),
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
                        'agents' => 'Agentes (Business)',
                        'agencies' => 'Agencias (Business)',
                        'travel' => 'Viajes (Agencias y Agentes)',
                        'quotes' => 'Cotizador y Cotizaciones',
                        'helpdesk' => 'Tickets Helpdesk',
                        'sessions' => 'Sesiones de usuario',
                        'tdev' => 'Compensación TDEV',
                        'failed' => 'Eventos fallidos',
                        'email' => 'Envíos de correo',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'sales' => $query->where('action', 'like', '%SALE%'),
                            'voucher' => $query->where('action', 'like', '%VOUCHER%'),
                            'approval' => $query->where('action', 'like', '%APPROV%'),
                            'pdf' => $query->where('action', 'like', '%PDF%'),
                            'suppliers' => $query->where('action', 'like', 'AUDIT_OPERATIONS_SUPPLIER_%'),
                            'agents' => $query->where(function (Builder $builder): Builder {
                                return $builder
                                    ->where('action', 'like', 'AUDIT_BUSINESS_AGENT_%')
                                    ->orWhere('action', 'like', 'AUDIT_BUSINESS_AGENTS_%');
                            }),
                            'agencies' => $query->where(function (Builder $builder): Builder {
                                return $builder
                                    ->where('action', 'like', 'AUDIT_BUSINESS_AGENCY_%')
                                    ->orWhere('action', 'like', 'AUDIT_BUSINESS_AGENCIES_%');
                            }),
                            'travel' => $query->where(function (Builder $builder): Builder {
                                return $builder
                                    ->where('action', 'like', 'AUDIT_%_TRAVEL_AGENCY_%')
                                    ->orWhere('action', 'like', 'AUDIT_%_TRAVEL_AGENT_%');
                            }),
                            'quotes' => $query->where(function (Builder $builder): Builder {
                                return $builder
                                    ->where('action', 'like', 'AUDIT_%_COTIZADOR_%')
                                    ->orWhere('action', 'like', 'AUDIT_%_INDIVIDUAL_QUOTE_%')
                                    ->orWhere('action', 'like', 'AUDIT_%_CORPORATE_QUOTE_%')
                                    ->orWhere('action', 'like', 'AUDIT_%_DRESS_TYLOR_QUOTE_%');
                            }),
                            'helpdesk' => $query->where('action', 'like', 'AUDIT_HELPDESK_%'),
                            'sessions' => $query->where('action', 'like', 'AUDIT_USER_SESSION_%'),
                            'tdev' => $query->where('action', 'like', 'TDEV_COMPENSACION_%'),
                            'failed' => $query->where('action', 'like', '%FAILED%'),
                            'email' => $query->where('action', 'like', '%EMAIL%'),
                            default => $query,
                        };
                    }),
                SelectFilter::make('module')
                    ->label('Módulo')
                    ->options([
                        'operations' => 'Operaciones',
                        'business' => 'Business',
                        'marketing' => 'Marketing',
                        'administration' => 'Administración',
                        'tdev' => 'Compensación TDEV',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'operations' => $query->where('action', 'like', 'AUDIT_OPERATIONS_%'),
                            'business' => $query->where('action', 'like', 'AUDIT_BUSINESS_%'),
                            'marketing' => $query->where('action', 'like', 'AUDIT_MARKETING_%'),
                            'administration' => $query->where(function (Builder $builder): Builder {
                                return $builder
                                    ->where('action', 'like', 'AUDIT_AFFILIATION_%')
                                    ->orWhere('action', 'like', 'AUDIT_PAYMENT_%')
                                    ->orWhere('action', 'like', 'AUDIT_SALE_%');
                            }),
                            'tdev' => $query->where('action', 'like', 'TDEV_COMPENSACION_%'),
                            default => $query,
                        };
                    }),
                SelectFilter::make('severity')
                    ->label('Severidad')
                    ->options([
                        'success' => 'Éxito',
                        'warning' => 'Advertencia',
                        'danger' => 'Error',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'danger' => $query->where('action', 'like', '%FAILED%'),
                            'warning' => $query->where(function (Builder $builder): Builder {
                                return $builder
                                    ->where('action', 'like', '%UPLOADED%')
                                    ->orWhere('action', 'like', '%INACTIVATED%');
                            }),
                            'success' => $query->where(function (Builder $builder): Builder {
                                return $builder
                                    ->where('action', 'not like', '%FAILED%')
                                    ->where(function (Builder $inner): Builder {
                                        return $inner
                                            ->where('action', 'like', '%APPROVED%')
                                            ->orWhere('action', 'like', '%REGISTERED%')
                                            ->orWhere('action', 'like', '%CREATED%')
                                            ->orWhere('action', 'like', '%UPDATED%')
                                            ->orWhere('action', 'like', '%ACTIVATED%')
                                            ->orWhere('action', 'like', '%SENT%')
                                            ->orWhere('action', 'like', '%ASSIGNED%')
                                            ->orWhere('action', 'like', '%DELETED%')
                                            ->orWhere('action', 'like', '%DOWNLOADED%')
                                            ->orWhere('action', 'like', '%VIEWED%')
                                            ->orWhere('action', 'like', '%PROMOTED%');
                                    });
                            }),
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

    private static function resolveModuleLabel(string $action): string
    {
        if (str_starts_with($action, 'AUDIT_OPERATIONS_')) {
            return 'Operaciones';
        }

        if (str_starts_with($action, 'AUDIT_BUSINESS_')) {
            return 'Business';
        }

        if (str_starts_with($action, 'AUDIT_MARKETING_')) {
            return 'Marketing';
        }

        if (str_starts_with($action, 'TDEV_COMPENSACION_')) {
            return 'Compensación TDEV';
        }

        return 'Administración';
    }

    private static function resolveModuleColor(string $action): string
    {
        return match (self::resolveModuleLabel($action)) {
            'Operaciones' => 'warning',
            'Business' => 'success',
            'Compensación TDEV' => 'info',
            default => 'primary',
        };
    }

    private static function resolveEventKindLabel(string $action): string
    {
        if (str_contains($action, 'FAILED')) {
            return 'Fallido';
        }

        if (str_contains($action, 'EMAIL')) {
            return 'Envío de correo';
        }

        if (str_contains($action, 'UPLOADED')) {
            return 'Carga';
        }

        if (str_contains($action, 'DOWNLOADED')) {
            return 'Descarga';
        }

        if (str_contains($action, 'VIEWED')) {
            return 'Visualización';
        }

        if (str_contains($action, 'UPDATED')) {
            return 'Actualización';
        }

        if (str_contains($action, 'CREATED')) {
            return 'Creación';
        }

        if (str_contains($action, 'DELETED')) {
            return 'Eliminación';
        }

        return 'Acción';
    }

    private static function resolveSeverityLabel(string $action): string
    {
        if (str_contains($action, 'FAILED')) {
            return 'Error';
        }

        if (str_contains($action, 'UPLOADED') || str_contains($action, 'INACTIVATED')) {
            return 'Advertencia';
        }

        return 'Éxito';
    }
}
