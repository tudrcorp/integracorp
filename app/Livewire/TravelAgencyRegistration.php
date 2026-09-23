<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\TravelAgency;
use App\Support\TravelAgencies\TravelAgencyPublicRegistrar;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class TravelAgencyRegistration extends Component
{
    public TravelAgency $parentAgency;

    public string $name = '';

    public string $identificationNumber = '';

    public string $email = '';

    public string $representativeName = '';

    public string $phone = '';

    public string $phoneAdditional = '';

    public string $address = '';

    public string $instagramUsername = '';

    public bool $submitted = false;

    public bool $askAssociateAgents = false;

    public ?string $registeredAtDisplay = null;

    public ?string $createdAgencyName = null;

    public ?string $createdAgencyAgentRegistrationUrl = null;

    public function mount(string $token): void
    {
        $this->parentAgency = TravelAgency::query()
            ->where('agency_registration_token', $token)
            ->firstOrFail();
    }

    public function submit(): void
    {
        $validated = $this->validate();

        $agency = TravelAgencyPublicRegistrar::registerAgency($this->parentAgency, [
            'name' => $validated['name'],
            'numberIdentification' => $validated['identificationNumber'] ?: null,
            'email' => $validated['email'] ?: null,
            'representante' => $validated['representativeName'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'phoneAdditional' => $validated['phoneAdditional'] ?: null,
            'address' => $validated['address'] ?: null,
            'userInstagram' => $validated['instagramUsername'] ?: null,
        ]);

        $this->submitted = true;
        $this->askAssociateAgents = true;
        $this->registeredAtDisplay = now()->timezone(config('app.timezone'))->format('d/m/Y H:i');
        $this->createdAgencyName = $agency->name;
        $this->createdAgencyAgentRegistrationUrl = TravelAgencyPublicRegistrar::agentRegistrationUrl($agency);
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
        $this->representativeName = '';
        $this->phone = '';
        $this->phoneAdditional = '';
        $this->address = '';
        $this->instagramUsername = '';
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'identificationNumber' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'representativeName' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'phoneAdditional' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'instagramUsername' => ['nullable', 'string', 'max:255'],
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
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombre de la agencia',
            'identificationNumber' => 'RIF',
            'email' => 'correo',
            'representativeName' => 'representante',
            'phone' => 'teléfono',
            'phoneAdditional' => 'teléfono adicional',
            'address' => 'dirección',
            'instagramUsername' => 'Instagram',
        ];
    }

    public function render(): View
    {
        return view('livewire.travel-agency-registration', [
            'hierarchyPreview' => TravelAgencyPublicRegistrar::hierarchyFromParent($this->parentAgency),
        ])->layout('layouts.tdev-agent-registration', [
            'faviconUrl' => $this->parentAgency->faviconUrl(),
            'pageTitle' => 'Registro de agencia · '.$this->parentAgency->name,
        ]);
    }
}
