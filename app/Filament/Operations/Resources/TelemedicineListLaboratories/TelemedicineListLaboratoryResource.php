<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineListLaboratories;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\TelemedicineClinicalCatalogs\Actions\DeleteTelemedicineClinicalCatalogAction;
use App\Filament\Operations\Resources\TelemedicineListLaboratories\Pages\CreateTelemedicineListLaboratory;
use App\Filament\Operations\Resources\TelemedicineListLaboratories\Pages\EditTelemedicineListLaboratory;
use App\Filament\Operations\Resources\TelemedicineListLaboratories\Pages\ListTelemedicineListLaboratories;
use App\Models\TelemedicineListLaboratory;
use App\Support\Filament\Operations\TelemedicineClinicalCatalogForm;
use App\Support\Filament\Operations\TelemedicineClinicalCatalogTable;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TelemedicineListLaboratoryResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = TelemedicineListLaboratory::class;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACION';

    protected static ?string $navigationLabel = 'Lista de Laboratorios';

    protected static ?string $modelLabel = 'laboratorio';

    protected static ?string $pluralModelLabel = 'Laboratorios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?int $navigationSort = 27;

    public static function form(Schema $schema): Schema
    {
        return TelemedicineClinicalCatalogForm::configure(
            $schema,
            TelemedicineListLaboratory::class,
            'Nombre del laboratorio',
            'Ej. HEMOGRAMA, GLICEMIA',
            'Datos del laboratorio',
            'Este nombre aparece cuando el médico indica laboratorios en la consulta.',
            'heroicon-m-beaker',
        );
    }

    public static function table(Table $table): Table
    {
        return TelemedicineClinicalCatalogTable::configure(
            $table,
            'Lista de Laboratorios',
            'Catálogo que el médico elige al indicar laboratorios en la consulta.',
            self::deleteAction(),
            'heroicon-o-beaker',
            'Sin laboratorios',
            'Cree el primer laboratorio para que el médico pueda indicarlo en la consulta.',
            'Buscar laboratorio…',
        );
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteTelemedicineClinicalCatalogAction::make(
            'laboratorio',
            'AUDIT_OPERATIONS_TELEMEDICINE_LIST_LABORATORY_DELETED',
            'operations.telemedicine-list-laboratories.delete',
        );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelemedicineListLaboratories::route('/'),
            'create' => CreateTelemedicineListLaboratory::route('/create'),
            'edit' => EditTelemedicineListLaboratory::route('/{record}/edit'),
        ];
    }
}
