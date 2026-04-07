<?php

namespace App\Filament\Marketing\Resources\Helpdesks;

use App\Filament\Marketing\Resources\Helpdesks\Pages\CreateHelpdesk;
use App\Filament\Marketing\Resources\Helpdesks\Pages\EditHelpdesk;
use App\Filament\Marketing\Resources\Helpdesks\Pages\ListHelpdesks;
use App\Filament\Marketing\Resources\Helpdesks\Schemas\HelpdeskForm;
use App\Filament\Marketing\Resources\Helpdesks\Tables\HelpdesksTable;
use App\Models\HelpDesk;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class HelpdeskResource extends Resource
{
    protected static ?string $model = HelpDesk::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    public static function form(Schema $schema): Schema
    {
        return HelpdeskForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HelpdesksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHelpdesks::route('/'),
            'create' => CreateHelpdesk::route('/create'),
            'edit' => EditHelpdesk::route('/{record}/edit'),
        ];
    }
}
