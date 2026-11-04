<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\QualityControlSurveys;

use App\Enums\QualityControlAffiliateType;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\QualityControlSurveys\Schemas\QualityControlSurveyForm;
use App\Filament\Operations\Resources\QualityControlSurveys\Schemas\QualityControlSurveyInfolist;
use App\Filament\Operations\Resources\QualityControlSurveys\Tables\QualityControlSurveysTable;
use App\Models\QualityControlSurvey;
use App\Support\Filament\BusinessFilamentActionAccess;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Base de «Quality Control Individuales» y «Quality Control Corporativos»: misma tabla,
 * separada por `affiliate_type`.
 *
 * - Ver y registrar: permiso de menú de cada ítem.
 * - Editar y eliminar: además, el permiso de acción `gestionar-quality-control-*`.
 */
abstract class QualityControlSurveyResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = QualityControlSurvey::class;

    protected static string|UnitEnum|null $navigationGroup = 'AFILIADOS';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $modelLabel = 'encuesta de Quality Control';

    protected static ?string $pluralModelLabel = 'encuestas de Quality Control';

    protected static ?string $recordTitleAttribute = 'patient_name';

    protected static bool $isGloballySearchable = false;

    abstract public static function affiliateType(): QualityControlAffiliateType;

    /**
     * Slug del permiso de acción que habilita editar y eliminar.
     */
    abstract public static function manageActionSlug(): string;

    public static function getEloquentQuery(): Builder
    {
        $relation = static::affiliateType() === QualityControlAffiliateType::Individual
            ? 'individualAffiliate:id,full_name,status,affiliation_id'
            : 'corporateAffiliate:id,first_name,last_name,status,affiliation_corporate_id';

        return parent::getEloquentQuery()
            ->ofType(static::affiliateType())
            ->with($relation);
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess() && static::belongsHere($record);
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManage() && static::belongsHere($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canManage() && static::belongsHere($record);
    }

    public static function canDeleteAny(): bool
    {
        return static::canManage();
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }

    /**
     * Memorizado por petición, usuario y panel: la tabla lo evalúa en cada fila.
     */
    public static function canManage(): bool
    {
        $userId = Auth::id();

        if ($userId === null) {
            return false;
        }

        $panelId = Filament::getCurrentPanel()?->getId();
        $slug = static::manageActionSlug();

        /** `once()` memoriza por las variables capturadas: usuario, panel y permiso van en `use`. */
        return once(static function () use ($userId, $panelId, $slug): bool {
            unset($userId, $panelId);

            return static::canAccess() && BusinessFilamentActionAccess::userCan($slug);
        });
    }

    public static function form(Schema $schema): Schema
    {
        return QualityControlSurveyForm::configure($schema, static::affiliateType());
    }

    public static function infolist(Schema $schema): Schema
    {
        return QualityControlSurveyInfolist::configure($schema, static::affiliateType());
    }

    public static function table(Table $table): Table
    {
        return QualityControlSurveysTable::configure($table, static::class);
    }

    /**
     * Una encuesta corporativa no se abre desde el ítem de individuales (ni al revés) por URL.
     */
    protected static function belongsHere(Model $record): bool
    {
        return $record instanceof QualityControlSurvey && $record->affiliate_type === static::affiliateType();
    }
}
