<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\FiscalBooks;

use App\Enums\FiscalBook;
use App\Filament\Administration\Resources\FiscalBooks\Pages\ListFiscalPurchasesBook;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Libro de Compras: facturas recibidas de proveedores, mes a mes.
 */
class FiscalPurchasesBookResource extends FiscalBookResource
{
    protected static ?string $slug = 'libro-de-compras';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmarkSquare;

    protected static ?string $navigationLabel = 'Libro de Compras';

    protected static ?string $modelLabel = 'documento de compra';

    protected static ?string $pluralModelLabel = 'Libro de Compras';

    protected static ?int $navigationSort = 36;

    public static function book(): FiscalBook
    {
        return FiscalBook::Purchases;
    }

    public static function getPages(): array
    {
        return ['index' => ListFiscalPurchasesBook::route('/')];
    }
}
