<?php

declare(strict_types=1);

namespace App\Support\Birthday;

use App\Enums\BirthdayCouponType;
use App\Models\BirthdayCouponToken;

final class BirthdayCouponWhatsAppMessages
{
    /**
     * @var array<string, string>
     */
    private const PHONES = [
        'viajes' => '0424-222-0056',
        'farmadoc' => '0424-287-8918',
        'kit' => '0424-227-1498',
        'casa' => '0424-227-1498',
    ];

    public static function forToken(BirthdayCouponToken $token): string
    {
        $verifyUrl = route('birthday.coupon.show', ['token' => $token->public_token]);

        return match ($token->coupon_type) {
            BirthdayCouponType::Viajes => "Hola, vengo del cupón de cumpleaños de Tu Doctor en Viajes (15% de descuento en mi próximo plan de asistencia en viajes). Quisiera activarlo o recibir más información.\n\nValidación del cupón: {$verifyUrl}",
            BirthdayCouponType::Farmadoc => "Hola, vengo del cupón de cumpleaños Farmadoc (hasta 15% en medicamentos, vitaminas, suplementos y cuidado personal). Quisiera hacer mi pedido o recibir más información.\n\nValidación del cupón: {$verifyUrl}",
            BirthdayCouponType::Kit => "Hola, vengo del cupón de cumpleaños de Tu Doctor Group (sorteo del kit corporativo del cumpleañero del mes). Quisiera inscribirme o saber cómo participar.\n\nValidación del cupón: {$verifyUrl}",
            BirthdayCouponType::Casa => "Hola, vengo del cupón referidor de Tu Doctor en Casa (10% en planes para familia y amigos). Quisiera regalar salud o recibir más información.\n\nValidación del cupón: {$verifyUrl}",
        };
    }

    /**
     * @return list<array{type: string, whatsappMessage: string, verifyUrl: string, img: string, phone: string, publicToken: string, status: string}>
     */
    public static function payloadForAnimation(\Illuminate\Support\Collection $tokens): array
    {
        $imageByType = [
            BirthdayCouponType::Viajes->value => 'assets/cupon-viajes.jpg',
            BirthdayCouponType::Farmadoc->value => 'assets/cupon-farmadoc.jpg',
            BirthdayCouponType::Kit->value => 'assets/cupon-kit.jpg',
            BirthdayCouponType::Casa->value => 'assets/cupon-casa.jpg',
        ];

        return $tokens
            ->sortBy(fn (BirthdayCouponToken $token): int => match ($token->coupon_type) {
                BirthdayCouponType::Viajes => 0,
                BirthdayCouponType::Farmadoc => 1,
                BirthdayCouponType::Kit => 2,
                BirthdayCouponType::Casa => 3,
            })
            ->values()
            ->map(function (BirthdayCouponToken $token) use ($imageByType): array {
                $type = $token->coupon_type->value;

                return [
                    'type' => $type,
                    'img' => $imageByType[$type] ?? 'assets/cupon-viajes.jpg',
                    'phone' => self::PHONES[$type] ?? '',
                    'verifyUrl' => route('birthday.coupon.show', ['token' => $token->public_token]),
                    'whatsappMessage' => self::forToken($token),
                    'publicToken' => $token->public_token,
                    'status' => $token->status->value,
                ];
            })
            ->all();
    }
}
