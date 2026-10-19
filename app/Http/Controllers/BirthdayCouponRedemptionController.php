<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Birthday\BirthdayCelebrationAccessIssuer;
use App\Support\Birthday\BirthdayCouponRedemptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class BirthdayCouponRedemptionController extends Controller
{
    public function __construct(
        private readonly BirthdayCouponRedemptionService $redemptionService,
    ) {}

    public function show(string $token): View
    {
        $coupon = BirthdayCelebrationAccessIssuer::findCouponByPublicToken($token);

        if ($coupon === null) {
            abort(404, 'Cupón no encontrado.');
        }

        $coupon = $this->redemptionService->syncExpiredStatus($coupon);
        $access = $coupon->celebrationAccess;

        if ($access === null || $access->isExpired()) {
            abort(404, 'Cupón no encontrado o expirado.');
        }

        return view('public.birthday-coupon-redemption', [
            'coupon' => $coupon,
            'access' => $access,
        ]);
    }

    public function redeem(Request $request, string $token): RedirectResponse
    {
        $coupon = BirthdayCelebrationAccessIssuer::findCouponByPublicToken($token);

        if ($coupon === null) {
            abort(404, 'Cupón no encontrado.');
        }

        $user = Auth::user();
        if ($user === null) {
            abort(403);
        }

        try {
            $this->redemptionService->redeem($coupon, $user, $request->ip());
        } catch (ValidationException $exception) {
            return redirect()
                ->route('birthday.coupon.show', ['token' => $token])
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('birthday.coupon.show', ['token' => $token])
            ->with('birthday_coupon_notice', 'Cupón marcado como usado correctamente.');
    }
}
