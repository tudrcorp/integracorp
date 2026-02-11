<?php

namespace App\Filament\Business\Resources\AffiliationCorporates;

use App\Filament\Business\Resources\AffiliationCorporates\Pages\CreateAffiliationCorporate;
use App\Filament\Business\Resources\AffiliationCorporates\Pages\EditAffiliationCorporate;
use App\Filament\Business\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates;
use App\Filament\Business\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate;
use App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\AffiliationCorporatePlansRelationManager;
use App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\CorporateAffiliatesRelationManager;
use App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\PaidMembershipCorporatesRelationManager;
use App\Filament\Business\Resources\AffiliationCorporates\Schemas\AffiliationCorporateForm;
use App\Filament\Business\Resources\AffiliationCorporates\Schemas\AffiliationCorporateInfolist;
use App\Filament\Business\Resources\AffiliationCorporates\Tables\AffiliationCorporatesTable;
use App\Models\AffiliationCorporate;
use BackedEnum;
use Carbon\Carbon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AffiliationCorporateResource extends Resource
{
    protected static ?string $model = AffiliationCorporate::class;

    protected static ?string $navigationLabel = 'Corporativas';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

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
        return AffiliationCorporateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliationCorporateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliationCorporatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AffiliationCorporatePlansRelationManager::class,
            CorporateAffiliatesRelationManager::class,
            PaidMembershipCorporatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliationCorporates::route('/'),
            'create' => CreateAffiliationCorporate::route('/create'),
            'view' => ViewAffiliationCorporate::route('/{record}'),
            'edit' => EditAffiliationCorporate::route('/{record}/edit'),
        ];
    }
}