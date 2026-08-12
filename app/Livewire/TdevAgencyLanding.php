<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\TdevAgency;
use App\Support\Tdev\TdevAgencyRegistrar;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class TdevAgencyLanding extends Component
{
    public TdevAgency $agency;

    public string $agencyRegistrationUrl = '';

    public string $freelanceAgentRegistrationUrl = '';

    public function mount(string $token): void
    {
        $this->agency = TdevAgency::query()
            ->with(['country', 'state', 'city'])
            ->where('registration_token', $token)
            ->where('level', TdevAgency::LEVEL_TWO)
            ->firstOrFail();

        $this->agencyRegistrationUrl = TdevAgencyRegistrar::publicLevelThreeAgencyRegistrationUrl($this->agency);
        $this->freelanceAgentRegistrationUrl = TdevAgencyRegistrar::publicAgentRegistrationUrl($this->agency);
    }

    public function render(): View
    {
        return view('livewire.tdev-agency-landing')
            ->layout('layouts.tdev-agent-registration');
    }
}
