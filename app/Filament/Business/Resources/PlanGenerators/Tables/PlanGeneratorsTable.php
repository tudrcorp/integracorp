<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Tables;

use App\Filament\Business\Resources\PlanGenerators\Tables\Actions\DeriveQuotationBulkAction;
use App\Models\PlanGenerator;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class PlanGeneratorsTable
{
    private static function statusColor(?string $state): string
    {
        return match (strtoupper((string) $state)) {
            'ACTIVO', 'ACTIVA', 'APROBADA', 'APROBADO' => 'success',
            'PRE-APROBADO' => 'warning',
            'INACTIVO', 'INACTIVA' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Agrupa cada registro base con las cotizaciones derivadas de él.
     *
     * La llave es `COALESCE(parent_id, id)`: un registro base se agrupa consigo
     * mismo y sus derivadas caen en la misma familia. El orden fija primero las
     * familias más recientes (el id del base es autoincremental) y, dentro de
     * cada una, el base antes de sus derivadas; el orden de la tabla solo
     * desempata entre derivadas.
     */
    private static function familyGroup(): Group
    {
        $familyKey = 'COALESCE(plan_generators.parent_id, plan_generators.id)';

        return Group::make('family')
            ->label('Familia')
            ->titlePrefixedWithLabel(false)
            ->collapsible()
            ->getKeyFromRecordUsing(fn (PlanGenerator $record): string => (string) self::familyKeyFor($record))
            ->getTitleFromRecordUsing(function (PlanGenerator $record): string {
                $base = $record->templateBase();

                return '# '.((string) $base->control_number).' · '.((string) $base->name);
            })
            ->getDescriptionFromRecordUsing(function (PlanGenerator $record): string {
                $base = $record->templateBase();
                $derived = (int) ($base->derived_quotations_count ?? 0);

                $client = trim((string) $base->client_data);
                $parts = $client !== '' ? [$client] : [];

                $parts[] = match (true) {
                    $derived === 0 => 'Registro base · sin cotizaciones derivadas',
                    $derived === 1 => 'Registro base + 1 cotización derivada',
                    default => 'Registro base + '.$derived.' cotizaciones derivadas',
                };

                return implode(' · ', $parts);
            })
            ->orderQueryUsing(fn (EloquentBuilder $query): EloquentBuilder => $query
                ->orderByRaw($familyKey.' desc')
                ->orderByRaw('CASE WHEN plan_generators.parent_id IS NULL THEN 0 ELSE 1 END'))
            ->scopeQueryUsing(fn (EloquentBuilder $query, PlanGenerator $record): EloquentBuilder => $query
                ->whereRaw($familyKey.' = ?', [self::familyKeyFor($record)]))
            ->scopeQueryByKeyUsing(fn (EloquentBuilder $query, ?string $key): EloquentBuilder => $query
                ->whereRaw($familyKey.' = ?', [(int) $key]))
            ->groupQueryUsing(fn ($query) => $query->groupByRaw($familyKey));
    }

    /**
     * Llave de familia coherente con el título de la cabecera.
     *
     * `templateBase()` devuelve el propio registro cuando su `parent_id` apunta
     * a una fila que ya no existe, así que la llave se saca de ahí y no de la
     * columna: de otro modo un huérfano caería en una familia cuyo título se
     * resuelve desde él mismo y Filament pintaría dos cabeceras para un grupo.
     */
    private static function familyKeyFor(PlanGenerator $record): int
    {
        return (int) $record->templateBase()->getKey();
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Generador de planes')
            ->description('Planes comerciales con matrices de beneficios, tarifas por rango etario y totales grupales.')
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->deferFilters(false)
            ->recordTitleAttribute('name')
            ->emptyStateHeading('Sin planes generados')
            ->emptyStateDescription('Crea el primer plan o ajusta los filtros para ver resultados.')
            ->emptyStateIcon(Heroicon::OutlinedTableCells)
            ->columns([
                ColumnGroup::make('Plan', [
                    TextColumn::make('control_number')
                        ->label('Nro. Control')
                        ->icon(Heroicon::OutlinedHashtag)
                        ->badge()
                        ->color('gray')
                        ->searchable()
                        ->sortable()
                        ->placeholder('—'),
                    TextColumn::make('name')
                        ->label('Nombre del plan')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->weight('semibold')
                        ->searchable()
                        ->sortable()
                        ->limit(32)
                        ->tooltip(fn (PlanGenerator $record): string => (string) $record->name),
                    TextColumn::make('status')
                        ->label('Estatus')
                        ->badge()
                        ->color(fn (?string $state): string => self::statusColor($state))
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('origin')
                        ->label('Origen')
                        ->badge()
                        ->state(fn (PlanGenerator $record): string => $record->isDerivedQuotation()
                            ? '↳ Derivada'
                            : 'Base')
                        ->color(fn (PlanGenerator $record): string => $record->isDerivedQuotation() ? 'info' : 'gray')
                        ->tooltip(fn (PlanGenerator $record): string => $record->isDerivedQuotation()
                            ? 'Derivada de la plantilla # '.((string) $record->templateBase()->control_number)
                            : 'Registro base: sirve de plantilla para nuevas cotizaciones'),
                ]),
                ColumnGroup::make('Propuesta comercial', [
                    TextColumn::make('client_data')
                        ->label('Cliente')
                        ->icon(Heroicon::OutlinedBuildingOffice2)
                        ->searchable()
                        ->limit(36)
                        ->tooltip(fn (PlanGenerator $record): ?string => filled($record->client_data) ? (string) $record->client_data : null)
                        ->placeholder('—'),
                    TextColumn::make('agent_name')
                        ->label('Agente')
                        ->icon(Heroicon::OutlinedUser)
                        ->searchable()
                        ->limit(28)
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('population_summary')
                        ->label('Población')
                        ->icon(Heroicon::OutlinedUsers)
                        ->searchable()
                        ->badge()
                        ->color('info')
                        ->placeholder('—'),
                    TextColumn::make('issued_at')
                        ->label('Emisión')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->date('d/m/Y')
                        ->sortable()
                        ->placeholder('—')
                        ->toggleable(),
                ]),
                ColumnGroup::make('Estructura', [
                    TextColumn::make('columns_count')
                        ->label('Columnas')
                        ->counts('columns')
                        ->badge()
                        ->color('blue')
                        ->alignCenter(),
                    TextColumn::make('rows_count')
                        ->label('Beneficios')
                        ->counts('rows')
                        ->badge()
                        ->color('gray')
                        ->alignCenter(),
                    TextColumn::make('rate_rows_count')
                        ->label('Rangos')
                        ->counts('rateRows')
                        ->badge()
                        ->color('amber')
                        ->alignCenter(),
                ]),
                ColumnGroup::make('Auditoría', [
                    TextColumn::make('created_by')
                        ->label('Creado por')
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->placeholder('—'),
                    TextColumn::make('created_at')
                        ->label('Creado')
                        ->icon(Heroicon::OutlinedClock)
                        ->dateTime('d/m/Y H:i')
                        ->sortable()
                        ->toggleable(),
                    TextColumn::make('updated_at')
                        ->label('Actualizado')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->dateTime('d/m/Y H:i')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ]),
            ])
            ->defaultGroup(self::familyGroup())
            // Resalta el registro base frente a sus derivadas. El check de la
            // cabecera del grupo se oculta por CSS (theme.css del panel): su
            // casilla seleccionaba la familia completa y la acción de derivar
            // trabaja sobre un solo registro.
            ->recordClasses(fn (PlanGenerator $record): string => $record->isDerivedQuotation()
                ? 'pg-family-derived-row'
                : 'pg-family-base-row')
            // Las familias arrancan cerradas: la tabla se lee de un golpe como
            // una lista de cotizaciones base y el analista abre solo la que le
            // interesa.
            ->collapsedGroupsByDefault()
            ->filters([
                TernaryFilter::make('derived')
                    ->label('Tipo de registro')
                    ->placeholder('Base y derivadas')
                    ->trueLabel('Solo cotizaciones derivadas')
                    ->falseLabel('Solo registros base')
                    ->queries(
                        true: fn (EloquentBuilder $query): EloquentBuilder => $query->whereNotNull('parent_id'),
                        false: fn (EloquentBuilder $query): EloquentBuilder => $query->whereNull('parent_id'),
                        blank: fn (EloquentBuilder $query): EloquentBuilder => $query,
                    ),
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'PRE-APROBADO' => 'PRE-APROBADO',
                        'APROBADA' => 'APROBADA',
                        'ACTIVO' => 'ACTIVO',
                        'INACTIVO' => 'INACTIVO',
                    ])
                    ->native(false),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver plan'),
                    EditAction::make()
                        ->label('Editar'),
                    DeleteAction::make()
                        ->label('Eliminar'),
                ]),
            ])
            ->toolbarActions([
                DeriveQuotationBulkAction::make(),
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->modalDescription('Las cotizaciones derivadas de un registro base no se eliminan: quedan como registros base independientes.')
                        // Sin esto Filament puede borrar con un `DELETE` masivo
                        // que no dispara eventos de modelo, y `PlanGeneratorObserver`
                        // no llegaría a liberar a las cotizaciones derivadas.
                        ->fetchSelectedRecords(),
                ]),
            ]);
    }
}
