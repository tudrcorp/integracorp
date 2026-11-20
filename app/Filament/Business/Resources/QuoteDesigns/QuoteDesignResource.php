<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\QuoteDesigns;

use App\Filament\Business\Resources\QuoteDesigns\Pages\CreateQuoteDesign;
use App\Filament\Business\Resources\QuoteDesigns\Pages\EditQuoteDesign;
use App\Filament\Business\Resources\QuoteDesigns\Pages\ListQuoteDesigns;
use App\Filament\Business\Resources\QuoteDesigns\Schemas\QuoteDesignForm;
use App\Filament\Business\Resources\QuoteDesigns\Tables\QuoteDesignsTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\QuoteDesign;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Diseño de cotización: colores, tipografía, logo y textos de la página de cálculos, más las
 * imágenes de las páginas fijas de la propuesta económica, por plan. Lo carga el analista con lo
 * que envía el equipo de diseño (HTML de referencia + imágenes). Nada se borra: se inactiva.
 */
class QuoteDesignResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = QuoteDesign::class;

    protected static ?string $modelLabel = 'diseño de cotización';

    protected static ?string $pluralModelLabel = 'diseños de cotización';

    protected static ?string $navigationLabel = 'Diseño de cotización';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    public static function form(Schema $schema): Schema
    {
        return QuoteDesignForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuoteDesignsTable::configure($table);
    }

    /**
     * @return Builder<QuoteDesign>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('plans:id,description');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuoteDesigns::route('/'),
            'create' => CreateQuoteDesign::route('/create'),
            'edit' => EditQuoteDesign::route('/{record}/edit'),
        ];
    }
}
