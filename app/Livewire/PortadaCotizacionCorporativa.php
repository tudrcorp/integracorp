<?php

namespace App\Livewire;

use Livewire\Component;

class PortadaCotizacionCorporativa extends Component
{
    public $name;

    public function mount($name)
    {
        $this->name = $name;
    }
    
    public function render()
    {
        return view('livewire.portada-cotizacion-corporativa', [
            'name' => $this->name
        ]);
    }
}