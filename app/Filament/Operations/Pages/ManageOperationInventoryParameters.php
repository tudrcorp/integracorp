<?php

declare(strict_types=1);

namespace App\Filament\Operations\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\OperationInventorySetting;
use App\Support\Filament\FilamentIosButton;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use UnitEnum;

class ManageOperationInventoryParameters extends Page
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $navigationLabel = 'Parámetros de Inventario';

    protected static ?string $title = 'Parámetros de Inventario';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'INVENTARIO DIAGNOMOVIL';

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'parametros-inventario';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = OperationInventorySetting::current();

        $this->form->fill([
            'low_stock_threshold' => $settings->lowStockThreshold(),
        ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString(
            '<span class="text-sm text-slate-500 dark:text-slate-400">Configure el umbral de existencia que activa las alertas diarias de stock bajo.</span>'
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Fieldset::make('Alertas de stock bajo')
                    ->schema([
                        TextInput::make('low_stock_threshold')
                            ->label('Umbral de existencia')
                            ->helperText('Se notificará diariamente cuando la existencia total de un producto activo sea menor o igual a este valor.')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required()
                            ->prefixIcon('heroicon-m-exclamation-triangle'),
                    ]),
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
            ->id('manage-operation-inventory-parameters-form')
            ->livewireSubmitHandler('save')
            ->footer([
                $this->getFormActionsContentComponent(),
            ]);
    }

    public function getFormActionsContentComponent(): Actions
    {
        return Actions::make($this->getFormActions())
            ->fullWidth(false)
            ->sticky();
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar parámetros')
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
        $threshold = max(0, (int) ($data['low_stock_threshold'] ?? 3));

        $settings = OperationInventorySetting::current();
        $settings->update([
            'low_stock_threshold' => $threshold,
            'updated_by' => Auth::user()?->name,
        ]);

        $this->form->fill([
            'low_stock_threshold' => $settings->lowStockThreshold(),
        ]);

        Notification::make()
            ->title('Parámetros guardados')
            ->body("El umbral de stock bajo quedó en {$threshold}. Las alertas diarias usarán este valor.")
            ->success()
            ->send();
    }
}
