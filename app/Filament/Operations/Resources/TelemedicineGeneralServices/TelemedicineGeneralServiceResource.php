<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineGeneralServices;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\TelemedicineGeneralServices\Pages\CreateTelemedicineGeneralService;
use App\Filament\Operations\Resources\TelemedicineGeneralServices\Pages\EditTelemedicineGeneralService;
use App\Filament\Operations\Resources\TelemedicineGeneralServices\Pages\ListTelemedicineGeneralServices;
use App\Filament\Operations\Resources\TelemedicineGeneralServices\Schemas\TelemedicineGeneralServiceForm;
use App\Filament\Operations\Resources\TelemedicineGeneralServices\Tables\TelemedicineGeneralServicesTable;
use App\Models\TelemedicineGeneralService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TelemedicineGeneralServiceResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = TelemedicineGeneralService::class;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACION';

    protected static ?string $navigationLabel = 'Servicios Consulta General';

    protected static ?string $modelLabel = 'Servicio de Consulta General';

    protected static ?string $pluralModelLabel = 'Servicios de Consulta General';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 25;

    public static function form(Schema $schema): Schema
    {
        return TelemedicineGeneralServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TelemedicineGeneralServicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelemedicineGeneralServices::route('/'),
            'create' => CreateTelemedicineGeneralService::route('/create'),
            'edit' => EditTelemedicineGeneralService::route('/{record}/edit'),
        ];
    }
}
