<?php

declare(strict_types=1);

namespace App\Http\Controllers\Business;

use App\Support\Companies\CompanyAssociateDocumentsBellAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DatabaseNotificationBellAlertController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['alert' => false]);
        }

        return response()->json([
            'alert' => CompanyAssociateDocumentsBellAlert::consume((int) $user->getKey()),
        ]);
    }
}
