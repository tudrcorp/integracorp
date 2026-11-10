<?php

declare(strict_types=1);

namespace App\Filament\Shared\DocumentEmailTemplates;

use App\Enums\DocumentEmailAudience;
use App\Enums\DocumentEmailType;
use App\Models\DocumentEmailTemplate;
use App\Support\DocumentEmails\DocumentEmailTemplateRenderer;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Ficha de un copy: sus datos, cómo se ve el correo con datos de ejemplo y su uso.
 */
final class DocumentEmailTemplateInfolistSchema
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('copy_tabs')
                    ->columnSpanFull()
                    ->persistTabInQueryString('pestana')
                    ->tabs([
                        Tab::make('Copy del correo')
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 3])->schema([
                                    TextEntry::make('name')
                                        ->label('Nombre del copy')
                                        ->weight('semibold'),
                                    TextEntry::make('document')
                                        ->label('Se usará en')
                                        ->badge()
                                        ->color('primary')
                                        ->formatStateUsing(fn (DocumentEmailType $state): string => DocumentEmailTemplateFormSchema::documentLabel($state)),
                                    TextEntry::make('audience')
                                        ->label('Dirigido a')
                                        ->badge()
                                        ->formatStateUsing(fn (DocumentEmailAudience $state): string => $state->label())
                                        ->color(fn (DocumentEmailAudience $state): string => $state->color()),
                                ]),
                                TextEntry::make('subject')
                                    ->label('Asunto (con variables)')
                                    ->copyable()
                                    ->copyMessage('Asunto copiado'),
                            ]),
                        Tab::make('Así se ve el correo')
                            ->icon('heroicon-o-eye')
                            ->schema([
                                TextEntry::make('preview_hint')
                                    ->hiddenLabel()
                                    ->state('Con datos de ejemplo. En cada envío se usan los datos reales del documento, y el PDF va adjunto.')
                                    ->color('gray'),
                                TextEntry::make('rendered_subject')
                                    ->label('Asunto')
                                    ->state(fn (DocumentEmailTemplate $record): string => self::rendered($record)['subject'])
                                    ->weight('semibold'),
                                TextEntry::make('rendered_body')
                                    ->label('Cuerpo')
                                    ->state(fn (DocumentEmailTemplate $record): HtmlString => new HtmlString(self::rendered($record)['body_html']))
                                    ->html(),
                            ]),
                        Tab::make('Uso')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->schema([
                                Grid::make(['default' => 2, 'md' => 3])->schema([
                                    IconEntry::make('is_active')
                                        ->label('Activo')
                                        ->boolean(),
                                    IconEntry::make('is_default')
                                        ->label('Predeterminado')
                                        ->boolean(),
                                    TextEntry::make('sort_order')
                                        ->label('Orden en la lista'),
                                ]),
                                Grid::make(['default' => 1, 'md' => 2])->schema([
                                    TextEntry::make('created_by')
                                        ->label('Creado por')
                                        ->placeholder('—')
                                        ->helperText(fn (DocumentEmailTemplate $record): ?string => $record->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i')),
                                    TextEntry::make('updated_by')
                                        ->label('Última edición por')
                                        ->placeholder('—')
                                        ->helperText(fn (DocumentEmailTemplate $record): ?string => $record->updated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i')),
                                ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * @return array{subject: string, body_html: string}
     */
    private static function rendered(DocumentEmailTemplate $record): array
    {
        return DocumentEmailTemplateRenderer::render($record, $record->document->sampleValues());
    }
}
