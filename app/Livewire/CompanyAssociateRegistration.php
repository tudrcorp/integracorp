<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Company;
use App\Models\CompanyAssociate;
use App\Models\CompanyResponsible;
use App\Support\Companies\CompanyAssociateRegistrar;
use App\Support\Companies\CompanyAssociateRegistrationNotifier;
use App\Support\SecurityAudit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

    public ?string $resolvedResponsibleStartDate = null;

    public ?string $resolvedResponsibleEndDate = null;

    public ?int $responsibleCalculatedDays = null;

    public ?int $responsibleRemainingDays = null;

    public string $fullName = '';

    public string $identityCard = '';

    public string $birthDate = '';

    public ?int $age = null;

    public string $email = '';

    public string $phone = '';

    public string $sex = '';

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

    public function updatedResolvedResponsibleStartDate(): void
    {
        $this->recalculateResponsibleDays();
    }

    public function updatedResolvedResponsibleEndDate(): void
    {
        $this->recalculateResponsibleDays();
    }

    public function recalculateResponsibleDays(): void
    {
        $this->responsibleCalculatedDays = CompanyAssociateRegistrar::calculateDaysBetween(
            $this->resolvedResponsibleStartDate,
            $this->resolvedResponsibleEndDate,
        );

        if ($this->resolvedResponsibleAvailableDays === null) {
            $this->responsibleRemainingDays = null;

            return;
        }

        $this->responsibleRemainingDays = CompanyAssociateRegistrar::remainingDaysAfterRegistration(
            $this->resolvedResponsible(),
            $this->responsibleCalculatedDays,
        );
    }

    public function resolveResponsible(): void
    {
        $this->resolvedResponsibleName = null;
        $this->resolvedResponsibleId = null;
        $this->resolvedResponsibleContractedDays = null;
        $this->resolvedResponsibleConsumedDays = null;
        $this->resolvedResponsibleAvailableDays = null;
        $this->responsibleDaysExhausted = false;
        $this->resolvedResponsibleStartDate = null;
        $this->resolvedResponsibleEndDate = null;
        $this->responsibleCalculatedDays = null;
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
        $this->resolvedResponsibleStartDate = $responsible->contract_start_date?->format('Y-m-d');
        $this->resolvedResponsibleEndDate = $responsible->contract_end_date?->format('Y-m-d');
        $this->recalculateResponsibleDays();
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
            $this->addError('resolvedResponsibleId', 'Este responsable ha consumido el total de días contratados. No es posible registrar un nuevo asociado.');

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

        if ($this->responsibleCalculatedDays === null || $this->responsibleRemainingDays === null || $this->responsibleRemainingDays < 0) {
            $this->addError('resolvedResponsibleEndDate', 'Los días del período seleccionado exceden los días disponibles del responsable.');

            return;
        }

        $registeredAt = now();
        $associateId = null;

        try {
            DB::transaction(function () use ($validated, $age, $registeredAt, &$associateId): void {
                $responsible = $this->resolvedResponsible();

                if (CompanyAssociateRegistrar::remainingDaysAfterRegistration($responsible, $this->responsibleCalculatedDays) < 0) {
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
                    'contact_full_name' => $validated['contactFullName'],
                    'contact_phone' => $validated['contactPhone'],
                    'contact_email' => $validated['contactEmail'],
                    'identity_document' => $documentPaths[0] ?? null,
                    'identity_documents' => $documentPaths,
                    'registered_at' => $registeredAt,
                    'registration_start_date' => $this->resolvedResponsibleStartDate,
                    'registration_end_date' => $this->resolvedResponsibleEndDate,
                    'registration_period_days' => $this->responsibleCalculatedDays,
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
            'resolvedResponsibleStartDate',
            'resolvedResponsibleEndDate',
            'responsibleCalculatedDays',
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
                        $fail('Este responsable ha consumido el total de días contratados. No es posible registrar un nuevo asociado.');
                    }
                },
            ],
            'resolvedResponsibleStartDate' => ['required', 'date'],
            'resolvedResponsibleEndDate' => [
                'required',
                'date',
                'after_or_equal:resolvedResponsibleStartDate',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->responsibleDaysExhausted) {
                        return;
                    }

                    if ($this->responsibleRemainingDays === null) {
                        return;
                    }

                    if ($this->responsibleRemainingDays < 0) {
                        $fail('Los días del período seleccionado exceden los días disponibles del responsable.');
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
                        $fail('Ya existe un asociado registrado con este documento de identidad.');
                    }
                },
            ],
            'birthDate' => ['required', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('company_associates', 'email')],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+[1-9]\d{6,14}$/'],
            'flightDate' => ['required', 'date', 'after_or_equal:today'],
            'flightTime' => ['required', 'date_format:H:i'],
            'sex' => ['required', Rule::in(['MASCULINO', 'FEMENINO'])],
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
            'resolvedResponsibleStartDate.required' => 'Debe seleccionar la fecha desde del responsable.',
            'resolvedResponsibleEndDate.required' => 'Debe seleccionar la fecha hasta del responsable.',
            'resolvedResponsibleEndDate.after_or_equal' => 'La fecha hasta debe ser igual o posterior a la fecha desde.',
            'email.unique' => 'Ya existe un asociado registrado con este correo electrónico.',
            'phone.required' => 'El teléfono es obligatorio.',
            'phone.regex' => 'Ingrese el teléfono con prefijo de país. Ej: +584127018390',
            'flightDate.required' => 'Debe seleccionar la fecha de vuelo.',
            'flightDate.after_or_equal' => 'La fecha de vuelo no puede ser anterior a hoy.',
            'flightTime.required' => 'Debe seleccionar la hora de vuelo.',
            'flightTime.date_format' => 'La hora de vuelo no es válida.',
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
