<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\SystemAuditTraces;

use App\Filament\Business\Resources\SystemAuditTraces\Pages\ListSystemAuditTraces;
use App\Filament\Business\Resources\SystemAuditTraces\Tables\SystemAuditTracesTable;
use App\Models\Log;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SystemAuditTraceResource extends Resource
{
    protected static ?string $model = Log::class;

    protected static ?string $navigationLabel = 'Trazas de Seguridad';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    protected static ?int $navigationSort = 90;

    public static function table(Table $table): Table
    {
        return SystemAuditTracesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['user'])
            ->where(function (Builder $builder): void {
                $builder
                    ->where('action', 'like', 'AUDIT_%')
                    ->orWhere('action', 'like', 'TDEV_COMPENSACION_%');
            });

        $user = Auth::user();
        $departments = (array) ($user?->departament ?? []);
        if (! in_array('SUPERADMIN', $departments, true)) {
            $query->where('user_id', (int) ($user?->id ?? 0));
        }

        return $query;
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

    public static function getPages(): array
    {
        return [
            'index' => ListSystemAuditTraces::route('/'),
        ];
    }
}
