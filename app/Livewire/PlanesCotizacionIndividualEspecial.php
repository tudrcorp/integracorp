<?php

namespace App\Livewire;

use Livewire\Component;

class PlanesCotizacionIndividualEspecial extends Component
{
    public $data = [];
    public $name;

    public function mount($data, $name)
    {
        $this->data = $data;
        $this->name = $name;
    }
    
    public function render()
    {
        return view('livewire.planes-cotizacion-individual-especial');
    }
}