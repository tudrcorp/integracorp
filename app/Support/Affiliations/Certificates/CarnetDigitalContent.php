<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

/**
 * Contenido del carnet digital al que lleva el QR del certificado.
 *
 * Hay dos versiones del mismo diseño:
 * - Individual («Carnet Tu Dr en Casa v3»): Telemedicina y AMD por un lado y
 *   coordinación de servicios derivados por otro.
 * - Corporativa («Carnet Tu Dr en Casa v4 Tailor Made»): plan a la medida con
 *   un único canal, MediChat.
 */
final class CarnetDigitalContent
{
    public const INDIVIDUAL = 'individual';

    public const CORPORATE = 'corporativo';

    private const AMD_WHATSAPP = '584142009229';

    private const MEDICHAT_WHATSAPP = '584242132112';

    private const PHARMACY_WHATSAPP = '584242878918';

    private const INFOCHAT_WHATSAPP = '584242271498';

    /**
     * @return array{
     *     variant: string,
     *     title: string,
     *     photo: string,
     *     photo_alt: string,
     *     photo_position: string,
     *     subtitle: string,
     *     actions: list<array{href: string, label: string, hint: string, icons: list<string>, color: string, hint_color: string, icon_color: string, shadow: string, bar_label: string, bar_icon_color: string}>,
     *     steps: list<array{icon: string, color: string, title: string, text: string}>,
     *     info_href: string,
     *     pharmacy_href: string,
     *     pharmacy_phone: string,
     *     contact_groups: list<array{icon: string, title: string, phones: list<array{label: string, href: string}>, emails: list<string>}>
     * }
     */
    public static function for(string $variant): array
    {
        $corporate = $variant === self::CORPORATE;

        return [
            'variant' => $corporate ? self::CORPORATE : self::INDIVIDUAL,
            'title' => $corporate ? 'Carnet digital · Plan a la medida · Tu Dr en Casa' : 'Carnet digital · Tu Dr en Casa',
            'photo' => $corporate
                ? 'image/certificado-afiliacion/carnet-digital-portada-a-la-medida.jpg'
                : 'image/certificado-afiliacion/carnet-digital-portada.jpg',
            'photo_alt' => $corporate
                ? 'Médico de Tu Doctor en Casa atendiendo a una niña en casa junto a sus padres'
                : 'Médica de Tu Doctor en Casa atendiendo a un niño en casa junto a su familia',
            'photo_position' => $corporate ? '48% center' : '66% center',
            'subtitle' => $corporate
                ? 'Tu plan a la medida: un médico para ti, en casa o en la oficina.'
                : '¿Te sientes mal? Elige una opción y un médico te atiende.',
            'actions' => $corporate ? self::corporateActions() : self::individualActions(),
            'steps' => [
                [
                    'icon' => 'mouse-pointer-click',
                    'color' => '#2D89CA',
                    'title' => 'Contáctanos',
                    'text' => $corporate
                        ? 'Escríbenos o llámanos por MediChat al +58 424-2132112.'
                        : 'Toca Telemedicina y AMD o Coordinación de servicios derivados, según lo que necesites.',
                ],
                ['icon' => 'id-card', 'color' => '#2D89CA', 'title' => 'Indica tu cédula', 'text' => 'Validamos tu afiliación en segundos.'],
                ['icon' => 'stethoscope', 'color' => '#00A859', 'title' => 'Recibe atención médica', 'text' => 'Un médico te orienta y, si lo necesitas, va a donde estés.'],
            ],
            'info_href' => self::whatsapp(self::INFOCHAT_WHATSAPP, 'Hola, soy afiliado de Tu Dr en Casa y quiero consultar los beneficios extra de mi plan. Mi cédula es: '),
            'pharmacy_href' => self::whatsapp(self::PHARMACY_WHATSAPP, 'Buen día, soy afiliado de Tu Dr en Casa y me gustaría realizar una compra con el descuento de mi carnet.'),
            'pharmacy_phone' => '+58 424-2878918',
            'contact_groups' => [
                [
                    'icon' => 'clipboard-plus',
                    'title' => 'Información de servicios médicos',
                    'phones' => [self::phone('0414 901 0352'), self::phone('0414 136 2847')],
                    'emails' => ['operaciones@tudrencasa.com'],
                ],
                [
                    'icon' => 'file-text',
                    'title' => 'Información de planes y tarifas',
                    'phones' => [self::phone('0424 222 0056'), self::phone('0424 227 1498')],
                    'emails' => ['cotizaciones@tudrencasa.com', 'comercial@tudrencasa.com'],
                ],
                [
                    'icon' => 'credit-card',
                    'title' => 'Información de pagos',
                    'phones' => [self::phone('0424 222 0056'), self::phone('0414 924 5606')],
                    'emails' => ['administracion@tudrencasa.com'],
                ],
            ],
        ];
    }

    public static function whatsapp(string $number, string $text): string
    {
        return 'https://wa.me/'.$number.'?text='.rawurlencode($text);
    }

    /**
     * @return list<array{href: string, label: string, hint: string, icons: list<string>, color: string, hint_color: string, icon_color: string, shadow: string, bar_label: string, bar_icon_color: string}>
     */
    private static function individualActions(): array
    {
        return [
            [
                'href' => self::whatsapp(self::AMD_WHATSAPP, 'Hola, soy afiliado de Tu Dr en Casa y necesito atención por telemedicina o atención médica domiciliaria.'),
                'label' => 'Telemedicina y Atención Médica Domiciliaria (AMD)',
                'hint' => 'Contactar ahora',
                'icons' => ['ambulance'],
                'color' => '#FF0F0F',
                'hint_color' => '#D40B0B',
                'icon_color' => '#FFFFFF',
                'shadow' => 'rgba(255,15,15,.14)',
                'bar_label' => 'Telemedicina y AMD',
                'bar_icon_color' => '#FF0F0F',
            ],
            [
                'href' => self::whatsapp(self::MEDICHAT_WHATSAPP, 'Hola, soy afiliado de Tu Dr en Casa y necesito coordinar una consulta o un estudio de imagenología.'),
                'label' => 'Coordinación de servicios derivados',
                'hint' => 'Consultas e imagenología',
                'icons' => ['clipboard-plus'],
                'color' => '#F5B800',
                'hint_color' => '#8A6400',
                'icon_color' => '#0F2233',
                'shadow' => 'rgba(245,184,0,.18)',
                'bar_label' => 'Servicios derivados',
                'bar_icon_color' => '#8A6400',
            ],
        ];
    }

    /**
     * @return list<array{href: string, label: string, hint: string, icons: list<string>, color: string, hint_color: string, icon_color: string, shadow: string, bar_label: string, bar_icon_color: string}>
     */
    private static function corporateActions(): array
    {
        return [
            [
                'href' => self::whatsapp(self::MEDICHAT_WHATSAPP, 'Hola, soy afiliado de Tu Dr en Casa y necesito orientación médica.'),
                'label' => 'MediChat',
                'hint' => 'Contactar ahora',
                'icons' => ['phone', 'message-circle'],
                'color' => '#00A859',
                'hint_color' => '#007A40',
                'icon_color' => '#FFFFFF',
                'shadow' => 'rgba(0,168,89,.16)',
                'bar_label' => 'MediChat',
                'bar_icon_color' => '#00A859',
            ],
        ];
    }

    /**
     * @return array{label: string, href: string}
     */
    private static function phone(string $label): array
    {
        return ['label' => $label, 'href' => 'tel:+58'.substr(str_replace(' ', '', $label), 1)];
    }
}
