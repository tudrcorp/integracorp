<?php

namespace App\Http\Controllers;

use App\Models\BusinessAppointments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BusinessAppointmentsController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validación de datos
        $validator = Validator::make($request->all(), [
            'legal_name' => 'required|string|max:255',
            'phone'      => 'required|string|max:20',
            'email'      => 'required|email|max:255',
            'country_id' => 'required|exists:countries,id',
            'state_id'   => 'required|exists:states,id',
            'city_id'    => 'required|exists:cities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Datos inválidos',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            // 2. Guardado en la base de datos
            $cita = BusinessAppointments::create([
                'legal_name' => $request->legal_name,
                'phone'      => $request->phone,
                'email'      => $request->email,
                'country_id' => $request->country_id,
                'state_id'   => $request->state_id,
                'city_id'    => $request->city_id,
                'status'     => 'PENDIENTE', // Estado por defecto
                'created_by' => $request->legal_name,
            ]);

            // 3. Respuesta de éxito
            return response()->json([
                'status'  => 'success',
                'message' => 'Cita registrada correctamente',
                'data'    => $cita
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No se pudo guardar la información.'
            ], 500);
        }
    }
}
