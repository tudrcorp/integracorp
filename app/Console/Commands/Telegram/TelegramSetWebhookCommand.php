<?php

declare(strict_types=1);

namespace App\Console\Commands\Telegram;

use App\Support\Telegram\TelegramApiException;
use App\Support\Telegram\TelegramBotClient;
use App\Support\Telegram\TelegramHealth;
use App\Support\Telegram\TelegramUpdateHandler;
use Illuminate\Console\Command;

/**
 * Registra en Telegram la URL del webhook con su clave secreta. Se corre una vez
 * por servidor (y cada vez que cambie el dominio, el token o la clave).
 */
class TelegramSetWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook
        {--url= : URL pública del webhook (por defecto, la ruta telegram.webhook con APP_URL)}
        {--drop-pending : Descarta los mensajes que Telegram tenga acumulados}
        {--delete : Quita el webhook (para usar telegram:poll en local)}';

    protected $description = 'Registra (o quita) el webhook del bot de Telegram con su clave secreta';

    public function handle(): int
    {
        $client = TelegramBotClient::make();

        if (! $client->isConfigured()) {
            $this->error('Falta TELEGRAM_BOT_TOKEN en el .env.');

            return self::FAILURE;
        }

        try {
            if ($this->option('delete')) {
                $client->deleteWebhook((bool) $this->option('drop-pending'));
                TelegramHealth::forget();
                $this->info('Webhook eliminado. Ahora puede usar php artisan telegram:poll.');

                return self::SUCCESS;
            }

            $secret = (string) config('telegram.webhook_secret', '');

            if (strlen($secret) < 16 || ! preg_match('/^[A-Za-z0-9_-]{16,256}$/', $secret)) {
                $this->error('TELEGRAM_WEBHOOK_SECRET debe tener entre 16 y 256 caracteres (letras, números, «_» o «-»). Genere uno con: php -r "echo bin2hex(random_bytes(32));"');

                return self::FAILURE;
            }

            $url = (string) ($this->option('url') ?: route('telegram.webhook'));

            if (! str_starts_with($url, 'https://')) {
                $this->error('Telegram solo acepta webhooks HTTPS. URL recibida: '.$url);

                return self::FAILURE;
            }

            $client->setWebhook($url, $secret, TelegramUpdateHandler::ALLOWED_UPDATES, (bool) $this->option('drop-pending'));
            TelegramHealth::forget();
            $info = $client->getWebhookInfo();
        } catch (TelegramApiException $exception) {
            $this->error('Telegram rechazó la operación: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Webhook registrado: '.($info['url'] ?? $url));
        $this->line('Mensajes pendientes: '.(int) ($info['pending_update_count'] ?? 0));

        if (filled($info['last_error_message'] ?? null)) {
            $this->warn('Último error informado por Telegram: '.$info['last_error_message']);
        }

        return self::SUCCESS;
    }
}
