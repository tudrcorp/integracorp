<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithQuotePdfCoverageTable;
use Livewire\Component;

class PlanesCotizacionCorporativaIdeal extends Component
{
    use InteractsWithQuotePdfCoverageTable;

    public $data = [];

    public $name;

    public $name_user;

    public function mount($data, $name, $name_user, int|string|null $planId = null): void
    {
        $this->data = $data;
        $this->name = $name;
        $this->name_user = $name_user;
        $this->buildQuotePdfCoverageTable($data, $planId);
    }

    public function render()
    {
        return view('livewire.planes-cotizacion-corporativa-ideal');
    }
}
