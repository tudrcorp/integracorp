<?php

declare(strict_types=1);

namespace App\Filament\Agents\Resources\TelemedicinePatients;

use App\Filament\Agents\Resources\TelemedicinePatients\Pages\ListTelemedicinePatients;
use App\Filament\Agents\Resources\TelemedicinePatients\Pages\ViewTelemedicinePatient;
use App\Filament\Concerns\AuthorizesCommercialNetworkPatients;
use App\Filament\Shared\CommercialTelemedicine\CommercialTelemedicinePanel;
use App\Filament\Shared\CommercialTelemedicine\RelationManagers\CommercialTelemedicineCasesRelationManager;
use App\Filament\Shared\CommercialTelemedicine\Schemas\CommercialTelemedicinePatientInfolist;
use App\Filament\Shared\CommercialTelemedicine\Tables\CommercialTelemedicinePatientsTable;
use App\Models\TelemedicinePatient;
use App\Support\Filament\CommercialNetworkTelemedicineScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TelemedicinePatientResource extends Resource
{
    use AuthorizesCommercialNetworkPatients;

    protected static ?string $model = TelemedicinePatient::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static string|UnitEnum|null $navigationGroup = 'SEGUIMIENTO';

    protected static ?string $navigationLabel = 'Pacientes';

    protected static ?string $modelLabel = 'paciente';

    protected static ?string $pluralModelLabel = 'pacientes';

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return CommercialTelemedicinePatientInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommercialTelemedicinePatientsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CommercialTelemedicineCasesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelemedicinePatients::route('/'),
            'view' => ViewTelemedicinePatient::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return CommercialNetworkTelemedicineScope::applyToPatients(
            parent::getEloquentQuery(),
            CommercialTelemedicinePanel::authenticatedUser(),
        );
    }
}
