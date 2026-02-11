<?php

namespace App\Filament\Business\Resources\Affiliations;

use App\Filament\Business\Resources\Affiliations\Pages\CreateAffiliation;
use App\Filament\Business\Resources\Affiliations\Pages\EditAffiliation;
use App\Filament\Business\Resources\Affiliations\Pages\ListAffiliations;
use App\Filament\Business\Resources\Affiliations\Pages\ViewAffiliation;
use App\Filament\Business\Resources\Affiliations\RelationManagers\AffiliatesRelationManager;
use App\Filament\Business\Resources\Affiliations\RelationManagers\PaidMembershipsRelationManager;
use App\Filament\Business\Resources\Affiliations\Schemas\AffiliationForm;
use App\Filament\Business\Resources\Affiliations\Schemas\AffiliationInfolist;
use App\Filament\Business\Resources\Affiliations\Tables\AffiliationsTable;
use App\Models\Affiliation;
use BackedEnum;
use Carbon\Carbon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class AffiliationResource extends Resource
{
    protected static ?string $model = Affiliation::class;

    protected static ?string $navigationLabel = 'Individuales';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static string | UnitEnum | null $navigationGroup = 'AFILIACIONES';

    /**
     * Muestra un badge con la palabra NEW y el conteo de afiliados
     * con estatus 'ACTIVA' registrados el día de hoy.
     */
    public static function getNavigationBadge(): ?string
    {
        $todayCount = static::getModel()::where('status', 'ACTIVA')
            ->whereDate('created_at', Carbon::today())
            ->count();

        return $todayCount > 0 ? "NUEVO {$todayCount}" : null;
    }

    /**
     * Color personalizado para el badge (Verde iOS).
     */
    public static function getNavigationBadgeColor(): ?string
    {

        return 'verdeApple';
    }

    public static function form(Schema $schema): Schema
    {
        return AffiliationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AffiliatesRelationManager::class,
            PaidMembershipsRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliations::route('/'),
            'create' => CreateAffiliation::route('/create'),
            'view' => ViewAffiliation::route('/{record}'),
            'edit' => EditAffiliation::route('/{record}/edit'),
        ];
    }
}