<?php

declare(strict_types=1);

namespace App\Filament\Shared\DocumentEmailTemplates;

use App\Enums\DocumentEmailAudience;
use App\Enums\DocumentEmailType;
use App\Models\DocumentEmailTemplate;
use App\Support\DocumentEmails\DocumentEmailTemplateAccess;
use App\Support\DocumentEmails\DocumentEmailTemplateRenderer;
use Closure;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;

/**
 * Formulario de un copy de correo. El documento (recibo, aviso) lo fija la pantalla, no el analista:
 * cada pantalla solo crea y edita copys de su documento.
 */
final class DocumentEmailTemplateFormSchema
{
    /**
     * @param  DocumentEmailType  $document  documento de la pantalla: el que viene elegido por defecto
     */
    public static function configure(Schema $schema, DocumentEmailType $document): Schema
    {
        $selected = fn (Get $get): DocumentEmailType => self::selectedDocument($get, $document);

        return $schema
            ->columns(3)
            ->components([
                Section::make('Copy del correo')
                    ->description('Texto que recibe el destinatario junto con el documento en PDF.')
                    ->columnSpan(['default' => 3, 'lg' => 2])
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])->schema([
                            TextInput::make('name')
                                ->label('Nombre del copy')
                                ->placeholder(fn (Get $get): string => 'Ej.: Envío de '.mb_strtolower($selected($get)->label()).': In House')
                                ->helperText('Es el nombre que el analista ve al elegir el copy.')
                                ->required()
                                ->maxLength(150)
                                ->unique(
                                    ignoreRecord: true,
                                    modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('document', $selected($get)->value),
                                ),
                            Select::make('document')
                                ->label('Se usará en')
                                ->options(fn (?DocumentEmailTemplate $record): array => self::documentOptions($record))
                                ->default($document->value)
                                ->required()
                                ->in(fn (?DocumentEmailTemplate $record): array => array_keys(self::documentOptions($record)))
                                ->native(false)
                                ->selectablePlaceholder(false)
                                ->live()
                                ->afterStateUpdated(function (?string $old, ?string $state, Get $get, Set $set): void {
                                    $previous = DocumentEmailType::tryFrom((string) $old);
                                    $next = DocumentEmailType::tryFrom((string) $state);

                                    if ($previous !== null && $next !== null && trim((string) $get('subject')) === $previous->defaultSubject()) {
                                        $set('subject', $next->defaultSubject());
                                    }
                                })
                                ->helperText(fn (Get $get): string => match ($selected($get)) {
                                    DocumentEmailType::ReceiptPayment => 'Aparece al enviar recibos de pago (Ventas).',
                                    DocumentEmailType::CollectionNotice => 'Aparece al enviar avisos de cobro (Gestión de Cobranza y Cobranza Por Mes).',
                                }),
                            Select::make('audience')
                                ->label('Dirigido a')
                                ->options(DocumentEmailAudience::options())
                                ->helperText('Define en qué selector aparece: el del afiliado, el del agente / agencia o ambos.')
                                ->required()
                                ->native(false),
                        ]),
                        TextInput::make('subject')
                            ->label('Asunto del correo')
                            ->default($document->defaultSubject())
                            ->required()
                            ->maxLength(200)
                            ->rules([self::knownVariablesRule($document)]),
                        RichEditor::make('body')
                            ->label('Cuerpo del correo')
                            ->required()
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'link'],
                                ['bulletList', 'orderedList'],
                                ['undo', 'redo'],
                            ])
                            ->helperText('El encabezado con el logo y el número del documento se agrega solo. Incluya el saludo y la firma.')
                            ->rules([
                                self::knownVariablesRule($document),
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (DocumentEmailTemplateRenderer::plainText(DocumentEmailTemplateRenderer::sanitizeBody((string) $value)) === '') {
                                        $fail('Escriba el texto del correo.');
                                    }
                                },
                            ]),
                    ]),
                Section::make('Uso')
                    ->columnSpan(['default' => 3, 'lg' => 1])
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->helperText('Solo los copys activos se pueden elegir al enviar.')
                            ->default(true)
                            ->live(),
                        Toggle::make('is_default')
                            ->label('Predeterminado')
                            ->helperText('Queda preseleccionado al enviar. Solo hay uno por documento y destinatario: al marcarlo, el anterior deja de serlo.')
                            ->default(false)
                            ->disabled(fn (Get $get): bool => ! $get('is_active'))
                            ->dehydrated(),
                        TextInput::make('sort_order')
                            ->label('Orden en la lista')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(9999)
                            ->default(0),
                        Placeholder::make('variables_help')
                            ->label(fn (Get $get): string => 'Variables disponibles · '.$selected($get)->label())
                            ->content(fn (Get $get): HtmlString => self::variablesHelp($selected($get))),
                    ]),
            ]);
    }

    /**
     * Documentos que el usuario puede elegir: los que tiene permiso de gestionar. Al editar, el
     * documento actual del copy siempre está, para no perderlo aunque el permiso cambie.
     *
     * @return array<string, string>
     */
    public static function documentOptions(?DocumentEmailTemplate $record = null): array
    {
        $options = [];

        foreach (DocumentEmailType::cases() as $case) {
            if (DocumentEmailTemplateAccess::canManage($case) || $record?->document === $case) {
                $options[$case->value] = self::documentLabel($case);
            }
        }

        return $options;
    }

    public static function documentLabel(DocumentEmailType $document): string
    {
        return match ($document) {
            DocumentEmailType::ReceiptPayment => 'Envío de recibos de pago',
            DocumentEmailType::CollectionNotice => 'Envío de avisos de cobro (cobranza)',
        };
    }

    private static function selectedDocument(Get $get, DocumentEmailType $fallback): DocumentEmailType
    {
        $state = $get('document');

        if ($state instanceof DocumentEmailType) {
            return $state;
        }

        return DocumentEmailType::tryFrom((string) $state) ?? $fallback;
    }

    /**
     * Valida contra las variables del documento elegido en el formulario (no el de la pantalla).
     */
    private static function knownVariablesRule(DocumentEmailType $fallback): Closure
    {
        return fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $fallback): void {
            $unknown = DocumentEmailTemplateRenderer::unknownVariables((string) $value, self::selectedDocument($get, $fallback));

            if ($unknown !== []) {
                $fail('Estas variables no existen: {'.implode('}, {', $unknown).'}. Use solo las de la lista de variables disponibles.');
            }
        };
    }

    private static function variablesHelp(DocumentEmailType $document): HtmlString
    {
        $items = '';

        foreach ($document->variables() as $name => $description) {
            $items .= '<li><code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-white/10">{'.e($name).'}</code> '
                .'<span class="text-gray-600 dark:text-gray-400">'.e($description).'</span></li>';
        }

        return new HtmlString(
            '<p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Opcionales. Escríbalas con llaves en el asunto o el cuerpo y se reemplazan con los datos de cada documento.</p>'
            .'<ul class="space-y-1 text-sm">'.$items.'</ul>'
        );
    }
}
