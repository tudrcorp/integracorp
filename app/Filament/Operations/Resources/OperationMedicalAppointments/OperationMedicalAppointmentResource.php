<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationMedicalAppointments;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\OperationMedicalAppointments\Pages\ManageOperationMedicalAppointments;
use App\Filament\Operations\Resources\OperationMedicalAppointments\Tables\OperationMedicalAppointmentsTable;
use App\Models\OperationMedicalAppointment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class OperationMedicalAppointmentResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = OperationMedicalAppointment::class;

    protected static ?string $navigationLabel = 'Citas Médicas';

    protected static ?string $modelLabel = 'cita médica';

    protected static ?string $pluralModelLabel = 'citas médicas';

    protected static ?string $slug = 'citas-medicas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'COORDINACIÓN DE SERVICIOS';

    protected static ?int $navigationSort = 18;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return OperationMedicalAppointmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOperationMedicalAppointments::route('/'),
        ];
    }
}
