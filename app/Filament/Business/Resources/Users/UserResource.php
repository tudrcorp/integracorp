<?php

namespace App\Filament\Business\Resources\Users;

use App\Filament\Business\Resources\Concerns\ConfiguresBusinessGlobalSearch;
use App\Filament\Business\Resources\Users\Pages\CreateUser;
use App\Filament\Business\Resources\Users\Pages\EditUser;
use App\Filament\Business\Resources\Users\Pages\ListUsers;
use App\Filament\Business\Resources\Users\Pages\ViewUser;
use App\Filament\Business\Resources\Users\Schemas\UserForm;
use App\Filament\Business\Resources\Users\Schemas\UserInfolist;
use App\Filament\Business\Resources\Users\Tables\UsersTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class UserResource extends Resource
{
    use AuthorizesDepartmentNavigation;
    use ConfiguresBusinessGlobalSearch;

    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'Usuarios';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 8;

    protected static ?int $globalSearchSort = 15;

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchSelectColumns(): array
    {
        return ['id', 'name', 'email', 'phone', 'identity_card', 'status'];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchTextColumns(): array
    {
        return ['name', 'email', 'phone'];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchCodeColumns(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchDocumentColumns(): array
    {
        return ['identity_card'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof User) {
            return [];
        }

        return [
            'Correo' => filled($record->email) ? (string) $record->email : '—',
            'Documento' => filled($record->identity_card) ? (string) $record->identity_card : '—',
            'Teléfono' => filled($record->phone) ? (string) $record->phone : '—',
            'Estatus' => filled($record->status) ? (string) $record->status : '—',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|\Illuminate\Contracts\Support\Htmlable
    {
        if (! $record instanceof User) {
            return parent::getGlobalSearchResultTitle($record);
        }

        $name = filled($record->name) ? (string) $record->name : 'Usuario';
        $email = filled($record->email) ? (string) $record->email : null;

        return $email !== null ? $name.' · '.$email : $name;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
