<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Support\Filament\FilamentIosButton;
use App\Support\SecurityAudit;
use App\Support\TuDrQuote\QuoteDocumentLayout;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Composición del PDF de la Propuesta Económica.
 *
 * El microservicio de cotización entrega las páginas de tarifas; el portal
 * pone las institucionales y decide el orden. Aquí el SUPERADMIN ajusta
 * cuántas hojas tiene el documento y en qué página caen los cálculos, sin
 * tocar código ni volver a desplegar.
 */
class ManageQuoteDocumentLayout extends Page
{
    protected static ?string $navigationLabel = 'Formato de la cotización';

    protected static ?string $title = 'Formato de la cotización';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    protected static ?int $navigationSort = 18;

    protected string $view = 'filament.business.pages.manage-quote-document-layout';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->fillForm();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString(
            'Define cuántas hojas tiene la Propuesta Económica y en qué página se ubican los cálculos. '
            .'Aplica al documento que se entrega al cliente en cotizaciones individuales y corporativas.'
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->scopeSection(
                    QuoteDocumentLayout::SCOPE_INDIVIDUAL,
                    'Documento que se entrega en las cotizaciones individuales.',
                ),
                $this->scopeSection(
                    QuoteDocumentLayout::SCOPE_CORPORATE,
                    'Documento que se entrega en las cotizaciones corporativas, con la población agrupada por rango de edad.',
                ),
            ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Form
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('manage-quote-document-layout-form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->fullWidth(false)
                    ->sticky(),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar formato')
                ->icon('heroicon-o-check')
                ->submit('save')
                ->color('success')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                ]),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $guardado = [];

        foreach (QuoteDocumentLayout::scopes() as $scope) {
            $guardado[$scope] = QuoteDocumentLayout::save(
                $scope,
                (int) ($data[$scope]['total_pages'] ?? QuoteDocumentLayout::DEFAULT_TOTAL_PAGES),
                (int) ($data[$scope]['calculations_page'] ?? QuoteDocumentLayout::DEFAULT_CALCULATIONS_PAGE),
            );
        }

        SecurityAudit::log(
            'AUDIT_BUSINESS_QUOTE_DOCUMENT_LAYOUT_UPDATED',
            'business.quote-document-layout.settings',
            $guardado,
        );

        $this->fillForm();

        Notification::make()
            ->title('Formato actualizado')
            ->body('Las próximas cotizaciones se armarán con el número de hojas y la posición de cálculos que acaba de guardar.')
            ->success()
            ->send();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::userCanAccessPage();
    }

    public static function canAccess(): bool
    {
        return self::userCanAccessPage();
    }

    private function scopeSection(string $scope, string $description): Section
    {
        return Section::make(QuoteDocumentLayout::label($scope))
            ->description($description)
            ->icon(Heroicon::OutlinedDocumentText)
            ->columnSpanFull()
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextInput::make($scope.'.total_pages')
                            ->label('Número de hojas')
                            ->helperText('Total de páginas del documento final. Máximo '.QuoteDocumentLayout::MAX_TOTAL_PAGES.'.')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(QuoteDocumentLayout::MAX_TOTAL_PAGES)
                            ->validationMessages([
                                'required' => 'Indique cuántas hojas tendrá la cotización.',
                                'min' => 'La cotización debe tener al menos una hoja.',
                                'max' => 'El máximo admitido es '.QuoteDocumentLayout::MAX_TOTAL_PAGES.' hojas.',
                            ]),
                        TextInput::make($scope.'.calculations_page')
                            ->label('Página de los cálculos')
                            ->helperText('En qué hoja se ubican las tarifas. Nunca puede superar el número de hojas.')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(QuoteDocumentLayout::MAX_TOTAL_PAGES)
                            ->validationMessages([
                                'required' => 'Indique en qué hoja van los cálculos.',
                                'min' => 'La página de cálculos empieza en 1.',
                            ]),
                    ]),
            ]);
    }

    private function fillForm(): void
    {
        $data = [];

        foreach (QuoteDocumentLayout::scopes() as $scope) {
            $data[$scope] = QuoteDocumentLayout::for($scope);
        }

        $this->form->fill($data);
    }

    private static function userCanAccessPage(): bool
    {
        $departments = (array) (Auth::user()?->departament ?? []);

        return in_array('SUPERADMIN', $departments, true);
    }
}
