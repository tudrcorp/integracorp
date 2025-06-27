<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AgentController extends Controller
{
    static function generate_code_agent($code) {

        // Encontrar el último número en el código
        preg_match('/\d+(?=\D*$)/', $code, $matches);

        if (!empty($matches)) {
            // Obtener el último número encontrado
            $ultimoNumero = (int)$matches[0];

            // Sumar 1 al número
            $nuevoNumero = $ultimoNumero + 1;

            // Reemplazar el último número en el código original con el nuevo valor
            $nuevoCodigo = preg_replace('/\d+(?=\D*$)/', $nuevoNumero, $code);

            return $nuevoCodigo;
        }
    }
}