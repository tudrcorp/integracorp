<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGeneratorImages;

use App\Filament\Business\Resources\PlanGeneratorImages\Pages\CreatePlanGeneratorImage;
use App\Filament\Business\Resources\PlanGeneratorImages\Pages\EditPlanGeneratorImage;
use App\Filament\Business\Resources\PlanGeneratorImages\Pages\ListPlanGeneratorImages;
use App\Filament\Business\Resources\PlanGeneratorImages\Schemas\PlanGeneratorImageForm;
use App\Filament\Business\Resources\PlanGeneratorImages\Tables\PlanGeneratorImagesTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\PlanGeneratorImage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PlanGeneratorImageResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = PlanGeneratorImage::class;

    protected static ?string $navigationLabel = 'Galería de imágenes';

    protected static ?string $modelLabel = 'imagen';

    protected static ?string $pluralModelLabel = 'imágenes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return PlanGeneratorImageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlanGeneratorImagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlanGeneratorImages::route('/'),
            'create' => CreatePlanGeneratorImage::route('/create'),
            'edit' => EditPlanGeneratorImage::route('/{record}/edit'),
        ];
    }
}
