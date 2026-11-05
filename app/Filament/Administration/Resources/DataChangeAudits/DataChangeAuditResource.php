<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\DataChangeAudits;

use App\Filament\Administration\Resources\DataChangeAudits\Pages\ListDataChangeAudits;
use App\Filament\Administration\Resources\DataChangeAudits\Tables\DataChangeAuditsTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\DataChangeAudit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Auditoría de cambios: cada alta, modificación, eliminación y reversión de afiliaciones,
 * cotizaciones, agencias, agentes, ventas, cobranza, comisiones, planes, tarifas y Nuevos
 * Negocios, con el antes y el después de cada campo.
 *
 * Verla exige el permiso de menú `auditoria-de-cambios`; revertir, además, el permiso de
 * acción `revertir-cambios-auditados`. Es de solo lectura: nadie edita ni borra movimientos.
 */
class DataChangeAuditResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = DataChangeAudit::class;

    protected static ?string $slug = 'auditoria-de-cambios';

    protected static ?string $navigationLabel = 'Auditoría de cambios';

    protected static ?string $modelLabel = 'movimiento';

    protected static ?string $pluralModelLabel = 'auditoría de cambios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACIÓN';

    protected static ?int $navigationSort = 40;

    public static function table(Table $table): Table
    {
        return DataChangeAuditsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDataChangeAudits::route('/'),
        ];
    }
}
