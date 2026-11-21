<?php

declare(strict_types=1);

namespace App\Services\Farmadoc;

use App\Models\FarmadocOrder;
use App\Support\Farmadoc\FarmadocException;
use App\Support\Farmadoc\FarmadocMedicationList;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente del API de aliados de Farmadoc.
 * El listado es solo el catálogo de Las Delicias, sin precio ni existencia.
 */
final class FarmadocAllyClient
{
    /** Segundos para descargar la nota de entrega (PDF de ~1 MB). */
    public const DELIVERY_NOTE_TIMEOUT = 30;

    /** Tamaño máximo aceptado de la nota de entrega. */
    public const DELIVERY_NOTE_MAX_BYTES = 20 * 1024 * 1024;

    /** Medicamentos máximos que se guardan del detalle de una aceptación. */
    public const ACCEPTANCE_MAX_ITEMS = 200;

    /**
     * @return array{
     *     logo_url: string|null,
     *     branch_id: int|null,
     *     branch_name: string|null,
     *     medications: list<array<string, mixed>>
     * }
     */
    public function catalog(): array
    {
        $this->assertConfigured(requireClient: false);
        $this->assertActive();

        $response = $this->send('get', '/api/external/inventory/las-delicias', [
            'partner_company' => $this->partnerCompany(),
        ]);

        $branch = $response->json('branch');
        $rows = $response->json('data');

        return [
            'logo_url' => is_string($response->json('logo_url')) ? $response->json('logo_url') : null,
            'branch_id' => is_array($branch) && is_numeric($branch['id'] ?? null) ? (int) $branch['id'] : null,
            'branch_name' => is_array($branch) && is_string($branch['name'] ?? null) ? $branch['name'] : null,
            'medications' => FarmadocMedicationList::fromApiRows(is_array($rows) ? $rows : []),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>|null  $serviceOrder
     * @return array{order_number: string, order_id: int|null, items_count: int, status: string, status_label: string, service_order_number: string|null, service_order_id: int|null}
     */
    public function placeOrder(array $items, ?int $branchId, ?string $notes, ?string $orderNumber = null, ?array $serviceOrder = null): array
    {
        $this->assertConfigured(requireClient: true);

        $payload = [
            'partner_company' => $this->partnerCompany(),
            'client_id' => $this->clientId(),
            'status' => 'pendiente',
            'items' => array_map(function (array $item): array {
                return [
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'quantity' => (int) ($item['quantity'] ?? 0),
                    'unit_price' => 0.0,
                    'product_name_snapshot' => is_string($item['product_name_snapshot'] ?? null)
                        ? $item['product_name_snapshot']
                        : null,
                    'sku_snapshot' => is_string($item['sku_snapshot'] ?? null) ? $item['sku_snapshot'] : null,
                ];
            }, $items),
        ];

        if ($branchId !== null && $branchId > 0) {
            $payload['branch_id'] = $branchId;
        }

        if (is_string($notes) && trim($notes) !== '') {
            $payload['notes'] = trim($notes);
        }

        if (is_string($orderNumber) && $orderNumber !== '') {
            $payload['order_number'] = $orderNumber;
        }

        if (is_array($serviceOrder)) {
            $payload['service_order'] = $serviceOrder;
        }

        $response = $this->send('post', '/api/external/orders', $payload);
        $data = $response->json('data');

        if (! is_array($data) || ! is_string($data['order_number'] ?? null) || $data['order_number'] === '') {
            throw new FarmadocException('Farmadoc no confirmó el número del pedido. Revise en la farmacia antes de enviarlo otra vez.');
        }

        $status = is_string($data['status'] ?? null) ? $data['status'] : 'pendiente';
        $service = is_array($data['service_order'] ?? null) ? $data['service_order'] : [];

        return [
            'order_number' => $data['order_number'],
            'order_id' => is_numeric($data['order_id'] ?? null) ? (int) $data['order_id'] : null,
            'items_count' => (int) ($data['items_count'] ?? count($items)),
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'service_order_number' => is_string($service['service_order_number'] ?? null) && $service['service_order_number'] !== ''
                ? $service['service_order_number']
                : null,
            'service_order_id' => is_numeric($service['order_service_id'] ?? null) ? (int) $service['order_service_id'] : null,
        ];
    }

    /**
     * Estatus de un pedido del aliado. Farmadoc también devuelve montos; se descartan.
     *
     * @return array{order_number: string, order_id: int|null, status: string, items_count: int|null, updated_at: string|null, delivery_note_number: string|null, accepted_at: string|null, accepted_by: string|null, acceptance_items: list<array{name: string, barcode: string|null, sku: string|null, quantity: int, accepted: bool, rejection_note: string|null}>|null}
     */
    public function orderStatus(string $orderNumber): array
    {
        $this->assertConfigured(requireClient: false);

        $orderNumber = trim($orderNumber);

        if ($orderNumber === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,254}$/', $orderNumber) !== 1) {
            throw new FarmadocException('El número de pedido no es válido.');
        }

        $response = $this->send('get', '/api/external/orders/'.rawurlencode($orderNumber), [
            'partner_company' => $this->partnerCompany(),
        ]);

        $data = $response->json('data');

        if (! is_array($data) || ! is_string($data['order_number'] ?? null)) {
            throw new FarmadocException('Farmadoc no devolvió el estatus del pedido.');
        }

        // De la aceptación solo se toman la nota de entrega y la fecha: nunca montos.
        $acceptance = is_array($data['acceptance'] ?? null) ? $data['acceptance'] : [];

        return [
            'order_number' => $data['order_number'],
            'order_id' => is_numeric($data['order_id'] ?? null) ? (int) $data['order_id'] : null,
            'status' => is_string($data['status'] ?? null) ? $data['status'] : 'pendiente',
            'items_count' => is_numeric($data['items_count'] ?? null) ? (int) $data['items_count'] : null,
            'updated_at' => is_string($data['updated_at'] ?? null) ? $data['updated_at'] : null,
            'delivery_note_number' => is_string($acceptance['delivery_note_number'] ?? null) ? $acceptance['delivery_note_number'] : null,
            'accepted_at' => is_string($acceptance['accepted_at'] ?? null) ? $acceptance['accepted_at'] : null,
            'accepted_by' => is_string($acceptance['accepted_by'] ?? null) ? $acceptance['accepted_by'] : null,
            'acceptance_items' => $this->acceptanceItems($acceptance['items'] ?? null),
        ];
    }

    /**
     * Detalle por medicamento de la aceptación, sin precios ni existencia de Farmadoc.
     *
     * @return list<array{name: string, barcode: string|null, sku: string|null, quantity: int, accepted: bool, rejection_note: string|null}>|null
     */
    private function acceptanceItems(mixed $items): ?array
    {
        if (! is_array($items) || ! array_is_list($items)) {
            return null;
        }

        $lines = [];

        foreach (array_slice($items, 0, self::ACCEPTANCE_MAX_ITEMS) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $lines[] = [
                'name' => self::cleanText($item['product_name'] ?? null, 255) ?? 'Medicamento sin nombre',
                'barcode' => self::cleanText($item['barcode'] ?? null, 64),
                'sku' => self::cleanText($item['sku'] ?? null, 100),
                'quantity' => is_numeric($item['quantity_ordered'] ?? null) ? max(0, (int) $item['quantity_ordered']) : 0,
                'accepted' => filter_var($item['accepted'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'rejection_note' => self::cleanText($item['rejection_note'] ?? null, 500),
            ];
        }

        return $lines;
    }

    private static function cleanText(mixed $value, int $length): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $length);
    }

    /**
     * PDF de la nota de entrega de un pedido aceptado. Farmadoc solo la entrega con el
     * token del aliado, por eso se descarga desde aquí y nunca se expone su enlace.
     *
     * @return array{content: string, filename: string}
     *
     * @throws FarmadocException
     */
    public function deliveryNote(string $orderNumber): array
    {
        $this->assertConfigured(requireClient: false);

        $orderNumber = trim($orderNumber);

        if ($orderNumber === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,254}$/', $orderNumber) !== 1) {
            throw new FarmadocException('El número de pedido no es válido.');
        }

        $response = $this->send(
            'get',
            '/api/external/orders/'.rawurlencode($orderNumber).'/nota-de-entrega',
            ['partner_company' => $this->partnerCompany()],
            accept: 'application/pdf',
            timeout: max(self::DELIVERY_NOTE_TIMEOUT, $this->timeout()),
        );

        $content = $response->body();

        if (! str_starts_with($content, '%PDF-') || strlen($content) > self::DELIVERY_NOTE_MAX_BYTES) {
            Log::warning('Farmadoc devolvió una nota de entrega que no es un PDF válido', [
                'order_number' => $orderNumber,
                'content_type' => $response->header('Content-Type'),
                'bytes' => strlen($content),
            ]);

            throw new FarmadocException('Farmadoc no devolvió la nota de entrega. Intente de nuevo en unos minutos.');
        }

        return ['content' => $content, 'filename' => $this->deliveryNoteFilename($response, $orderNumber)];
    }

