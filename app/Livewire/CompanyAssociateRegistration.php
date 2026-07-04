<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\City;
use App\Models\Company;
use App\Models\CompanyAssociate;
use App\Models\CompanyResponsible;
use App\Models\State;
use App\Support\Companies\CompanyAssociateRegistrar;
use App\Support\Companies\CompanyAssociateRegistrationNotifier;
use App\Support\SecurityAudit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class CompanyAssociateRegistration extends Component
{
    use WithFileUploads;

    public Company $company;

    public string $responsibleIdentityCard = '';

    public ?string $resolvedResponsibleName = null;

    public ?int $resolvedResponsibleId = null;

    public ?int $resolvedResponsibleContractedDays = null;

    public ?int $resolvedResponsibleConsumedDays = null;

    public ?int $resolvedResponsibleAvailableDays = null;

    public bool $responsibleDaysExhausted = false;

    public ?int $responsibleRemainingDays = null;

    public string $fullName = '';

    public string $identityCard = '';

    public string $birthDate = '';

    public ?int $age = null;

    public string $email = '';

    public string $phone = '';

    public string $sex = '';

    public ?int $stateId = null;

    public ?int $cityId = null;

    public string $observations = '';

    public string $flightDate = '';

    public string $flightTime = '';

    public string $contactFullName = '';

    public string $contactPhone = '';

    public string $contactEmail = '';

    public $identityDocuments = [];

    public bool $submitted = false;

    public ?string $registeredAtDisplay = null;

    public function mount(string $token): void
    {
        $company = Company::query()
            ->where('registration_token', $token)
            ->firstOrFail();

        $this->company = $company;
    }

    public function updatedResponsibleIdentityCard(): void
    {
        $this->resolveResponsible();
    }

    public function updatedBirthDate(): void
    {
        $this->age = CompanyAssociateRegistrar::calculateAge($this->birthDate);
    }

    public function updatedPhone(?string $value): void
    {
        $this->phone = CompanyAssociateRegistrar::normalizeInternationalPhone($value);
    }

    public function updatedStateId(): void
    {
        $this->cityId = null;
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function states(): array
    {
        return State::query()
            ->orderBy('definition')
            ->pluck('definition', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function cities(): array
    {
        if (blank($this->stateId)) {
            return [];
        }

        return City::query()
            ->where('state_id', $this->stateId)
            ->orderBy('definition')
            ->pluck('definition', 'id')
            ->all();
    }

    public function resolveResponsible(): void
    {
        $this->resolvedResponsibleName = null;
        $this->resolvedResponsibleId = null;
        $this->resolvedResponsibleContractedDays = null;
        $this->resolvedResponsibleConsumedDays = null;
        $this->resolvedResponsibleAvailableDays = null;
        $this->responsibleDaysExhausted = false;
        $this->responsibleRemainingDays = null;

        if (blank($this->responsibleIdentityCard)) {
            return;
        }

        $responsible = CompanyAssociateRegistrar::findResponsibleForCompany(
            $this->company,
            $this->responsibleIdentityCard,
        );

        if ($responsible === null) {
            return;
        }

        $this->resolvedResponsibleName = $responsible->full_name;
        $this->resolvedResponsibleId = $responsible->id;
        $this->resolvedResponsibleContractedDays = (int) $responsible->contracted_days;
        $this->resolvedResponsibleConsumedDays = CompanyAssociateRegistrar::consumedDaysByResponsible($responsible);
        $this->resolvedResponsibleAvailableDays = CompanyAssociateRegistrar::availableDaysForResponsible($responsible);
        $this->responsibleDaysExhausted = CompanyAssociateRegistrar::hasExhaustedRegistrationDays($responsible);
        $this->responsibleRemainingDays = $this->resolvedResponsibleAvailableDays;
    }

    protected function resolvedResponsible(): CompanyResponsible
    {
        return CompanyResponsible::query()->findOrFail($this->resolvedResponsibleId);
    }

    public function startNewRegistration(): void
    {
        $this->submitted = false;
        $this->registeredAtDisplay = null;
    }

    public function submit(): void
    {
        $this->resolveResponsible();

        if ($this->responsibleDaysExhausted) {
            $this->addError('resolvedResponsibleId', 'Este responsable ha consumido el total de días contratados. No es posible registrar un nuevo afiliado.');

            return;
        }

        $validated = $this->validate(
            $this->rules(),
            $this->messages(),
        );

        $age = CompanyAssociateRegistrar::calculateAge($validated['birthDate']);

        if ($age === null) {
            $this->addError('birthDate', 'La fecha de nacimiento no es válida.');

            return;
        }

        $registeredAt = now();
        $associateId = null;

        try {
            DB::transaction(function () use ($validated, $age, $registeredAt, &$associateId): void {
                $responsible = $this->resolvedResponsible();

                if (CompanyAssociateRegistrar::remainingDaysAfterRegistration($responsible) < 0) {
                    throw new \RuntimeException('Responsible registration days exhausted.');
                }

                $documentPaths = [];

                foreach ($this->identityDocuments as $document) {
                    $documentPaths[] = $document->store(
                        'company-associates/identity-documents',
                        'public',
                    );
                }

                $associate = CompanyAssociate::create([
                    'company_id' => $this->company->getKey(),
                    'company_responsible_id' => $this->resolvedResponsibleId,
                    'full_name' => $validated['fullName'],
                    'identity_card' => $validated['identityCard'],
                    'birth_date' => $validated['birthDate'],
                    'age' => $age,
                    'email' => $validated['email'] ?: null,
                    'phone' => $validated['phone'],
                    'flight_date' => $validated['flightDate'],
                    'flight_time' => $validated['flightTime'],
                    'sex' => $validated['sex'],
                    'state_id' => $validated['stateId'],
                    'city_id' => $validated['cityId'],
                    'observations' => $validated['observations'] ?: null,
                    'contact_full_name' => $validated['contactFullName'],
                    'contact_phone' => $validated['contactPhone'],
                    'contact_email' => $validated['contactEmail'],
                    'identity_document' => $documentPaths[0] ?? null,
                    'identity_documents' => $documentPaths,
                    'registered_at' => $registeredAt,
                    'registration_period_days' => CompanyAssociateRegistrar::DAYS_PER_REGISTRATION,
                ]);

                $associateId = $associate->getKey();
            });

            SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_REGISTERED', 'company-associates.public-register', [
                'company_id' => $this->company->getKey(),
                'company_name' => $this->company->name,
                'company_responsible_id' => $this->resolvedResponsibleId,
                'associate_id' => $associateId,
                'registered_at' => $registeredAt->toIso8601String(),
            ]);

            if ($associateId !== null) {
                CompanyAssociateRegistrationNotifier::notify($associateId);
            }
        } catch (\Throwable $exception) {
            report($exception);

            $this->addError('submit', 'No se pudo completar el registro. Intente nuevamente.');

            return;
        }

        $this->submitted = true;
        $this->registeredAtDisplay = $registeredAt->format('d/m/Y H:i:s');
        $this->reset([
            'responsibleIdentityCard',
            'resolvedResponsibleName',
            'resolvedResponsibleId',
            'resolvedResponsibleContractedDays',
            'resolvedResponsibleConsumedDays',
            'resolvedResponsibleAvailableDays',
            'responsibleDaysExhausted',
            'responsibleRemainingDays',
            'fullName',
            'identityCard',
            'birthDate',
            'age',
            'email',
            'phone',
            'flightDate',
            'flightTime',
            'sex',
            'stateId',
            'cityId',
            'observations',
            'contactFullName',
            'contactPhone',
            'contactEmail',
            'identityDocuments',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'responsibleIdentityCard' => ['required', 'string', 'max:20'],
            'resolvedResponsibleId' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->responsibleDaysExhausted) {
                        $fail('Este responsable ha consumido el total de días contratados. No es posible registrar un nuevo afiliado.');
                    }
                },
            ],
            'fullName' => ['required', 'string', 'max:255'],
            'identityCard' => [
                'required',
                'string',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $normalized = CompanyAssociateRegistrar::normalizeIdentityCard((string) $value);

                    if ($normalized === '') {
                        return;
                    }

                    $exists = CompanyAssociate::query()
                        ->get(['identity_card'])
                        ->contains(fn (CompanyAssociate $associate): bool => CompanyAssociateRegistrar::normalizeIdentityCard($associate->identity_card) === $normalized);

                    if ($exists) {
                        $fail('Ya existe un afiliado registrado con este documento de identidad.');
                    }
                },
            ],
            'birthDate' => ['required', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('company_associates', 'email')],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+[1-9]\d{6,14}$/'],
            'flightDate' => ['required', 'date', 'after_or_equal:today'],
            'flightTime' => ['required', 'date_format:H:i'],
            'sex' => ['required', Rule::in(['MASCULINO', 'FEMENINO'])],
            'stateId' => ['required', 'integer', Rule::exists('states', 'id')],
            'cityId' => [
                'required',
                'integer',
                Rule::exists('cities', 'id')->where(fn ($query) => $query->where('state_id', $this->stateId)),
            ],
            'observations' => ['nullable', 'string', 'max:2000'],
            'contactFullName' => ['required', 'string', 'max:255'],
            'contactPhone' => ['required', 'string', 'max:30'],
            'contactEmail' => ['required', 'email', 'max:255'],
            'identityDocuments' => ['required', 'array', 'min:1'],
            'identityDocuments.*' => ['required', 'image', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'resolvedResponsibleId.required' => 'No se encontró un responsable con esa cédula para esta empresa.',
            'email.unique' => 'Ya existe un afiliado registrado con este correo electrónico.',
            'phone.required' => 'El teléfono es obligatorio.',
            'phone.regex' => 'Ingrese el teléfono con prefijo de país. Ej: +584127018390',
            'flightDate.required' => 'Debe seleccionar la fecha de vuelo.',
            'flightDate.after_or_equal' => 'La fecha de vuelo no puede ser anterior a hoy.',
            'flightTime.required' => 'Debe seleccionar la hora de vuelo.',
            'flightTime.date_format' => 'La hora de vuelo no es válida.',
            'stateId.required' => 'Debe seleccionar un estado.',
            'stateId.exists' => 'El estado seleccionado no es válido.',
            'cityId.required' => 'Debe seleccionar una ciudad.',
            'cityId.exists' => 'La ciudad seleccionada no pertenece al estado indicado.',
            'contactPhone.required' => 'El teléfono del contacto de emergencia es obligatorio.',
            'contactEmail.required' => 'El correo del contacto de emergencia es obligatorio.',
            'identityDocuments.required' => 'Debe cargar al menos un documento de identidad.',
            'identityDocuments.min' => 'Debe cargar al menos un documento de identidad.',
            'identityDocuments.*.image' => 'Cada documento debe ser una imagen válida.',
            'identityDocuments.*.max' => 'Cada documento no puede superar 5 MB.',
        ];
    }

    public function render(): View
    {
        return view('livewire.company-associate-registration')
            ->layout('layouts.company-associate-registration');
    }
}
