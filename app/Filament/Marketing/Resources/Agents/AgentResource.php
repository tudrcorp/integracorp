<?php

namespace App\Filament\Marketing\Resources\Agents;

use App\Filament\Marketing\Resources\Agents\Pages\CreateAgent;
use App\Filament\Marketing\Resources\Agents\Pages\EditAgent;
use App\Filament\Marketing\Resources\Agents\Pages\ListAgents;
use App\Filament\Marketing\Resources\Agents\Pages\ViewAgent;
use App\Filament\Marketing\Resources\Agents\Schemas\AgentForm;
use App\Filament\Marketing\Resources\Agents\Schemas\AgentInfolist;
use App\Filament\Marketing\Resources\Agents\Tables\AgentsTable;
use App\Models\Agent;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AgentResource extends Resource
{
    protected static ?string $model = Agent::class;

    // protected static string|BackedEnum|null $navigationIcon = 'heroicon-c-academic-cap';

    protected static string|UnitEnum|null $navigationGroup = 'ESTRUCTURA DE CORRETAJES';

    protected static ?string $navigationLabel = 'Agentes De Corretajes';

    // protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return AgentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AgentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AgentsTable::configure($table);
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
            'index' => ListAgents::route('/'),
            'create' => CreateAgent::route('/create'),
            'view' => ViewAgent::route('/{record}'),
            'edit' => EditAgent::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        $module = 'MARKETING';
        $permission = Permission::where('module', $module)->where('slug', 'agentes-de-corretaje')->first();

        // si es superadmin, retornar true
        if (in_array('SUPERADMIN', Auth::user()->departament)) {
            return true;
        }

        if (in_array($module, Auth::user()->departament)) {
            if (UserPermission::where('user_id', Auth::user()->id)->where('permission_id', $permission->id)->exists()) {
                return true;
            }
        }

        return false;
    }
}
