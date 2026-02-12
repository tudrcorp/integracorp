<?php

namespace App\Livewire;

use Livewire\Component;

class PlanesCotizacionIndividualEspecial extends Component
{
    public $data = [];
    public $name;
    public $name_user;
    public $number_control;

    public function mount($data, $name, $name_user, $number_control)
    {
        $this->data = $data;
        $this->name = $name;
        $this->name_user = $name_user;
        $this->number_control = $number_control;
    }
    
    public function render()
    {
        return view('livewire.planes-cotizacion-individual-especial');
    }
}