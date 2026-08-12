<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TdevAgencies;

use App\Filament\Business\Resources\Concerns\ConfiguresBusinessGlobalSearch;
use App\Filament\Business\Resources\TdevAgencies\Pages\CreateTdevAgency;
use App\Filament\Business\Resources\TdevAgencies\Pages\EditTdevAgency;
use App\Filament\Business\Resources\TdevAgencies\Pages\ListTdevAgencies;
use App\Filament\Business\Resources\TdevAgencies\Pages\ViewTdevAgency;
use App\Filament\Business\Resources\TdevAgencies\RelationManagers\AgentsRelationManager;
use App\Filament\Business\Resources\TdevAgencies\RelationManagers\ChildAgenciesRelationManager;
use App\Filament\Business\Resources\TdevAgencies\Schemas\TdevAgencyForm;
use App\Filament\Business\Resources\TdevAgencies\Schemas\TdevAgencyInfolist;
use App\Filament\Business\Resources\TdevAgencies\Tables\TdevAgenciesTable;
use App\Filament\Business\Resources\TdevAgencies\Widgets\TdevAgencyStatsOverview;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\TdevAgency;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use UnitEnum;

use function Filament\Support\original_request;

class TdevAgencyResource extends Resource
{
    use AuthorizesDepartmentNavigation;
    use ConfiguresBusinessGlobalSearch;

    protected static ?string $model = TdevAgency::class;

    protected static ?string $navigationLabel = 'AGENCIAS TDEV';

    protected static ?string $modelLabel = 'agencia TDEV';

    protected static ?string $pluralModelLabel = 'agencias TDEV';

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'ESTRUCTURA COMERCIAL';

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 8;

    protected static ?int $globalSearchSort = 95;

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return new HtmlString(
            '<img src="'.e(asset('image/IMG_0874.PNG')).'" alt="TDEV" class="fi-icon fi-size-lg h-6 w-6 object-contain" />'
        );
    }

    /**
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        if (! static::hasPage('index')) {
            return [];
        }

        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->parentItem(static::getNavigationParentItem())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(fn (): bool => original_request()->routeIs(static::getNavigationItemActiveRoutePattern()))
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->badgeTooltip(static::getNavigationBadgeTooltip())
                ->sort(static::getNavigationSort())
                ->url(static::getNavigationUrl())
                ->extraAttributes([
                    'class' => 'fi-tdev-agency-nav-item',
                ]),
        ];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchSelectColumns(): array
    {
        return ['id', 'name', 'identification_number', 'email', 'phone'];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchTextColumns(): array
    {
        return ['name', 'email', 'representative_name'];
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
        return ['identification_number'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof TdevAgency) {
            return [];
        }

        return [
            'ID' => filled($record->identification_number) ? (string) $record->identification_number : '—',
            'Email' => filled($record->email) ? (string) $record->email : '—',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return TdevAgencyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TdevAgencyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TdevAgenciesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AgentsRelationManager::class,
            ChildAgenciesRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            TdevAgencyStatsOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTdevAgencies::route('/'),
            'create' => CreateTdevAgency::route('/create'),
            'view' => ViewTdevAgency::route('/{record}'),
            'edit' => EditTdevAgency::route('/{record}/edit'),
        ];
    }
}
