<?php

declare(strict_types=1);

namespace App\Services\Farmadoc;

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
     * @return array{order_number: string, order_id: int|null, items_count: int, status: string, status_label: string}
     */
    public function placeOrder(array $items, ?int $branchId, ?string $notes, ?string $orderNumber = null): array
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

        $response = $this->send('post', '/api/external/orders', $payload);
        $data = $response->json('data');

        if (! is_array($data) || ! is_string($data['order_number'] ?? null) || $data['order_number'] === '') {
            throw new FarmadocException('Farmadoc no confirmó el número del pedido. Revise en la farmacia antes de enviarlo otra vez.');
        }

        $status = is_string($data['status'] ?? null) ? $data['status'] : 'pendiente';

        return [
            'order_number' => $data['order_number'],
            'order_id' => is_numeric($data['order_id'] ?? null) ? (int) $data['order_id'] : null,
            'items_count' => (int) ($data['items_count'] ?? count($items)),
            'status' => $status,
            'status_label' => $this->statusLabel($status),
        ];
    }

    /**
     * Estatus de un pedido del aliado. Farmadoc también devuelve montos; se descartan.
     *
     * @return array{order_number: string, order_id: int|null, status: string, items_count: int|null, updated_at: string|null}
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

        return [
            'order_number' => $data['order_number'],
            'order_id' => is_numeric($data['order_id'] ?? null) ? (int) $data['order_id'] : null,
            'status' => is_string($data['status'] ?? null) ? $data['status'] : 'pendiente',
            'items_count' => is_numeric($data['items_count'] ?? null) ? (int) $data['items_count'] : null,
            'updated_at' => is_string($data['updated_at'] ?? null) ? $data['updated_at'] : null,
        ];
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
    private function send(string $method, string $path, array $payload = []): Response
    {
        $request = Http::acceptJson()
            ->timeout($this->timeout())
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
        return match ($status) {
            'pendiente' => 'Pendiente',
            'en-proceso' => 'En proceso',
            'finalizado' => 'Finalizado',
            default => 'Pendiente',
        };
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
