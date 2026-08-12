<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\TdevAgency;
use App\Support\Tdev\TdevAgencyRegistrar;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class TdevAgentRegistration extends Component
{
    public TdevAgency $agency;

    public string $fullName = '';

    public string $position = '';

    public string $email = '';

    public string $phone = '';

    public string $birthDate = '';

    public bool $submitted = false;

    public ?string $registeredAtDisplay = null;

    public function mount(string $token): void
    {
        $this->agency = TdevAgency::query()
            ->with('parentAgency')
            ->where('registration_token', $token)
            ->firstOrFail();
    }

    public function submit(): void
    {
        $validated = $this->validate();

        TdevAgencyRegistrar::registerAgent($this->agency, [
            'full_name' => $validated['fullName'],
            'position' => $validated['position'] ?: null,
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'birth_date' => $validated['birthDate'] ?: null,
        ]);

        $this->submitted = true;
        $this->registeredAtDisplay = now()->timezone(config('app.timezone'))->format('d/m/Y H:i');
        $this->resetFormFields();
    }

    public function startNewRegistration(): void
    {
        $this->submitted = false;
        $this->registeredAtDisplay = null;
        $this->resetFormFields();
        $this->resetValidation();
    }

    protected function resetFormFields(): void
    {
        $this->fullName = '';
        $this->position = '';
        $this->email = '';
        $this->phone = '';
        $this->birthDate = '';
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'fullName' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'birthDate' => ['nullable', 'date', 'before:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'fullName.required' => 'El nombre y apellido son obligatorios.',
            'email.email' => 'Ingrese un correo válido.',
            'birthDate.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'fullName' => 'nombre y apellido',
            'position' => 'cargo',
            'email' => 'correo',
            'phone' => 'teléfono',
            'birthDate' => 'fecha de nacimiento',
        ];
    }

    public function render(): View
    {
        return view('livewire.tdev-agent-registration')
            ->layout('layouts.tdev-agent-registration');
    }
}
