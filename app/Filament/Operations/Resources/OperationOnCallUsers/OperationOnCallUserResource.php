<?php

namespace App\Filament\Operations\Resources\OperationOnCallUsers;

use App\Filament\Operations\Resources\OperationOnCallUsers\Pages\CreateOperationOnCallUser;
use App\Filament\Operations\Resources\OperationOnCallUsers\Pages\EditOperationOnCallUser;
use App\Filament\Operations\Resources\OperationOnCallUsers\Pages\ListOperationOnCallUsers;
use App\Filament\Operations\Resources\OperationOnCallUsers\Pages\ViewOperationOnCallUser;
use App\Filament\Operations\Resources\OperationOnCallUsers\Schemas\OperationOnCallUserForm;
use App\Filament\Operations\Resources\OperationOnCallUsers\Schemas\OperationOnCallUserInfolist;
use App\Filament\Operations\Resources\OperationOnCallUsers\Tables\OperationOnCallUsersTable;
use App\Models\OperationOnCallUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class OperationOnCallUserResource extends Resource
{
    protected static ?string $model = OperationOnCallUser::class;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACION';

    protected static ?string $navigationLabel = 'Roles de Guardia';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    public static function form(Schema $schema): Schema
    {
        return OperationOnCallUserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OperationOnCallUserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationOnCallUsersTable::configure($table);
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
            'index' => ListOperationOnCallUsers::route('/'),
            'create' => CreateOperationOnCallUser::route('/create'),
            'view' => ViewOperationOnCallUser::route('/{record}'),
            'edit' => EditOperationOnCallUser::route('/{record}/edit'),
        ];
    }
}
