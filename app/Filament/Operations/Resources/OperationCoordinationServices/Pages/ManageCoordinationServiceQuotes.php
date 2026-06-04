<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Pages;

use App\Filament\Operations\Resources\OperationCoordinationServices\OperationCoordinationServiceResource;
use App\Filament\Operations\Resources\OperationCoordinationServices\Schemas\ManageCoordinationServiceQuotesForm;
use App\Filament\Operations\Resources\OperationServiceOrders\OperationServiceOrderResource;
use App\Support\Filament\FilamentIosButton;
use App\Support\Operations\CoordinationServiceQuoteManager;
use Filament\Actions\Action;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Panel;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

class ManageCoordinationServiceQuotes extends Page
{
    use CanUseDatabaseTransactions;
    use InteractsWithRecord;

    protected static string $resource = OperationCoordinationServiceResource::class;

    protected static ?string $title = 'Gestionar cotizaciones';

    protected static ?string $navigationLabel = 'Gestionar cotizaciones';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.operations.resources.operation-coordination-services.pages.manage-coordination-service-quotes';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getRoutePath(Panel $panel): string
    {
        return '/{record}/manage-quotes';
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();

        abort_unless(
            CoordinationServiceQuoteManager::coordinationQuotes($this->getRecord())->isNotEmpty(),
            404,
            'Esta coordinación no tiene cotizaciones registradas.'
        );

        $this->fillForm();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Gestionar cotizaciones · #'.$this->getRecord()->getKey();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $record = $this->getRecord();

        return ($record->patient ?? 'Paciente').' · Ref. '.($record->reference_number ?? '—');
    }

    protected function fillForm(): void
    {
        $this->form->fill(CoordinationServiceQuoteManager::formDefaults($this->getRecord()));
    }

    public function form(Schema $schema): Schema
    {
        return ManageCoordinationServiceQuotesForm::configure($schema);
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
            ->id('manage-coordination-service-quotes-form')
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
                ->label('Guardar cambios')
                ->submit('save')
                ->color('warning')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                ]),
            Action::make('back')
                ->label('Volver al cuadro de control')
                ->color('gray')
                ->url(OperationCoordinationServiceResource::getUrl('index'))
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                ]),
        ];
    }

    public function save(): void
    {
        try {
            $this->beginDatabaseTransaction();

            $data = $this->form->getState();

            $createdOrderId = CoordinationServiceQuoteManager::save($this->getRecord(), $data);

            if ($createdOrderId === null) {
                $this->rollBackDatabaseTransaction();

                return;
            }

            $this->commitDatabaseTransaction();

            if ($createdOrderId > 0) {
                $this->redirect(OperationServiceOrderResource::getUrl('view', ['record' => $createdOrderId]));

                return;
            }
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction() ?
                $this->rollBackDatabaseTransaction() :
                $this->commitDatabaseTransaction();

            return;
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }

        $this->fillForm();
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            'fi-coordination-manage-quotes-page',
            'fi-resource-'.str_replace('/', '-', static::getResource()::getSlug()),
            'fi-resource-record-'.$this->getRecord()->getKey(),
        ];
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canView($this->getRecord()), 403);
    }
}
