<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Controllers\LogController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SecurityAudit
{
    /**
     * @param  array<string, mixed>  $details
     */
    public static function log(string $action, string $route, array $details = []): void
    {
        $user = Auth::user();

        $payload = [
            'trace_id' => (string) Str::uuid(),
            'timestamp' => now()->toIso8601String(),
            'user' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
            ],
            'request' => [
                'ip' => request()->ip(),
                'method' => request()->method(),
                'url' => request()->fullUrl(),
                'user_agent' => request()->userAgent(),
            ],
            'details' => $details,
        ];

        $response = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        LogController::log(
            user_id: (int) ($user?->id ?? 0),
            action: $action,
            route: $route,
            response: Str::limit((string) $response, 9000),
        );
    }
}
