<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiBcvController extends Controller
{
    //
    public static function getTasaBcv()
    {
        //https://ve.dolarapi.com/v1/dolares/oficial
        //Response: {"moneda": "USD","fuente": "oficial","nombre": "Dólar","compra": null,"venta": null,"promedio": 482.7586,"fechaActualizacion": "2026-04-22T00:00:00-04:00"}
        try {

            $response = Http::get('https://ve.dolarapi.com/v1/dolares/oficial');
            $data = $response->json();
            $value = round($data['promedio'], 2);
            return $value;

        } catch (\Exception $e) {
            return null;
            Log::error('Error al obtener la tasa BCV: ' . $e->getMessage());
        }
    }

    public static function statusApiBcv(): bool
    {

        //https://ve.dolarapi.com/v1/estado
        //Response: {"estado": "Disponible","aleatorio": 1234}
        try {
            
            $response = Http::get('https://ve.dolarapi.com/v1/estado');
            $data = $response->json();
            return $data['estado'] === 'Disponible';

        } catch (\Exception $e) {
            return false;
            Log::error('Error al obtener el estado de la API BCV: ' . $e->getMessage());
        }
    }
}
