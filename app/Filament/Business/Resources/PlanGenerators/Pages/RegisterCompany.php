<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Pages;

use App\Filament\Business\Resources\PlanGenerators\PlanGeneratorResource;
use App\Filament\Business\Resources\PlanGenerators\Schemas\RegisterCompanyForm;
use App\Models\Company;
use App\Models\PlanGenerator;
use App\Support\Filament\FilamentIosButton;
use App\Support\PlanGenerators\PlanGeneratorCompanyRates;
use App\Support\PlanGenerators\PlanGeneratorPreAffiliationSession;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Panel;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Throwable;

class RegisterCompany extends Page
{
    use CanUseDatabaseTransactions;
    use InteractsWithRecord;

    protected static string $resource = PlanGeneratorResource::class;

    protected static ?string $title = 'Registro de Empresa';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.business.resources.plan-generators.pages.register-company';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getRoutePath(Panel $panel): string
    {
        return '/{record}/register-company';
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();

        $this->fillForm();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
    }

    protected function fillForm(): void
    {
        /** @var PlanGenerator $plan */
        $plan = $this->getRecord();

        $this->ensurePlanGeneratorSession($plan);

        $payload = PlanGeneratorPreAffiliationSession::get();
        $columnKey = PlanGeneratorCompanyRates::defaultColumnKey($payload);
        $dataRecord = PlanGeneratorCompanyRates::dataRecordForColumn($payload, $columnKey);
        $amounts = PlanGeneratorCompanyRates::amountsFor('ANUAL', $dataRecord ?? []);

        $this->form->fill([
            'name' => $plan->client_data,
            'responsibles' => [],
            'plan_generator_column_key' => $columnKey,
            'plan_generator_column_label' => (string) ($dataRecord['header_label'] ?? ''),
            'payment_frequency' => 'ANUAL',
            'fee_anual' => $amounts['fee_anual'],
            'total_amount' => $amounts['total_amount'],
        ]);
    }

    protected function ensurePlanGeneratorSession(PlanGenerator $plan): void
    {
        $payload = PlanGeneratorPreAffiliationSession::get();
        $activePlanId = is_array($payload) ? ($payload['plan_generator_id'] ?? null) : null;

        if (! PlanGeneratorPreAffiliationSession::isActive() || (int) $activePlanId !== (int) $plan->getKey()) {
            PlanGeneratorPreAffiliationSession::store($plan, PlanGeneratorPreAffiliationSession::TYPE_NEW_BUSINESS);
        }
    }

    public function getTitle(): string|Htmlable
    {
        return 'Registro de Empresa';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $plan = $this->getRecord();

        return 'Cotización aprobada · '.($plan->name ?? '—');
    }

    public function form(Schema $schema): Schema
    {
        return RegisterCompanyForm::configure($schema);
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
            ->id('register-company-form')
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
                ->label('Registrar empresa')
                ->icon('heroicon-o-check')
                ->submit('save')
                ->color('success')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                ]),
            Action::make('back')
                ->label('Volver al plan')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(PlanGeneratorResource::getUrl('view', ['record' => $this->getRecord()]))
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                ]),
        ];
    }

    public function save(): void
    {
        /** @var PlanGenerator $plan */
        $plan = $this->getRecord();

        try {
            $this->beginDatabaseTransaction();

            $data = $this->form->getState();
            $payload = PlanGeneratorPreAffiliationSession::get();
            $columnKey = $data['plan_generator_column_key']
                ?? PlanGeneratorCompanyRates::defaultColumnKey($payload);

            if (blank($data['plan_generator_column_label'] ?? null) && is_string($columnKey)) {
                $dataRecord = PlanGeneratorCompanyRates::dataRecordForColumn($payload, $columnKey);
                $data['plan_generator_column_label'] = (string) ($dataRecord['header_label'] ?? '');
            }

            $company = Company::create([
                'plan_generator_id' => $plan->getKey(),
                'plan_generator_column_key' => $columnKey,
                'plan_generator_column_label' => $data['plan_generator_column_label'] ?? null,
                'payment_frequency' => $data['payment_frequency'] ?? 'ANUAL',
                'fee_anual' => $data['fee_anual'] ?? null,
                'total_amount' => $data['total_amount'] ?? null,
                'name' => $data['name'],
                'rif' => $data['rif'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'created_by' => Auth::user()?->name,
            ]);

            $this->persistResponsibles($company, $data['responsibles'] ?? []);

            $this->commitDatabaseTransaction();

            PlanGeneratorPreAffiliationSession::forget();

            SecurityAudit::log('AUDIT_BUSINESS_COMPANY_REGISTERED', 'business.plan-generators.register-company', [
                'plan_generator_id' => $plan->getKey(),
                'company_id' => $company->getKey(),
                'company_name' => $company->name,
                'company_rif' => $company->rif,
                'responsibles_count' => $company->responsibles()->count(),
            ]);
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }

        Notification::make()
            ->title('Empresa registrada')
            ->body('Los datos de la empresa se guardaron correctamente.')
            ->success()
            ->send();

        $this->redirect(PlanGeneratorResource::getUrl('view', ['record' => $plan->getKey()]));
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $responsibles
     */
    protected function persistResponsibles(Company $company, array $responsibles): void
    {
        foreach ($responsibles as $responsible) {
            if (! is_array($responsible) || blank($responsible['full_name'] ?? null)) {
                continue;
            }

            $company->responsibles()->create([
                'full_name' => $responsible['full_name'],
                'identity_card' => $responsible['identity_card'] ?? null,
                'company' => $responsible['company'] ?? null,
                'phone' => $responsible['phone'] ?? null,
                'email' => $responsible['email'] ?? null,
                'state_id' => $responsible['state_id'] ?? null,
                'zone_id' => $responsible['zone_id'] ?? null,
                'contract_start_date' => $responsible['contract_start_date'] ?? null,
                'contract_end_date' => $responsible['contract_end_date'] ?? null,
                'contracted_days' => (int) ($responsible['contracted_days'] ?? 0),
            ]);
        }
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            'fi-plan-generator-register-company-page',
            'fi-resource-'.str_replace('/', '-', static::getResource()::getSlug()),
            'fi-resource-record-'.$this->getRecord()->getKey(),
        ];
    }
}
