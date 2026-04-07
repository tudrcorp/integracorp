<?php

namespace App\Filament\Business\Resources\Helpdesks;

use App\Filament\Business\Resources\Helpdesks\Pages\CreateHelpdesk;
use App\Filament\Business\Resources\Helpdesks\Pages\EditHelpdesk;
use App\Filament\Business\Resources\Helpdesks\Pages\ListHelpdesks;
use App\Filament\Business\Resources\Helpdesks\Schemas\HelpdeskForm;
use App\Filament\Business\Resources\Helpdesks\Tables\HelpdesksTable;
use App\Models\HelpDesk;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class HelpdeskResource extends Resource
{
    protected static ?string $model = HelpDesk::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

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
