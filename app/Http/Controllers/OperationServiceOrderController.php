<?php

namespace App\Http\Controllers;

use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderItem;
use App\Models\TelemedicinePatient;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\OperationServiceOrderProviderSelection;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OperationServiceOrderController extends Controller
{
    //
    public static function create(array $data, array $ownerRecord, Collection $records): bool
    {
        $providerError = OperationServiceOrderProviderSelection::validationMessage($data);

        if ($providerError !== null) {
            Notification::make()
                ->title('Proveedor de la orden')
                ->body($providerError)
                ->warning()
                ->send();

            return false;
        }

        $providers = OperationServiceOrderProviderSelection::resolveProviders($data);
        $data['doctor_nurse_id'] = $providers['doctor_nurse_id'];
        $data['supplier_id'] = $providers['supplier_id'];
        $data['supplier_external'] = $providers['supplier_external'];

        DB::transaction(function () use ($data, $ownerRecord, $records) {
            try {
                $medicationsList = $data['medications_list'] ?? [];
                $serviceType = $data['service_type'] ?? null;
                $totalItems = $records->count();
                $totalItemsUnit = 0;
                $indexForSum = 0;
                foreach ($records as $_record) {
                    if ($serviceType === 'MEDICAMENTOS') {
                        $line = $medicationsList[$indexForSum] ?? [];
                        $totalItemsUnit += max(1, (int) ($line['quantity'] ?? 1));
                    } else {
                        $totalItemsUnit += 1;
                    }
                    $indexForSum++;
                }

                $operationServiceOrder = OperationServiceOrder::create([
                    'operation_coordination_service_id' => $ownerRecord['id'],
                    'supplier_id' => $data['supplier_id'] ?? null,
                    'telemedicine_supplier_id' => OperationsSupplierScope::resolveTelemedicineSupplierIdFromCoordination($ownerRecord),
                    'managed_by' => OperationsSupplierScope::managedByFromCoordination($ownerRecord),
                    'doctor_nurse_id' => $data['doctor_nurse_id'] ?? null,
                    'supplier_external' => $data['supplier_external'] ?? null,
                    'operation_inventory_ubication_id' => $data['operation_inventory_ubication_id'] ?? null,
                    'telemedicine_priority_id' => $data['telemedicine_priority_id'] ?? null,
                    'description' => $data['description'] ?? null,
                    'service_type' => $serviceType,
                    'currency' => $data['currency'] ?? null,
                    'tasa_bcv' => $data['tasa_bcv'] ?? null,
                    'total_amount_usd' => $data['total_amount_usd'] ?? null,
                    'total_amount_ves' => $data['total_amount_ves'] ?? null,
                    'status' => $data['status'] ?? 'EN GESTION',
                    'approved_at' => now(),
                    'observations' => $data['observations'] ?? null,
                    'order_number' => $data['order_number'],
                    'total_items' => $totalItems,
                    'total_items_unit' => $totalItemsUnit,
                    'created_by' => $data['created_by'] ?? Auth::user()->name,
                    'updated_by' => $data['updated_by'] ?? Auth::user()->name,
                ]);

                $index = 0;

                if (($data['service_type'] ?? null) === 'MEDICAMENTOS') {
                    $records->loadMissing('operationInventory');
                }

                foreach ($records as $record) {
                    $line = $medicationsList[$index] ?? [];

                    if ($operationServiceOrder['service_type'] == 'MEDICAMENTOS') {
                        $item_name = $record->medicine ?? null;
                        $category = 'MEDICAMENTOS';
                        $dosage_instruction = is_string($line['indications'] ?? null)
                            ? $line['indications']
                            : ($record->indications ?? null);
                        $quantity = max(1, (int) ($line['quantity'] ?? 1));
                        $item_unit = $record->operationInventory?->unit;
                    } elseif ($operationServiceOrder['service_type'] == 'LABORATORIOS') {
                        $item_name = $record->laboratory ?? null;
                        $category = 'LABORATORIOS';
                        $dosage_instruction = null;
                        $quantity = 1;
                        $item_unit = null;
                    } elseif ($operationServiceOrder['service_type'] == 'IMAGENOLOGIA') {
                        $item_name = $record->study ?? null;
                        $category = 'IMAGENOLOGIA';
                        $dosage_instruction = null;
                        $quantity = 1;
                        $item_unit = null;
                    } elseif ($operationServiceOrder['service_type'] == 'ESPECIALISTA') {
                        $item_name = $record->specialty ?? null;
                        $category = 'ESPECIALISTA';
                        $dosage_instruction = null;
                        $quantity = 1;
                        $item_unit = null;
                    } else {
                        $item_name = null;
                        $category = 'OTRO';
                        $dosage_instruction = null;
                        $quantity = 1;
                        $item_unit = null;
                    }

                    OperationServiceOrderItem::create([
                        'operation_service_order_id' => $operationServiceOrder['id'],
                        'item_name' => $item_name,
                        'category' => $category,
                        'dosage_instruction' => $dosage_instruction,
                        'item_unit' => $item_unit ?? null,
                        'quantity' => $quantity,
                        'created_by' => Auth::user()->name,
                        'updated_by' => Auth::user()->name,
                    ]);

                    $index++;
                }
                if ($operationServiceOrder) {
                    Notification::make()
                        ->title('ORDEN DE SERVICIO')
                        ->body('La orden de servicio se ha creado correctamente.')
                        ->success()
                        ->send();

                    return true;
                }
            } catch (\Throwable $th) {
                Log::error('Error al crear la orden de servicio: '.$th->getMessage());
                Notification::make()
                    ->title('ORDEN DE SERVICIO')
                    ->body('Error al crear la orden de servicio.')
                    ->body($th->getMessage())
                    ->danger()
                    ->send();
                throw $th;
            }
        });

        return true;
    }

    public static function createOrderServiceInFramasysDoc(array $data, array $ownerRecord, Collection $records)
    {
        /**
         * POST /api/external/service-orders
         * Body: partner_company (code), paciente, diagnosis, items[name+indicacion]
         */
        $baseUrl = 'https://farmasysdoc.test';
        $token = 'fd_fbc8ee7f648919e2f366d500be1aa3bc709202a2f34a156b339a084c8f3c53cf';

        $patient = TelemedicinePatient::where('id', $ownerRecord['telemedicine_patient_id'])->first()->toArray();

        $items = [];
        foreach ($records as $record) {
            $items[] = [
                'name' => $record->medicine ?? null,
                'indicacion' => $record->indications ?? null,
            ];
        }

        $payload = [
            'partner_company' => 'ALDO-2026-005',
            'status' => 'en-proceso',
            'priority' => 'media',
            'service_type' => 'consulta',
            'external_reference' => 'EXT-'.$ownerRecord['reference_number'],
            'patient_name' => $ownerRecord['patient'],
            'patient_document' => $patient['nro_identificacion'],
            'patient_phone' => $patient['phone'],
            'patient_email' => $patient['email'],
            'diagnosis' => $ownerRecord['symptoms_diagnosis'],
            'items' => $items,
        ];

        $url = rtrim($baseUrl, '/').'/api/external/service-orders';
        // dd($url);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer '.$token,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $decodedBody = json_decode($body ?: '', true);

        return response()->json([
            'http_status' => $status,
            'response' => is_array($decodedBody) ? $decodedBody : $body,
        ], $status ?: 200);
    }
}