    private function deliveryNoteFilename(Response $response, string $orderNumber): string
    {
        $disposition = (string) $response->header('Content-Disposition');

        if (preg_match('/filename="?([^";]+)"?/i', $disposition, $matches) === 1) {
            $name = basename(trim($matches[1]));

            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,150}\.pdf$/i', $name) === 1) {
                return $name;
            }
        }

        return 'nota-entrega-'.$orderNumber.'.pdf';
    }

    private function assertActive(): void
    {
        $response = $this->send('get', '/api/external/status');

        if ($response->json('status') !== 'active') {
            throw new FarmadocException('Farmadoc no está disponible en este momento. Intente de nuevo en unos minutos.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $method, string $path, array $payload = [], ?string $accept = null, ?int $timeout = null): Response
    {
        $request = ($accept === null ? Http::acceptJson() : Http::accept($accept))
            ->timeout($timeout ?? $this->timeout())
            ->connectTimeout(5);

        if ($path !== '/api/external/status') {
            $token = $this->token();

            if ($token === null) {
                throw new FarmadocException('La integración con Farmadoc no está configurada.');
            }

            $request = $request->withToken($token);
        }

        try {
            $response = $method === 'post'
                ? $request->post($this->url($path), $payload)
                : $request->get($this->url($path), $payload);
        } catch (ConnectionException $exception) {
            Log::warning('Farmadoc no respondió', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);

            throw new FarmadocException('No se pudo contactar a Farmadoc. Verifique la conexión e intente de nuevo.');
        }

        if ($response->successful()) {
            return $response;
        }

        Log::warning('Farmadoc rechazó la solicitud', [
            'path' => $path,
            'status' => $response->status(),
            'message' => $response->json('message'),
        ]);

        throw new FarmadocException($this->failureMessage($response));
    }

    private function failureMessage(Response $response): string
    {
        return match ($response->status()) {
            401 => 'Farmadoc rechazó la credencial del aliado.',
            403 => 'Farmadoc no autorizó esta conexión.',
            404 => $this->responseMessage($response) ?? 'Farmadoc no encontró el recurso solicitado.',
            422 => $this->responseMessage($response) ?? 'Farmadoc no aceptó el pedido. Revise los medicamentos y las cantidades.',
            default => 'Farmadoc no pudo completar la operación. Intente de nuevo.',
        };
    }

    private function responseMessage(Response $response): ?string
    {
        $errors = $response->json('errors');

        if (is_array($errors)) {
            foreach ($errors as $messages) {
                $first = is_array($messages) ? ($messages[0] ?? null) : $messages;

                if (is_string($first) && trim($first) !== '') {
                    return trim($first);
                }
            }
        }

        $message = $response->json('message');

        return is_string($message) && trim($message) !== '' ? trim($message) : null;
    }

    private function statusLabel(string $status): string
    {
        return FarmadocOrder::statusLabel(FarmadocOrder::normalizeStatus($status));
    }

    private function assertConfigured(bool $requireClient): void
    {
        if ($this->partnerCompany() === '' || $this->token() === null) {
            throw new FarmadocException('La integración con Farmadoc no está configurada.');
        }

        if ($requireClient && $this->clientId() < 1) {
            throw new FarmadocException('La integración con Farmadoc no tiene el cliente al que se cargan los pedidos.');
        }
    }

    private function url(string $path): string
    {
        return $this->baseUrl().$path;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.farmadoc.base_url'), '/');
    }

    private function token(): ?string
    {
        $token = config('services.farmadoc.token');

        return is_string($token) && trim($token) !== '' ? trim($token) : null;
    }

    private function partnerCompany(): string
    {
        return trim((string) config('services.farmadoc.partner_company'));
    }

    private function clientId(): int
    {
        return (int) config('services.farmadoc.client_id');
    }

    private function timeout(): int
    {
        $timeout = (int) config('services.farmadoc.timeout');

        return $timeout > 0 ? $timeout : 20;
    }
}
