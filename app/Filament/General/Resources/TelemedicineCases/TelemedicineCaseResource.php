<?php

declare(strict_types=1);

namespace App\Filament\General\Resources\TelemedicineCases;

use App\Filament\Concerns\AuthorizesCommercialNetworkCases;
use App\Filament\General\Resources\TelemedicineCases\Pages\ListTelemedicineCases;
use App\Filament\General\Resources\TelemedicineCases\Pages\ViewTelemedicineCase;
use App\Filament\Shared\CommercialTelemedicine\CommercialTelemedicinePanel;
use App\Filament\Shared\CommercialTelemedicine\RelationManagers\CommercialTelemedicineConsultationsRelationManager;
use App\Filament\Shared\CommercialTelemedicine\Schemas\CommercialTelemedicineCaseInfolist;
use App\Filament\Shared\CommercialTelemedicine\Tables\CommercialTelemedicineCasesTable;
use App\Models\TelemedicineCase;
use App\Support\Filament\CommercialNetworkTelemedicineScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TelemedicineCaseResource extends Resource
{
    use AuthorizesCommercialNetworkCases;

    protected static ?string $model = TelemedicineCase::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'SEGUIMIENTO';

    protected static ?string $navigationLabel = 'Gestión de casos';

    protected static ?string $modelLabel = 'caso';

    protected static ?string $pluralModelLabel = 'casos';

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return CommercialTelemedicineCaseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommercialTelemedicineCasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CommercialTelemedicineConsultationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelemedicineCases::route('/'),
            'view' => ViewTelemedicineCase::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return CommercialNetworkTelemedicineScope::applyToCases(
            parent::getEloquentQuery()->with([
                CommercialNetworkTelemedicineScope::patientCaseRelationEagerLoad(),
                'telemedicineDoctor:id,full_name',
                'priority:id,name',
            ]),
            CommercialTelemedicinePanel::authenticatedUser(),
            excludeDischarged: true,
        );
    }
}
