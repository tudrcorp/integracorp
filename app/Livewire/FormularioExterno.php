<?php

namespace App\Livewire;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Support\Collection;
use Livewire\Component;

class FormularioExterno extends Component
{
    // Propiedades del formulario
    public $name;
    public $email;
    public $legal_name;
    public $phone;
    public $website;

    // Propiedades de ubicación
    public $country_id;
    public $state_id;
    public $city_id;

    // Colecciones para los selects
    public Collection $countries;
    public Collection $states;
    public Collection $cities;

    public function mount()
    {
        $this->countries = Country::all(['id', 'name']);
        $this->states = collect();
        $this->cities = collect();
    }

    /**
     * Se ejecuta automáticamente cuando cambia country_id
     */
    public function updatedCountryId($value)
    {
        $this->states = State::where('country_id', $value)->get(['id', 'name']);
        $this->state_id = null;
        $this->city_id = null;
        $this->cities = collect();
    }

    /**
     * Se ejecuta automáticamente cuando cambia state_id
     */
    public function updatedStateId($value)
    {
        $this->cities = City::where('state_id', $value)->get(['id', 'name']);
        $this->city_id = null;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'country_id' => 'required',
            'state_id' => 'required',
            'city_id' => 'required',
        ]);

        // Lógica para guardar la agencia...
        session()->flash('message', 'Agencia registrada con éxito.');
    }
    public function render()
    {
        return view('livewire.formulario-externo');
    }
}
