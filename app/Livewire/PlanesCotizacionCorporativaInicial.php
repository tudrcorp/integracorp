<?php

namespace App\Livewire;

use Livewire\Component;

class PlanesCotizacionCorporativaInicial extends Component
{
    public $data = [];
    public $name;
    public $name_user;

    public function mount($data, $name, $name_user)
    {
        $this->data = $data;
        $this->name = $name;
        $this->name_user = $name_user;
    }
    public function render()
    {
        return view('livewire.planes-cotizacion-corporativa-inicial');
    }
}