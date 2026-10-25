<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicinePanelEvents;

use App\Enums\TelemedicinePanelEventLevel as Level;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\TelemedicinePanelEvents\Pages\ListTelemedicinePanelEvents;
use App\Filament\Operations\Resources\TelemedicinePanelEvents\Pages\ViewTelemedicinePanelEvent;
use App\Filament\Operations\Resources\TelemedicinePanelEvents\Schemas\TelemedicinePanelEventInfolist;
use App\Filament\Operations\Resources\TelemedicinePanelEvents\Tables\TelemedicinePanelEventsTable;
use App\Models\TelemedicinePanelEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;
use UnitEnum;

/**
 * Bitácora del panel de Telemedicina: lo que hacen los médicos y lo que les sale
 * mal, en vivo, para diagnosticar sin preguntarles. Solo lectura.
 *
 * Verla exige el permiso de menú `bitacora-panel-telemedicina` (o SUPERADMIN).
 */
class TelemedicinePanelEventResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = TelemedicinePanelEvent::class;

    protected static ?string $slug = 'bitacora-panel-telemedicina';

    protected static ?string $navigationLabel = 'Bitácora del panel';

    protected static ?string $modelLabel = 'evento del panel';

    protected static ?string $pluralModelLabel = 'Bitácora del panel de Telemedicina';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|UnitEnum|null $navigationGroup = 'TELEMEDICINA';

    protected static ?int $navigationSort = 5;

    public static function canView(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
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

    /**
     * Problemas de las últimas 24 horas. Se cachea un minuto: el menú se pinta en
     * cada petición del panel.
     */
    public static function getNavigationBadge(): ?string
    {
        try {
            $count = Cache::remember('telemedicine-panel-events:problems-24h', 60, static fn (): int => TelemedicinePanelEvent::query()
                ->whereIn('level', [Level::Warning->value, Level::Error->value])
                ->where('occurred_at', '>=', now()->subDay())
                ->count());
        } catch (Throwable) {
            return null;
        }

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Problemas de médicos en las últimas 24 horas';
    }

    public static function infolist(Schema $schema): Schema
    {
        return TelemedicinePanelEventInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TelemedicinePanelEventsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelemedicinePanelEvents::route('/'),
            'view' => ViewTelemedicinePanelEvent::route('/{record}'),
        ];
    }
}
