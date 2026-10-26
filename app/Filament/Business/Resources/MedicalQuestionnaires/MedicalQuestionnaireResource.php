<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\MedicalQuestionnaires;

use App\Filament\Business\Resources\MedicalQuestionnaires\Pages\CreateMedicalQuestionnaire;
use App\Filament\Business\Resources\MedicalQuestionnaires\Pages\EditMedicalQuestionnaire;
use App\Filament\Business\Resources\MedicalQuestionnaires\Pages\ListMedicalQuestionnaires;
use App\Filament\Business\Resources\MedicalQuestionnaires\Schemas\MedicalQuestionnaireForm;
use App\Filament\Business\Resources\MedicalQuestionnaires\Tables\MedicalQuestionnairesTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\MedicalQuestionnaire;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Cuestionarios médicos por plan: las preguntas Sí/No que ven todos los formularios de afiliación.
 * Ver y editar exige el permiso de menú `cuestionarios-medicos` de Negocios.
 */
class MedicalQuestionnaireResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = MedicalQuestionnaire::class;

    protected static ?string $navigationLabel = 'Cuestionarios médicos';

    protected static ?string $modelLabel = 'cuestionario médico';

    protected static ?string $pluralModelLabel = 'cuestionarios médicos';

    protected static ?string $slug = 'cuestionarios-medicos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    public static function canEdit(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return MedicalQuestionnaireForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MedicalQuestionnairesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedicalQuestionnaires::route('/'),
            'create' => CreateMedicalQuestionnaire::route('/create'),
            'edit' => EditMedicalQuestionnaire::route('/{record}/edit'),
        ];
    }
}
