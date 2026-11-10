<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\SaleReceiptEmailTemplates;

use App\Enums\DocumentEmailType;
use App\Filament\Administration\Resources\SaleReceiptEmailTemplates\Pages\CreateSaleReceiptEmailTemplate;
use App\Filament\Administration\Resources\SaleReceiptEmailTemplates\Pages\EditSaleReceiptEmailTemplate;
use App\Filament\Administration\Resources\SaleReceiptEmailTemplates\Pages\ListSaleReceiptEmailTemplates;
use App\Filament\Administration\Resources\SaleReceiptEmailTemplates\Pages\ViewSaleReceiptEmailTemplate;
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
 * Copys del correo del recibo de pago que el analista elige en Ventas.
 *
 * Verlos exige el permiso de menú `copys-recibo-de-pago`; crear, editar, activar y desactivar
 * exigen además la acción `gestionar-copys-recibo-de-pago`. No se eliminan: se desactivan.
 */
class SaleReceiptEmailTemplateResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    public const DOCUMENT = DocumentEmailType::ReceiptPayment;

    protected static ?string $model = DocumentEmailTemplate::class;

    protected static ?string $slug = 'copys-recibo-de-pago';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACIÓN';

    protected static ?string $navigationLabel = 'Copys de recibos';

    protected static ?string $modelLabel = 'copy de recibo';

    protected static ?string $pluralModelLabel = 'copys de recibos';

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
            'index' => ListSaleReceiptEmailTemplates::route('/'),
            'create' => CreateSaleReceiptEmailTemplate::route('/create'),
            'view' => ViewSaleReceiptEmailTemplate::route('/{record}'),
            'edit' => EditSaleReceiptEmailTemplate::route('/{record}/edit'),
        ];
    }
}
