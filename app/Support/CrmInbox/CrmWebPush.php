<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\CrmPushSubscription;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Entrega un aviso al navegador con VAPID y cifrado aes128gcm.
 * No usa la cola ni la base de afiliaciones.
 */
final class CrmWebPush
{
    public function send(CrmPushSubscription $subscription, string $json): string
    {
        $publicKey = trim((string) config('crm-inbox.push_public_key', ''));
        $privateKey = trim((string) config('crm-inbox.push_private_key', ''));

        if ($publicKey === '' || $privateKey === '' || $subscription->content_encoding !== 'aes128gcm') {
            return 'unconfigured';
        }

        $uaPublic = self::decode($subscription->public_key);
        $auth = self::decode($subscription->auth_token);

        if ($uaPublic === '' || $auth === '') {
            return 'unconfigured';
        }

        try {
            $body = $this->seal($json, $uaPublic, $auth);
            $authorization = $this->authorization($subscription->endpoint, $publicKey, $privateKey);
        } catch (RuntimeException) {
            return 'failed';
        }

        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->withHeaders([
                    'TTL' => '43200',
                    'Urgency' => 'high',
                    'Topic' => substr(hash('sha256', (string) $subscription->id), 0, 16),
                    'Content-Encoding' => 'aes128gcm',
                    'Authorization' => $authorization,
                ])
                ->withBody($body, 'application/octet-stream')
                ->post($subscription->endpoint);
        } catch (Throwable) {
            return 'failed';
        }

        if (in_array($response->status(), [404, 410], true)) {
            return 'gone';
        }

        if ($response->successful()) {
            return 'sent';
        }

        return 'failed';
    }

    public function seal(string $payload, string $uaPublic, string $auth): string
    {
        $local = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($local === false) {
            throw new RuntimeException('No se pudo crear la clave del aviso.');
        }

        $details = openssl_pkey_get_details($local);

        if (! is_array($details) || ! isset($details['ec']['x'], $details['ec']['y'])) {
            throw new RuntimeException('No se pudo leer la clave del aviso.');
        }

        $localPublic = chr(4).self::coordinate($details['ec']['x']).self::coordinate($details['ec']['y']);
        $secret = openssl_pkey_derive(self::publicPem($uaPublic), $local, 32);

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('No se pudo acordar la clave del aviso.');
        }

        $salt = random_bytes(16);
        $ikm = hash_hkdf('sha256', $secret, 32, "WebPush: info\0".$uaPublic.$localPublic, $auth);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $tag = '';
        $cipher = openssl_encrypt($payload.chr(2), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

        if (! is_string($cipher) || ! is_string($tag)) {
            throw new RuntimeException('No se pudo cifrar el aviso.');
        }

        return $salt.pack('N', 4096).chr(strlen($localPublic)).$localPublic.$cipher.$tag;
    }

    private function authorization(string $endpoint, string $publicKey, string $privateKey): string
    {
        $parts = parse_url($endpoint);
        $scheme = is_array($parts) ? (string) ($parts['scheme'] ?? '') : '';
        $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';

        if ($scheme === '' || $host === '') {
            throw new RuntimeException('El destino del aviso no es válido.');
        }

        $audience = $scheme.'://'.$host;

        if (is_array($parts) && isset($parts['port'])) {
            $audience .= ':'.$parts['port'];
        }

        $header = self::encode((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_THROW_ON_ERROR));
        $claims = self::encode((string) json_encode([
            'aud' => $audience,
            'exp' => time() + 43200,
            'sub' => (string) config('crm-inbox.push_subject', 'mailto:crm@tudrencasa.com'),
        ], JSON_THROW_ON_ERROR));
        $input = $header.'.'.$claims;
        $pem = self::privatePem($privateKey);
        $key = openssl_pkey_get_private($pem);
        $der = '';

        if ($key === false || ! openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar el aviso.');
        }

        return 'vapid t='.$input.'.'.self::encode(self::rawSignature($der)).',k='.$publicKey;
    }

    private static function privatePem(string $stored): string
    {
        $decoded = self::decode($stored);

        if (str_contains($decoded, 'BEGIN')) {
            return $decoded;
        }

        if (str_contains($stored, 'BEGIN')) {
            return $stored;
        }

        throw new RuntimeException('La clave privada del aviso no es válida.');
    }

    private static function publicPem(string $uncompressed): string
    {
        $prefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');

        if ($prefix === false) {
            throw new RuntimeException('No se pudo preparar la clave del navegador.');
        }

        $body = chunk_split(base64_encode($prefix.$uncompressed), 64, "\n");

        return "-----BEGIN PUBLIC KEY-----\n".$body."-----END PUBLIC KEY-----\n";
    }

    private static function coordinate(string $value): string
    {
        return str_pad(substr($value, -32), 32, "\0", STR_PAD_LEFT);
    }

    private static function rawSignature(string $der): string
    {
        $offset = 3;
        $rLength = ord($der[$offset]);
        $r = substr($der, $offset + 1, $rLength);
        $offset += 1 + $rLength + 1;
        $sLength = ord($der[$offset]);
        $s = substr($der, $offset + 1, $sLength);

        return self::coordinate($r).self::coordinate($s);
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string
    {
        $padding = str_repeat('=', (4 - (strlen($value) % 4)) % 4);
        $decoded = base64_decode(strtr($value.$padding, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : '';
    }
}
