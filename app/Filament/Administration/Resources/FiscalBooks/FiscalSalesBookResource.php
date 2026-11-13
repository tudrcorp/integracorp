<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\FiscalBooks;

use App\Enums\FiscalBook;
use App\Filament\Administration\Resources\FiscalBooks\Pages\ListFiscalSalesBook;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Libro de Ventas: facturas emitidas por Tu Doctor en Casa, mes a mes.
 */
class FiscalSalesBookResource extends FiscalBookResource
{
    protected static ?string $slug = 'libro-de-ventas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Libro de Ventas';

    protected static ?string $modelLabel = 'documento de venta';

    protected static ?string $pluralModelLabel = 'Libro de Ventas';

    protected static ?int $navigationSort = 35;

    public static function book(): FiscalBook
    {
        return FiscalBook::Sales;
    }

    public static function getPages(): array
    {
        return ['index' => ListFiscalSalesBook::route('/')];
    }
}
