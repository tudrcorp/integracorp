<?php

namespace App\Livewire;

use Livewire\Component;

class CartaBienvenidaAgente extends Component
{
    public $id;
    public $name;

    public function mount($id, $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
    
    public function render()
    {
        return view('livewire.carta-bienvenida-agente');
    }
}