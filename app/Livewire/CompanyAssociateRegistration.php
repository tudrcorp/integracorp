<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Company;
use App\Models\CompanyAssociate;
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

    public string $fullName = '';

    public string $identityCard = '';

    public string $birthDate = '';

    public ?int $age = null;

    public string $email = '';

    public string $phone = '';

    public string $sex = '';

    public string $contactFullName = '';

    public string $contactPhone = '';

    public string $contactEmail = '';

    public $identityDocument;

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

    public function resolveResponsible(): void
    {
        $this->resolvedResponsibleName = null;
        $this->resolvedResponsibleId = null;

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
    }

    public function startNewRegistration(): void
    {
        $this->submitted = false;
        $this->registeredAtDisplay = null;
    }

    public function submit(): void
    {
        $this->resolveResponsible();

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
                $documentPath = $this->identityDocument->store(
                    'company-associates/identity-documents',
                    'public',
                );

                $associate = CompanyAssociate::create([
                    'company_id' => $this->company->getKey(),
                    'company_responsible_id' => $this->resolvedResponsibleId,
                    'full_name' => $validated['fullName'],
                    'identity_card' => $validated['identityCard'],
                    'birth_date' => $validated['birthDate'],
                    'age' => $age,
                    'email' => $validated['email'] ?: null,
                    'phone' => $validated['phone'] ?: null,
                    'sex' => $validated['sex'],
                    'contact_full_name' => $validated['contactFullName'],
                    'contact_phone' => $validated['contactPhone'],
                    'contact_email' => $validated['contactEmail'],
                    'identity_document' => $documentPath,
                    'registered_at' => $registeredAt,
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
            'fullName',
            'identityCard',
            'birthDate',
            'age',
            'email',
            'phone',
            'sex',
            'contactFullName',
            'contactPhone',
            'contactEmail',
            'identityDocument',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'responsibleIdentityCard' => ['required', 'string', 'max:20'],
            'resolvedResponsibleId' => ['required', 'integer'],
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
                        $fail('Ya existe un asociado registrado con esta cédula de identidad.');
                    }
                },
            ],
            'birthDate' => ['required', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('company_associates', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'sex' => ['required', Rule::in(['MASCULINO', 'FEMENINO'])],
            'contactFullName' => ['required', 'string', 'max:255'],
            'contactPhone' => ['required', 'string', 'max:30'],
            'contactEmail' => ['required', 'email', 'max:255'],
            'identityDocument' => ['required', 'image', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'resolvedResponsibleId.required' => 'No se encontró un responsable con esa cédula para esta empresa.',
            'email.unique' => 'Ya existe un asociado registrado con este correo electrónico.',
            'contactPhone.required' => 'El teléfono del contacto de emergencia es obligatorio.',
            'contactEmail.required' => 'El correo del contacto de emergencia es obligatorio.',
            'identityDocument.required' => 'Debe cargar el documento de identidad.',
            'identityDocument.image' => 'El documento debe ser una imagen válida.',
            'identityDocument.max' => 'El documento no puede superar 5 MB.',
        ];
    }

    public function render(): View
    {
        return view('livewire.company-associate-registration')
            ->layout('layouts.company-associate-registration');
    }
}
