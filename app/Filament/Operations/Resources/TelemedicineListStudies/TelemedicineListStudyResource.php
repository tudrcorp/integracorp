<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineListStudies;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\TelemedicineClinicalCatalogs\Actions\DeleteTelemedicineClinicalCatalogAction;
use App\Filament\Operations\Resources\TelemedicineListStudies\Pages\CreateTelemedicineListStudy;
use App\Filament\Operations\Resources\TelemedicineListStudies\Pages\EditTelemedicineListStudy;
use App\Filament\Operations\Resources\TelemedicineListStudies\Pages\ListTelemedicineListStudies;
use App\Models\TelemedicineListStudy;
use App\Support\Filament\Operations\TelemedicineClinicalCatalogForm;
use App\Support\Filament\Operations\TelemedicineClinicalCatalogTable;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TelemedicineListStudyResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = TelemedicineListStudy::class;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACION';

    protected static ?string $navigationLabel = 'Lista de Estudios';

    protected static ?string $modelLabel = 'estudio';

    protected static ?string $pluralModelLabel = 'Estudios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCamera;

    protected static ?int $navigationSort = 28;

    public static function form(Schema $schema): Schema
    {
        return TelemedicineClinicalCatalogForm::configure(
            $schema,
            TelemedicineListStudy::class,
            'Nombre del estudio',
            'Ej. RX TÓRAX, ECOGRAFÍA ABDOMINAL',
            'Datos del estudio',
            'Este nombre aparece cuando el médico indica estudios de imagenología en la consulta.',
            'heroicon-m-camera',
        );
    }

    public static function table(Table $table): Table
    {
        return TelemedicineClinicalCatalogTable::configure(
            $table,
            'Lista de Estudios',
            'Catálogo que el médico elige al indicar estudios de imagenología en la consulta.',
            self::deleteAction(),
            'heroicon-o-camera',
            'Sin estudios',
            'Cree el primer estudio para que el médico pueda indicarlo en la consulta.',
            'Buscar estudio…',
        );
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteTelemedicineClinicalCatalogAction::make(
            'estudio',
            'AUDIT_OPERATIONS_TELEMEDICINE_LIST_STUDY_DELETED',
            'operations.telemedicine-list-studies.delete',
        );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelemedicineListStudies::route('/'),
            'create' => CreateTelemedicineListStudy::route('/create'),
            'edit' => EditTelemedicineListStudy::route('/{record}/edit'),
        ];
    }
}
