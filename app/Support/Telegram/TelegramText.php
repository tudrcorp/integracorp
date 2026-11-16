<?php

declare(strict_types=1);

namespace App\Support\Telegram;

/**
 * Arma textos para el modo HTML de Telegram: todo lo dinámico se escapa, así
 * un nombre con «<» o «&» no rompe el mensaje ni inyecta formato.
 */
final class TelegramText
{
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false);
    }

    /**
     * Título en negrita y cuerpo en texto plano escapado.
     */
    public static function notification(string $title, string $body): string
    {
        $title = trim($title);
        $body = trim($body);

        return '<b>'.self::escape($title).'</b>'.($body !== '' ? "\n\n".self::escape($body) : '');
    }

    /**
     * Mismo aviso en el formato de WhatsApp (negrita con asteriscos), para el respaldo.
     */
    public static function whatsApp(string $title, string $body): string
    {
        $title = trim(str_replace('*', '', $title));
        $body = trim($body);

        return '*'.$title.'*'.($body !== '' ? "\n\n".$body : '');
    }

    /**
     * Convierte un mensaje con formato de WhatsApp (*negrita*, _cursiva_,
     * ~tachado~, ```bloque```) al HTML de Telegram, escapando todo lo demás.
     * Los guiones bajos dentro de palabras (archivo_respaldo.sql) no se tocan.
     */
    public static function fromWhatsApp(string $text): string
    {
        $html = self::escape(trim($text));

        $html = (string) preg_replace('/```(.+?)```/s', '<pre>$1</pre>', $html);
        $html = (string) preg_replace('/(?<![\w*])\*([^*\n]+?)\*(?![\w*])/u', '<b>$1</b>', $html);
        $html = (string) preg_replace('/(?<![\w])_([^_\n]+?)_(?![\w])/u', '<i>$1</i>', $html);
        $html = (string) preg_replace('/(?<![\w~])~([^~\n]+?)~(?![\w~])/u', '<s>$1</s>', $html);

        return $html;
    }

    /**
     * Texto plano sin marcas de WhatsApp, para la campanita del panel.
     */
    public static function plain(string $text): string
    {
        return trim((string) preg_replace('/(?<![\w])[*_~]|[*_~](?![\w])|```/u', '', $text));
    }
}
