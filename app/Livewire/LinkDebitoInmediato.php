<?php

namespace App\Livewire;

use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class LinkDebitoInmediato extends Component
{
    public $title = 'Link Debito Inmediato';

    public $first_name;
    public $last_name;
    public $document_type;
    public $ci;
    public $bank_id;
    public $account_number;

    public function mount()
    {
        $this->title = 'Link Debito Inmediato';
    }

    /**
     * Reglas de validación optimizadas al 100%
     */
    public function rules()
    {
        return [
            // No permite números ni espacios (solo letras)
            'first_name'     => ['required', 'regex:/^[a-zA-ZñÑáéíóúÁÉÍÓÚ]+$/u'],
            'last_name'      => ['required', 'regex:/^[a-zA-ZñÑáéíóúÁÉÍÓÚ]+$/u'],
            'document_type'  => 'required',
            // Solo números, sin espacios ni caracteres especiales
            'ci'             => ['required', 'numeric', 'regex:/^[0-9]+$/'],
            'bank_id'        => 'required',
            // Exactamente 20 dígitos numéricos, sin espacios ni letras
            'account_number' => ['required', 'numeric', 'digits:20', 'regex:/^[0-9]{20}$/'],
        ];
    }

    public function messages()
    {
        return [
            'first_name.required'    => 'El nombre es requerido.',
            'first_name.regex'       => 'El nombre solo debe contener letras, sin espacios.',
            'last_name.required'     => 'El apellido es requerido.',
            'last_name.regex'        => 'El apellido solo debe contener letras, sin espacios.',
            'document_type.required' => 'El tipo de documento es requerido.',
            'ci.required'            => 'La cédula es requerida.',
            'ci.numeric'             => 'La cédula debe ser solo números.',
            'ci.regex'               => 'La cédula no debe contener espacios ni caracteres especiales.',
            'bank_id.required'       => 'El banco es requerido.',
            'account_number.required' => 'El número de cuenta es requerido.',
            'account_number.numeric' => 'La cuenta debe contener solo números.',
            'account_number.digits'  => 'La cuenta debe tener exactamente 20 dígitos.',
            'account_number.regex'   => 'Formato de cuenta inválido (sin espacios ni letras).',
        ];
    }

    /**
     * Hook para limpiar los datos mientras el usuario escribe
     */
    public function updated($propertyName)
    {
        // Limpieza automática para CI y Account Number (remover todo lo que no sea número)
        if (in_array($propertyName, ['ci', 'account_number'])) {
            $this->$propertyName = preg_replace('/[^0-9]/', '', $this->$propertyName);
        }

        // Limpieza automática para nombres (remover números y espacios)
        if (in_array($propertyName, ['first_name', 'last_name'])) {
            $this->$propertyName = preg_replace('/[^a-zA-ZñÑáéíóúÁÉÍÓÚ]/u', '', $this->$propertyName);
        }

        $this->validateOnly($propertyName);
    }

    public function processPayment()
    {
        
        $this->validate();

        try {

            $cuenta             = $this->account_number;
            $commerceToken      = config('parameters.COMMERCE_TOKEN_R4');
            $url                = config('parameters.URL_R4_DOMICILIACIONES_CNTA');
            $tokenAuthorization = hash_hmac('sha256', $cuenta, $commerceToken);


            $headers = [
                'Content-Type: application/json',
                'Authorization: ' . $tokenAuthorization,
                'Commerce: ' . $commerceToken,
            ];

            $postData = [
                "docId"     => $this->document_type . $this->ci,
                "nombre"    => $this->first_name . ' ' . $this->last_name,
                "cuenta"    => $this->account_number,
                "monto"     => "100.00",
                "concepto"  => "Pago"
            ];


            $curl = curl_init($url);

            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_SSL_VERIFYPEER => true, // Verificar el certificado del servidor 
                CURLOPT_SSL_VERIFYHOST => 2,    // Verificar el hostname del certificado
            ]);

            /**
             * Manejo de errores de cURL
             * @version 2.0.0
             */
            $response = curl_exec($curl);

            if (curl_errno($curl)) {
                return Flux::toast(
                    heading: 'Error de conexión',
                    text: curl_error($curl),
                    variant: 'danger'
                );
            }

            $result = json_decode($response, true);
            curl_close($curl);

            if (!$result) {
                $this->reset();
                return Flux::toast(
                    heading: 'Error de conexión',
                    text: 'Respuesta del banco inválida.',
                    variant: 'danger'
                );
            }

            // Manejo de códigos según lógica del negocio
            if (isset($result['code']) && $result['code'] == '108') {
                $this->reset();
                return Flux::toast(
                    heading: 'Error en transacción',
                    text: $result['message'] ?? 'Código 108',
                    variant: 'danger'
                );
            }

            if (isset($result['codigo']) && $result['codigo'] == '202') {
                $this->reset();
                // Lógica de consulta de operación...
                $this->checkOperationStatus($result['uuid'], $commerceToken);

                return Flux::toast(
                    heading: 'Pago procesado con Exito',
                    text: 'Debe autorizar la operación en su portal bancario.',
                    variant: 'success'
                );
            }
            

        } catch (\Exception $e) {
            return Flux::toast(
                heading: 'Error',
                text: $e->getMessage(),
                variant: 'danger'
            );
        }

    }

    protected function checkOperationStatus($uuid, $commerceToken)
    {
        try {
            
            $url = 'https://r4conecta.mibanco.com.ve/ConsultarOperaciones';
            $tokenAuthorization = hash_hmac('sha256', $uuid, $commerceToken);

            $headers = [
                'Content-Type: application/json',
                'Authorization: ' . $tokenAuthorization,
                'Commerce: ' . $commerceToken,
            ];

            $postData = ["id" => $uuid];

            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            $response = curl_exec($curl);
            $result = json_decode($response, true);
            curl_close($curl);

            Log::info("Estatus Operación $uuid:", $result ?? []);
        } catch (\Exception $e) {
            Log::error("Error consultando estatus de operación $uuid:", [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
            ]);
        }
    }

    public function render()
    {
        return view('livewire.link-debito-inmediato');
    }
}
