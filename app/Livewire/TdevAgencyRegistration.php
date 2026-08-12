<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Models\TdevAgency;
use App\Support\Tdev\TdevAgencyRegistrar;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class TdevAgencyRegistration extends Component
{
    use WithFileUploads;

    public TdevAgency $parentAgency;

    public string $name = '';

    public string $identificationNumber = '';

    public string $email = '';

    public string $anniversaryDate = '';

    public string $representativeName = '';

    public string $representativeBirthDate = '';

    public string $phone = '';

    public string $phoneAdditional = '';

    public string $instagramUsername = '';

    public ?int $countryId = null;

    public ?int $stateId = null;

    public ?int $cityId = null;

    public string $address = '';

    public string $url = '';

    public $logo = null;

    public bool $submitted = false;

    public bool $askAssociateAgents = false;

    public ?string $registeredAtDisplay = null;

    public ?string $createdAgencyName = null;

    public ?string $createdAgencyAgentRegistrationUrl = null;

    public function mount(string $token): void
    {
        $this->parentAgency = TdevAgency::query()
            ->where('agency_registration_token', $token)
            ->where('level', TdevAgency::LEVEL_TWO)
            ->firstOrFail();
    }

    public function updatedCountryId(): void
    {
        $this->stateId = null;
        $this->cityId = null;
    }

    public function updatedStateId(): void
    {
        $this->cityId = null;
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function countries(): array
    {
        return Country::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function states(): array
    {
        if (blank($this->countryId)) {
            return [];
        }

        return State::query()
            ->where('country_id', $this->countryId)
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

    public function submit(): void
    {
        $validated = $this->validate();

        $agency = TdevAgencyRegistrar::registerLevelThreeAgency($this->parentAgency, [
            'name' => $validated['name'],
            'identification_number' => $validated['identificationNumber'] ?: null,
            'email' => $validated['email'] ?: null,
            'anniversary_date' => $validated['anniversaryDate'] ?: null,
            'representative_name' => $validated['representativeName'] ?: null,
            'representative_birth_date' => $validated['representativeBirthDate'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'phone_additional' => $validated['phoneAdditional'] ?: null,
            'instagram_username' => $validated['instagramUsername'] ?: null,
            'country_id' => $validated['countryId'] ?: null,
            'state_id' => $validated['stateId'] ?: null,
            'city_id' => $validated['cityId'] ?: null,
            'address' => $validated['address'] ?: null,
            'url' => $validated['url'] ?: null,
            'logo' => $this->logo,
        ]);

        $this->submitted = true;
        $this->askAssociateAgents = true;
        $this->registeredAtDisplay = now()->timezone(config('app.timezone'))->format('d/m/Y H:i');
        $this->createdAgencyName = $agency->name;
        $this->createdAgencyAgentRegistrationUrl = TdevAgencyRegistrar::publicAgentRegistrationUrl($agency);
        $this->resetFormFields();
    }

    public function associateAgentsNow(): mixed
    {
        if (blank($this->createdAgencyAgentRegistrationUrl)) {
            return null;
        }

        return $this->redirect($this->createdAgencyAgentRegistrationUrl, navigate: true);
    }

    public function skipAssociateAgents(): void
    {
        $this->askAssociateAgents = false;
    }

    public function startNewRegistration(): void
    {
        $this->submitted = false;
        $this->askAssociateAgents = false;
        $this->registeredAtDisplay = null;
        $this->createdAgencyName = null;
        $this->createdAgencyAgentRegistrationUrl = null;
        $this->resetFormFields();
        $this->resetValidation();
    }

    protected function resetFormFields(): void
    {
        $this->name = '';
        $this->identificationNumber = '';
        $this->email = '';
        $this->anniversaryDate = '';
        $this->representativeName = '';
        $this->representativeBirthDate = '';
        $this->phone = '';
        $this->phoneAdditional = '';
        $this->instagramUsername = '';
        $this->countryId = null;
        $this->stateId = null;
        $this->cityId = null;
        $this->address = '';
        $this->url = '';
        $this->logo = null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'identificationNumber' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'anniversaryDate' => ['nullable', 'date'],
            'representativeName' => ['nullable', 'string', 'max:255'],
            'representativeBirthDate' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:40'],
            'phoneAdditional' => ['nullable', 'string', 'max:40'],
            'instagramUsername' => ['nullable', 'string', 'max:100'],
            'countryId' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'stateId' => [
                'nullable',
                'integer',
                Rule::exists('states', 'id')->where(fn ($query) => $query->where('country_id', $this->countryId)),
            ],
            'cityId' => [
                'nullable',
                'integer',
                Rule::exists('cities', 'id')->where(fn ($query) => $query->where('state_id', $this->stateId)),
            ],
            'address' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'El nombre de la agencia es obligatorio.',
            'email.email' => 'Ingrese un correo válido.',
            'url.url' => 'Ingrese una URL válida.',
            'logo.image' => 'El logo debe ser una imagen válida.',
            'logo.max' => 'El logo no puede superar 2 MB.',
            'representativeBirthDate.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombre de agencia',
            'identificationNumber' => 'número de identificación',
            'email' => 'correo',
            'anniversaryDate' => 'fecha de aniversario',
            'representativeName' => 'nombre del representante',
            'representativeBirthDate' => 'fecha de nacimiento del representante',
            'phone' => 'teléfono',
            'phoneAdditional' => 'teléfono adicional',
            'instagramUsername' => 'usuario Instagram',
            'countryId' => 'país',
            'stateId' => 'estado',
            'cityId' => 'ciudad',
            'address' => 'dirección',
            'url' => 'URL',
            'logo' => 'imagen del logo',
        ];
    }

    public function render(): View
    {
        return view('livewire.tdev-agency-registration')
            ->layout('layouts.tdev-agent-registration');
    }
}
