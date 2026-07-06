<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithQuotePdfCoverageTable;
use Livewire\Component;

class PlanesCotizacionIndividualEspecial extends Component
{
    use InteractsWithQuotePdfCoverageTable;

    public $data = [];

    public $name;

    public $name_user;

    public $number_control;

    public function mount($data, $name, $name_user, $number_control, int|string|null $planId = null): void
    {
        $this->data = $data;
        $this->name = $name;
        $this->name_user = $name_user;
        $this->number_control = $number_control;
        $this->buildQuotePdfCoverageTable($data, $planId);
    }

    public function render()
    {
        return view('livewire.planes-cotizacion-individual-especial');
    }
}
