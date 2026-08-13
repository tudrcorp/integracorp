<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\CreditReconciliations;

use App\Filament\Administration\Resources\CreditReconciliations\Pages\ListCreditReconciliations;
use App\Filament\Administration\Resources\CreditReconciliations\Pages\ViewCreditReconciliation;
use App\Filament\Business\Resources\CreditReconciliations\Schemas\CreditReconciliationInfolist;
use App\Filament\Business\Resources\CreditReconciliations\Tables\CreditReconciliationsTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\CreditReconciliation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CreditReconciliationResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = CreditReconciliation::class;

    protected static ?string $navigationLabel = 'Conciliación de crédito';

    protected static ?string $modelLabel = 'movimiento de crédito';

    protected static ?string $pluralModelLabel = 'movimientos de crédito';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACIÓN';

    protected static ?int $navigationSort = 25;

    public static function infolist(Schema $schema): Schema
    {
        return CreditReconciliationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CreditReconciliationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditReconciliations::route('/'),
            'view' => ViewCreditReconciliation::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }
}
