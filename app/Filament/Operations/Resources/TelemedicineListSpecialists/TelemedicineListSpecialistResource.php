<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineListSpecialists;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\TelemedicineClinicalCatalogs\Actions\DeleteTelemedicineClinicalCatalogAction;
use App\Filament\Operations\Resources\TelemedicineListSpecialists\Pages\CreateTelemedicineListSpecialist;
use App\Filament\Operations\Resources\TelemedicineListSpecialists\Pages\EditTelemedicineListSpecialist;
use App\Filament\Operations\Resources\TelemedicineListSpecialists\Pages\ListTelemedicineListSpecialists;
use App\Models\TelemedicineListSpecialist;
use App\Support\Filament\Operations\TelemedicineClinicalCatalogForm;
use App\Support\Filament\Operations\TelemedicineClinicalCatalogTable;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TelemedicineListSpecialistResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = TelemedicineListSpecialist::class;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACION';

    protected static ?string $navigationLabel = 'Lista de Especialistas';

    protected static ?string $modelLabel = 'especialista';

    protected static ?string $pluralModelLabel = 'Especialistas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 29;

    public static function form(Schema $schema): Schema
    {
        return TelemedicineClinicalCatalogForm::configure(
            $schema,
            TelemedicineListSpecialist::class,
            'Nombre del especialista',
            'Ej. CARDIOLOGÍA, PEDIATRÍA',
            'Datos del especialista',
            'Este nombre aparece cuando el médico indica interconsulta con especialista.',
            'heroicon-m-user-group',
        );
    }

    public static function table(Table $table): Table
    {
        return TelemedicineClinicalCatalogTable::configure(
            $table,
            'Lista de Especialistas',
            'Catálogo que el médico elige al indicar consulta con especialista.',
            self::deleteAction(),
            'heroicon-o-user-group',
            'Sin especialistas',
            'Cree el primer especialista para que el médico pueda indicarlo en la consulta.',
            'Buscar especialista…',
        );
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteTelemedicineClinicalCatalogAction::make(
            'especialista',
            'AUDIT_OPERATIONS_TELEMEDICINE_LIST_SPECIALIST_DELETED',
            'operations.telemedicine-list-specialists.delete',
        );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelemedicineListSpecialists::route('/'),
            'create' => CreateTelemedicineListSpecialist::route('/create'),
            'edit' => EditTelemedicineListSpecialist::route('/{record}/edit'),
        ];
    }
}
