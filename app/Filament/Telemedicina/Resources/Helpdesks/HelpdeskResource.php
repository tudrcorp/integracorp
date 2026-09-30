<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\Helpdesks;

use App\Filament\Concerns\AuthorizesHelpdeskTicketCreation;
use App\Filament\Telemedicina\Resources\Helpdesks\Pages\CreateHelpdesk;
use App\Filament\Telemedicina\Resources\Helpdesks\Pages\ListHelpdesks;
use App\Filament\Telemedicina\Resources\Helpdesks\Pages\ViewHelpdesk;
use App\Filament\Telemedicina\Resources\Helpdesks\Schemas\HelpdeskForm;
use App\Filament\Telemedicina\Resources\Helpdesks\Tables\HelpdesksTable;
use App\Models\HelpDesk;
use App\Support\HelpdeskInfolistSchema;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Tickets que el doctor crea desde Telemedicina para el equipo de Operaciones.
 * Solo ve los que él mismo creó; la atención (estado, reasignación) ocurre en Operaciones.
 */
class HelpdeskResource extends Resource
{
    use AuthorizesHelpdeskTicketCreation;

    protected static ?string $model = HelpDesk::class;

    protected static ?string $navigationLabel = 'Mis tickets';

    protected static ?string $modelLabel = 'ticket';

    protected static ?string $pluralModelLabel = 'Mis tickets';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|UnitEnum|null $navigationGroup = 'GESTIÓN TELEMÉDICA';

    protected static ?int $navigationSort = 90;

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return HelpdeskForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return HelpdeskInfolistSchema::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HelpdesksTable::configure($table);
    }

    /**
     * Base de la tabla y de la resolución por URL: un ticket ajeno responde 404.
     *
     * @return Builder<HelpDesk>
     */
    public static function getEloquentQuery(): Builder
    {
        $userId = Auth::id();

        $query = parent::getEloquentQuery()->with(['rrhhColaboradores:id,fullName']);

        if ($userId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('created_by_user_id', $userId);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHelpdesks::route('/'),
            'create' => CreateHelpdesk::route('/create'),
            'view' => ViewHelpdesk::route('/{record}'),
        ];
    }
}
