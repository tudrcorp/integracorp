<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\QuoteDesigns\Schemas;

use App\Models\Plan;
use App\Models\QuoteDesign;
use App\Support\Quotes\Design\QuoteDesignTheme;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class QuoteDesignForm
{
    public const UPLOADS_DIRECTORY = QuoteDesign::DIRECTORY.'/uploads';

    public static function configure(Schema $schema): Schema
    {
        $defaults = QuoteDesignTheme::defaultThemeData();

        return $schema->components([
            Tabs::make('Diseño')
                ->columnSpanFull()
                ->persistTabInQueryString('pestana')
                ->tabs([
                    Tab::make('General')
                        ->icon(Heroicon::OutlinedInformationCircle)
                        ->schema(self::generalTab()),
                    Tab::make('Página de cálculos')
                        ->icon(Heroicon::OutlinedCalculator)
                        ->schema(self::calculationPageTab($defaults)),
                    Tab::make('Páginas fijas')
                        ->icon(Heroicon::OutlinedPhoto)
                        ->schema(self::fixedPagesTab()),
                ]),
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function generalTab(): array
    {
        return [
            Section::make('Diseño y planes')
                ->description('Un plan solo puede tener un diseño. Mientras el diseño esté inactivo, sus planes siguen saliendo con la propuesta de siempre.')
                ->icon(Heroicon::OutlinedSwatch)
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre del diseño')
                        ->placeholder('Ej.: Renacer Ecofuneral')
                        ->required()
                        ->maxLength(120),
                    ToggleButtons::make('status')
                        ->label('Estatus')
                        ->helperText('Solo un diseño activo se aplica a las cotizaciones nuevas.')
                        ->options([
                            QuoteDesign::STATUS_ACTIVE => 'Activo',
                            QuoteDesign::STATUS_INACTIVE => 'Inactivo',
                        ])
                        ->colors([
                            QuoteDesign::STATUS_ACTIVE => 'success',
                            QuoteDesign::STATUS_INACTIVE => 'gray',
                        ])
                        ->icons([
                            QuoteDesign::STATUS_ACTIVE => Heroicon::OutlinedCheckCircle,
                            QuoteDesign::STATUS_INACTIVE => Heroicon::OutlinedPauseCircle,
                        ])
                        ->default(QuoteDesign::STATUS_INACTIVE)
                        ->inline()
                        ->required(),
                    Select::make('plans')
                        ->label('Planes que usan este diseño')
                        ->helperText('Las cotizaciones individuales y corporativas de estos planes saldrán con este diseño. Los planes que ya tienen otro diseño aparecen deshabilitados.')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => self::planOptions())
                        ->disableOptionWhen(fn (int|string $value, ?QuoteDesign $record): bool => array_key_exists((int) $value, self::takenPlans($record)))
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->label('Notas internas')
                        ->placeholder('Ej.: Diseño enviado por Marketing el 17/11 para la línea funeraria.')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                    Hidden::make('source_html_path'),
                ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array<int, mixed>
     */
    private static function calculationPageTab(array $defaults): array
    {
        $colorFields = [];

        foreach (QuoteDesignTheme::COLORS as $key => $color) {
            $colorFields[] = ColorPicker::make("theme.{$key}")
                ->label($color['label'])
                ->helperText($color['help'])
                ->default($color['default'])
                ->regex('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/')
                ->required();
        }

        return [
            Section::make('Colores')
                ->description('Con «Importar HTML de diseño» (arriba) se llenan solos desde el archivo que envía diseño. Aquí se ajustan a mano.')
                ->icon(Heroicon::OutlinedSwatch)
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->columnSpanFull()
                ->schema($colorFields),

            Section::make('Logo y tipografía')
                ->description('Sin logo se usa el de Tu Doctor en Casa. Las fuentes del diseño (Montserrat, IBM Plex…) se suben en TTF; si no, se usan las del motor de PDF.')
                ->icon(Heroicon::OutlinedPhoto)
                ->columns(['default' => 1, 'md' => 2])
                ->columnSpanFull()
                ->schema([
                    FileUpload::make('logo_path')
                        ->label('Logo de la cabecera')
                        ->helperText('PNG o JPG horizontal, fondo transparente o del color de la hoja. Hasta 2 MB.')
                        ->image()
                        ->disk('public')
                        ->directory(QuoteDesign::DIRECTORY.'/logos')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg'])
                        ->maxSize(2048)
                        ->imagePreviewHeight('80')
                        ->columnSpanFull(),
                    Select::make('theme.sans_family')
                        ->label('Fuente de textos (si no se sube una)')
                        ->options(QuoteDesignTheme::SANS_FAMILIES)
                        ->default($defaults['sans_family'])
                        ->selectablePlaceholder(false)
                        ->required(),
                    Select::make('theme.mono_family')
                        ->label('Fuente de cifras y números de control (si no se sube una)')
                        ->options(QuoteDesignTheme::MONO_FAMILIES)
                        ->default($defaults['mono_family'])
                        ->selectablePlaceholder(false)
                        ->required(),
                    self::fontUpload('font_regular_path', 'Fuente de textos (TTF)', 'Ej.: Montserrat-Regular.ttf'),
                    self::fontUpload('font_bold_path', 'Fuente de textos en negrita (TTF, opcional)', 'Ej.: Montserrat-SemiBold.ttf. Sin ella, los títulos usan la fuente normal.'),
                    self::fontUpload('font_mono_path', 'Fuente de cifras (TTF, opcional)', 'Ej.: IBMPlexMono-Regular.ttf'),
                ]),

            Section::make('Textos de la página')
                ->description('Los datos del cliente, los beneficios y las tarifas salen siempre de la cotización: aquí solo se cambian los textos fijos.')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->columns(['default' => 1, 'md' => 2])
                ->columnSpanFull()
                ->schema([
                    TextInput::make('theme.overline')
                        ->label('Antetítulo (sobre el nombre del plan)')
                        ->default($defaults['overline'])
                        ->maxLength(60),
                    TextInput::make('theme.benefits_heading')
                        ->label('Encabezado de la tabla de beneficios')
                        ->default($defaults['benefits_heading'])
                        ->maxLength(60)
                        ->required(),
                    TextInput::make('theme.validity_note')
                        ->label('Nota de vigencia')
                        ->default($defaults['validity_note'])
                        ->maxLength(200)
                        ->required()
                        ->columnSpanFull(),
                    Toggle::make('theme.show_note')
                        ->label('Mostrar nota bajo los beneficios')
                        ->helperText('Para planes de salud: la nota de enfermedades agudas. En planes como el funerario conviene apagarla.')
                        ->default($defaults['show_note'])
                        ->live()
                        ->columnSpanFull(),
                    TextInput::make('theme.note_title')
                        ->label('Título de la nota')
                        ->default($defaults['note_title'])
                        ->maxLength(120)
                        ->visible(fn (Get $get): bool => (bool) $get('theme.show_note')),
                    Textarea::make('theme.note_body')
                        ->label('Texto de la nota')
                        ->default($defaults['note_body'])
                        ->rows(2)
                        ->maxLength(600)
                        ->visible(fn (Get $get): bool => (bool) $get('theme.show_note')),
                    Textarea::make('theme.conditions')
                        ->label('Condiciones (una por línea)')
                        ->helperText('Van al final de la página con una viñeta del color de acento. Máximo '.QuoteDesignTheme::MAX_CONDITIONS.' líneas; deje vacío para no mostrar condiciones.')
                        ->default(implode("\n", $defaults['conditions']))
                        ->formatStateUsing(fn (mixed $state): string => self::conditionsToText($state))
                        ->dehydrateStateUsing(fn (mixed $state): array => self::textToConditions($state))
                        ->rows(6)
                        ->columnSpanFull(),
                    TextInput::make('theme.footer_left')
                        ->label('Pie de página, izquierda')
                        ->placeholder('Ej.: Asistencia Funeraria · Venezuela')
                        ->default($defaults['footer_left'])
                        ->maxLength(120),
                    Grid::make(2)
                        ->schema([
                            TextInput::make('theme.footer_right_prefix')
                                ->label('Pie, derecha (texto)')
                                ->default($defaults['footer_right_prefix'])
                                ->maxLength(60),
                            TextInput::make('theme.footer_right_brand')
                                ->label('Pie, derecha (marca)')
                                ->default($defaults['footer_right_brand'])
                                ->maxLength(60),
                        ]),
                ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function fixedPagesTab(): array
    {
        return [
            Section::make('Páginas fijas')
                ->description('Imágenes de página completa (portada, «acerca de», contraportada…). Cada imagen es una hoja A4 vertical: se recomienda 1654 × 2339 px. Si la proporción no es A4 (p. ej. tamaño carta) se recorta centrada, sin deformarla. Si una lista queda vacía se usan las páginas de siempre. Arrastre para cambiar el orden.')
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->columns(['default' => 1, 'lg' => 2])
                ->columnSpanFull()
                ->schema([
                    self::pagesUpload('pages_before', 'Antes de los cálculos', 'Ej.: 1) portada, 2) acerca de.'),
                    self::pagesUpload('pages_after', 'Después de los cálculos', 'Ej.: contraportada.'),
                ]),
        ];
    }

    private static function pagesUpload(string $name, string $label, string $hint): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->helperText($hint.' PNG o JPG, hasta '.QuoteDesignTheme::MAX_FIXED_PAGES.' páginas de 10 MB cada una.')
            ->image()
            ->multiple()
            ->reorderable()
            ->appendFiles()
            ->maxFiles(QuoteDesignTheme::MAX_FIXED_PAGES)
            ->disk('public')
            ->directory(self::UPLOADS_DIRECTORY)
            ->visibility('public')
            ->acceptedFileTypes(['image/png', 'image/jpeg'])
            ->maxSize(10240)
            ->panelLayout('grid')
            ->imagePreviewHeight('220');
    }

    private static function fontUpload(string $name, string $label, string $hint): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->helperText($hint)
            ->disk('public')
            ->directory(QuoteDesign::DIRECTORY.'/fonts')
            ->visibility('public')
            ->acceptedFileTypes(['font/ttf', 'font/sfnt', 'application/x-font-ttf', 'application/x-font-truetype', 'application/font-sfnt', 'application/octet-stream'])
            ->maxSize(4096);
    }

    /**
     * @return array<int, string>
     */
    private static function planOptions(): array
    {
        return Plan::query()
            ->orderBy('description')
            ->get(['id', 'description', 'status'])
            ->mapWithKeys(fn (Plan $plan): array => [
                (int) $plan->getKey() => (string) $plan->description.($plan->status !== 'ACTIVO' ? ' (inactivo)' : ''),
            ])
            ->all();
    }

    /**
     * Planes que ya usa otro diseño, con el nombre de ese diseño.
     *
     * @return array<int, string>
     */
    public static function takenPlans(?QuoteDesign $record): array
    {
        return once(fn (): array => DB::table('quote_design_plan')
            ->join('quote_designs', 'quote_designs.id', '=', 'quote_design_plan.quote_design_id')
            ->when($record?->exists, fn ($query) => $query->where('quote_design_plan.quote_design_id', '!=', $record->getKey()))
            ->pluck('quote_designs.name', 'quote_design_plan.plan_id')
            ->mapWithKeys(fn (string $name, int|string $planId): array => [(int) $planId => $name])
            ->all());
    }

    public static function conditionsToText(mixed $state): string
    {
        if (is_string($state)) {
            return $state;
        }

        if (! is_array($state)) {
            return '';
        }

        return implode("\n", array_map(
            static fn (mixed $condition): string => is_array($condition) ? (string) ($condition['text'] ?? '') : (string) $condition,
            $state,
        ));
    }

    /**
     * @return list<string>
     */
    public static function textToConditions(mixed $state): array
    {
        if (is_array($state)) {
            $state = self::conditionsToText($state);
        }

        $lines = preg_split('/\R/u', (string) $state) ?: [];

        return array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
    }
}
