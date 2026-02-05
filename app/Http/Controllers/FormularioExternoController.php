<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FormularioExternoController extends Controller
{
    public function countries()
    {
        return Country::all(['id', 'name']);
    }
    public function statesByCountry($countryId)
    {
        return State::where('country_id', $countryId)->get(['id', 'definition']);
    }
    public function citiesByState($stateId)
    {
        return City::where('state_id', $stateId)->get(['id', 'definition']);
    }
}
