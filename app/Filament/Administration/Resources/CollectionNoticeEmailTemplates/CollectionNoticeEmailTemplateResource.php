<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\CollectionNoticeEmailTemplates;

use App\Enums\DocumentEmailType;
use App\Filament\Administration\Resources\CollectionNoticeEmailTemplates\Pages\CreateCollectionNoticeEmailTemplate;
use App\Filament\Administration\Resources\CollectionNoticeEmailTemplates\Pages\EditCollectionNoticeEmailTemplate;
use App\Filament\Administration\Resources\CollectionNoticeEmailTemplates\Pages\ListCollectionNoticeEmailTemplates;
use App\Filament\Administration\Resources\CollectionNoticeEmailTemplates\Pages\ViewCollectionNoticeEmailTemplate;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Shared\DocumentEmailTemplates\DocumentEmailTemplateFormSchema;
use App\Filament\Shared\DocumentEmailTemplates\DocumentEmailTemplateInfolistSchema;
use App\Filament\Shared\DocumentEmailTemplates\DocumentEmailTemplatesTableSchema;
use App\Models\DocumentEmailTemplate;
use App\Support\DocumentEmails\DocumentEmailTemplateAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Copys del correo del aviso de cobro que el analista elige en Gestión de Cobranza y Cobranza Por Mes.
 *
 * Verlos exige el permiso de menú `copys-aviso-de-cobro`; crear, editar, activar y desactivar
 * exigen además la acción `gestionar-copys-aviso-de-cobro`. No se eliminan: se desactivan.
 */
class CollectionNoticeEmailTemplateResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    public const DOCUMENT = DocumentEmailType::CollectionNotice;

    protected static ?string $model = DocumentEmailTemplate::class;

    protected static ?string $slug = 'copys-aviso-de-cobro';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACIÓN';

    protected static ?string $navigationLabel = 'Copys de avisos de cobro';

    protected static ?string $modelLabel = 'copy de aviso de cobro';

    protected static ?string $pluralModelLabel = 'copys de avisos de cobro';

    /**
     * @return Builder<DocumentEmailTemplate>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forDocument(self::DOCUMENT);
    }

    public static function form(Schema $schema): Schema
    {
        return DocumentEmailTemplateFormSchema::configure($schema, self::DOCUMENT);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DocumentEmailTemplateInfolistSchema::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentEmailTemplatesTableSchema::configure($table, self::DOCUMENT);
    }

    public static function canCreate(): bool
    {
        return static::canAccess() && DocumentEmailTemplateAccess::canManage(self::DOCUMENT);
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess()
            && DocumentEmailTemplateAccess::canManage(self::DOCUMENT)
            && $record instanceof DocumentEmailTemplate
            && $record->document === self::DOCUMENT;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCollectionNoticeEmailTemplates::route('/'),
            'create' => CreateCollectionNoticeEmailTemplate::route('/create'),
            'view' => ViewCollectionNoticeEmailTemplate::route('/{record}'),
            'edit' => EditCollectionNoticeEmailTemplate::route('/{record}/edit'),
        ];
    }
}
