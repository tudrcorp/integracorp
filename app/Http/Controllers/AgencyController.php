<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use Illuminate\Http\Request;

class AgencyController extends Controller
{
    static function generate_code_agency()
    {
        if (Agency::max('id') == null) {
            $parte_entera = 101;
        } else {
            $parte_entera = 100 + Agency::max('id');
        }
        return 'TDG-' . $parte_entera;
    }
}