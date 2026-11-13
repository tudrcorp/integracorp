<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\FiscalBooks;

use App\Enums\FiscalBook;
use App\Filament\Administration\Resources\FiscalBooks\Tables\FiscalBookEntriesTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\FiscalBookEntry;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Base de los dos libros fiscales. Cada libro es un recurso propio (permiso de
 * menú propio) sobre el mismo modelo, acotado a su libro.
 *
 * No hay páginas de crear ni editar: todo se hace en modales desde la lista,
 * que pasan por {@see \App\Support\Administration\FiscalBooks\FiscalBookEntryWriter}.
 */
abstract class FiscalBookResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = FiscalBookEntry::class;

    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACIÓN';

    protected static bool $isGloballySearchable = false;

    abstract public static function book(): FiscalBook;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forBook(static::book());
    }

    public static function table(Table $table): Table
    {
        return FiscalBookEntriesTable::configure($table, static::book());
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
}
