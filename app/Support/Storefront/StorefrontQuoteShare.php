<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Jobs\SendStorefrontQuoteShareJob;
use App\Models\IndividualQuote;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Destinatarios y envío de la cotización desde la PWA.
 *
 * @phpstan-type Recipient array{channel: string, value: string}
 */
final class StorefrontQuoteShare
{
    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const MAX_RECIPIENTS = 8;

    public const LAST_CODE_COOKIE = 'storefront_last_quote';

    /**
     * @return list<Recipient>
     */
    public static function seedFromQuote(IndividualQuote $quote): array
    {
        $phone = preg_replace('/\D+/', '', (string) $quote->phone) ?? '';

        return [self::emptyRecipient(self::CHANNEL_WHATSAPP, $phone)];
    }

    /**
     * @return Recipient
     */
    public static function emptyRecipient(string $channel = self::CHANNEL_WHATSAPP, string $value = ''): array
    {
        $resolved = self::channel($channel);

        return [
            'channel' => $resolved,
            'value' => $resolved === self::CHANNEL_EMAIL
                ? mb_strtolower(trim($value))
                : (preg_replace('/\D+/', '', $value) ?? ''),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<Recipient>
     */
    public static function normalize(array $rows): array
    {
        if (count($rows) > self::MAX_RECIPIENTS) {
            throw ValidationException::withMessages([
                'recipients' => ['Puedes enviar a un máximo de '.self::MAX_RECIPIENTS.' destinatarios.'],
            ]);
        }

        $clean = [];
        $errors = [];

        foreach (array_values($rows) as $index => $row) {
            $channel = self::channel($row['channel'] ?? null);
            $value = trim((string) ($row['value'] ?? ''));

            if ($value === '') {
                continue;
            }

            if ($channel === self::CHANNEL_EMAIL) {
                $email = mb_strtolower($value);

                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $errors['recipients.'.$index.'.value'] = 'El correo no es válido.';

                    continue;
                }

                $clean[] = [
                    'channel' => self::CHANNEL_EMAIL,
                    'value' => $email,
                ];

                continue;
            }

            $digits = preg_replace('/\D+/', '', $value) ?? '';

            if (strlen($digits) < 10) {
                $errors['recipients.'.$index.'.value'] = 'El teléfono debe tener al menos 10 dígitos.';

                continue;
            }

            $clean[] = [
                'channel' => self::CHANNEL_WHATSAPP,
                'value' => $digits,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $unique = [];
        $seen = [];

        foreach ($clean as $row) {
            $key = $row['channel'].'|'.$row['value'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $row;
        }

        if ($unique === []) {
            throw ValidationException::withMessages([
                'recipients' => ['Agrega al menos un teléfono o un correo.'],
            ]);
        }

        return $unique;
    }

    /**
     * @param  list<Recipient>  $recipients
     */
    public static function queue(string $code, array $recipients): int
    {
        $link = self::proposalUrl($code);
        $count = 0;

        foreach ($recipients as $row) {
            SendStorefrontQuoteShareJob::dispatch(
                $code,
                $row['channel'],
                $row['value'],
                $link,
            );
            $count++;
        }

        return $count;
    }

    public static function proposalPath(string $code): string
    {
        return '/app/cotizacion/'.rawurlencode(trim($code)).'/propuesta';
    }

    public static function proposalUrl(string $code): string
    {
        try {
            return route('storefront.quote.proposal', ['code' => $code], true);
        } catch (Throwable) {
            return self::proposalPath($code);
        }
    }

    public static function negociosPhone(?string $phone = null): string
    {
        $raw = $phone;

        if ($raw === null) {
            try {
                $raw = (string) config('services.chat_agent_registration.business_whatsapp_phone', '584127018390');
            } catch (Throwable) {
                $raw = '584127018390';
            }
        }

        $digits = preg_replace('/\D+/', '', $raw) ?: '584127018390';

        return $digits;
    }

    public static function negociosWhatsAppUrl(string $code = '', ?string $phone = null): string
    {
        $digits = self::negociosPhone($phone);
        $text = $code === ''
            ? 'Hola, quiero hablar con un asesor de negocios.'
            : 'Hola, quiero hablar con un asesor de negocios sobre la cotización '.$code.'.';

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }

    public static function rememberCode(string $code): void
    {
        $clean = trim($code);

        if ($clean === '' || preg_match('/^[A-Za-z0-9\-]+$/', $clean) !== 1) {
            return;
        }

        try {
            cookie()->queue(cookie(self::LAST_CODE_COOKIE, $clean, 60 * 24 * 30, '/app'));
        } catch (Throwable) {
        }
    }

    public static function lastCode(): ?string
    {
        try {
            $code = trim((string) request()->cookie(self::LAST_CODE_COOKIE, ''));
        } catch (Throwable) {
            return null;
        }

        if ($code === '' || preg_match('/^[A-Za-z0-9\-]+$/', $code) !== 1) {
            return null;
        }

        return $code;
    }

    public static function channel(mixed $value): string
    {
        return (string) $value === self::CHANNEL_EMAIL
            ? self::CHANNEL_EMAIL
            : self::CHANNEL_WHATSAPP;
    }
}
