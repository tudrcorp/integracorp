<?php

namespace App\Filament\Marketing\Resources\CollaboratorAnniversaries;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Marketing\Resources\CollaboratorAnniversaries\Pages\CreateCollaboratorAnniversary;
use App\Filament\Marketing\Resources\CollaboratorAnniversaries\Pages\EditCollaboratorAnniversary;
use App\Filament\Marketing\Resources\CollaboratorAnniversaries\Pages\ListCollaboratorAnniversaries;
use App\Filament\Marketing\Resources\CollaboratorAnniversaries\Schemas\CollaboratorAnniversaryForm;
use App\Filament\Marketing\Resources\CollaboratorAnniversaries\Tables\CollaboratorAnniversariesTable;
use App\Models\CollaboratorAnniversary;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class CollaboratorAnniversaryResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = CollaboratorAnniversary::class;

    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACION/RRHH';

    protected static ?string $navigationLabel = 'Aniversarios';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return 'NEW';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return CollaboratorAnniversaryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CollaboratorAnniversariesTable::configure($table);
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
            'index' => ListCollaboratorAnniversaries::route('/'),
            'create' => CreateCollaboratorAnniversary::route('/create'),
            'edit' => EditCollaboratorAnniversary::route('/{record}/edit'),
        ];
    }
}
