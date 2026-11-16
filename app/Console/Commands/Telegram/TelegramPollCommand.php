<?php

declare(strict_types=1);

namespace App\Console\Commands\Telegram;

use App\Support\Telegram\TelegramApiException;
use App\Support\Telegram\TelegramBotClient;
use App\Support\Telegram\TelegramUpdateHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Para desarrollo local: Telegram no puede llamar a un dominio .test, así que en
 * vez del webhook se le pregunta cada pocos segundos (getUpdates). No funciona
 * si el bot tiene webhook registrado (telegram:set-webhook --delete lo quita).
 * En producción no se usa.
 */
class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll {--seconds=300 : Cuánto tiempo escuchar antes de salir}';

    protected $description = 'Escucha al bot de Telegram en local (sin webhook) y procesa vinculaciones y grupos';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('En producción el bot usa el webhook (telegram:set-webhook). No use telegram:poll.');

            return self::FAILURE;
        }

        $client = TelegramBotClient::make();

        if (! $client->isConfigured()) {
            $this->error('Falta TELEGRAM_BOT_TOKEN en el .env.');

            return self::FAILURE;
        }

        $handler = new TelegramUpdateHandler($client);
        $until = time() + max(5, (int) $this->option('seconds'));
        $offset = (int) Cache::get('telegram:poll:offset', 0);

        $this->info('Escuchando a @'.config('telegram.bot_username').' hasta las '.date('H:i:s', $until).'. Ctrl+C para salir.');

        while (time() < $until) {
            try {
                $updates = $client->getUpdates($offset, 20, TelegramUpdateHandler::ALLOWED_UPDATES);
            } catch (TelegramApiException $exception) {
                $this->error($exception->getMessage());

                if ($exception->errorCode === 409) {
                    $this->warn('El bot tiene webhook registrado. Quítelo con: php artisan telegram:set-webhook --delete');

                    return self::FAILURE;
                }

                sleep(3);

                continue;
            }

            foreach ($updates as $update) {
                $offset = max($offset, (int) ($update['update_id'] ?? 0) + 1);
                $handler->handle($update);
                $this->line('Procesado update '.($update['update_id'] ?? '?'));
            }

            Cache::put('telegram:poll:offset', $offset, now()->addDays(7));
        }

        return self::SUCCESS;
    }
}
