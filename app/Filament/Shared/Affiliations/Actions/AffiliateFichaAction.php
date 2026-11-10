<?php

declare(strict_types=1);

namespace App\Filament\Shared\Affiliations\Actions;

use App\Enums\AffiliateFichaScope;
use App\Http\Controllers\AffiliateFichaPdfController;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\User;
use App\Support\Affiliations\AffiliateFicha\AffiliateFichaAccess;
use App\Support\Affiliations\AffiliateFicha\AffiliateFichaData;
use App\Support\Affiliations\AffiliateFicha\AffiliateFichaDelivery;
use App\Support\Affiliations\Certificates\CertificateDeliveryRecipients;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * «Ficha del afiliado» en el menú de acciones de cada afiliado (individual y
 * corporativo) de Negocios, Administración y Operaciones.
 *
 * El analista elige qué información lleva y ve el PDF en la misma modal, con un
 * indicador mientras se genera; al cambiar la opción, la vista previa se vuelve
 * a pedir sola. El PDF lo sirve {@see AffiliateFichaPdfController} por URL
 * firmada, que repite la autorización.
 *
 * Desde la misma modal se envía por correo (Para, CC y CCO, varios en cada uno)
 * o por WhatsApp, con la opción elegida arriba. Los dos botones del pie envían
 * el formulario con un argumento distinto; la validación de cada canal vive en
 * {@see AffiliateFichaDelivery}, que también la usa la cola.
 */
final class AffiliateFichaAction
{
    public const CHANNEL_EMAIL = 'correo';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public static function make(string $kind): Action
    {
        return Action::make('affiliateFicha')
            ->label('Ficha del afiliado')
            ->icon(Heroicon::OutlinedIdentification)
            ->color('info')
            ->modalIcon(Heroicon::OutlinedIdentification)
            ->modalHeading(fn (Model $record): string => 'Ficha de '.self::name($kind, $record))
            ->modalDescription('Elija qué información lleva la ficha. La vista previa se actualiza al cambiar la opción.')
            ->slideOver()
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('sendFichaByEmail', ['channel' => self::CHANNEL_EMAIL])
                    ->label('Enviar por correo')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('primary'),
                $action->makeModalSubmitAction('sendFichaByWhatsApp', ['channel' => self::CHANNEL_WHATSAPP])
                    ->label('Enviar por WhatsApp')
                    ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                    ->color('success'),
            ])
            ->visible(fn (): bool => self::panelId() !== null && AffiliateFichaAccess::canOpen(self::user(), (string) self::panelId()))
            ->fillForm(fn (Model $record): array => [
                'ficha_scope' => AffiliateFichaAccess::defaultScopeFor(self::user(), (string) self::panelId())->value,
                'ficha_to' => CertificateDeliveryRecipients::clean([$record->getAttribute('email')]),
                'ficha_cc' => [],
                'ficha_bcc' => [],
                'ficha_phone' => trim((string) $record->getAttribute('phone')),
            ])
            ->schema(fn (Model $record): array => self::schema($kind, $record))
            ->action(function (Action $action, Model $record, array $data, array $arguments) use ($kind): void {
                self::send($action, $kind, $record, $data, (string) ($arguments['channel'] ?? ''));
            });
    }

    /**
     * @return list<\Filament\Schemas\Components\Component|\Filament\Forms\Components\Field>
     */
    private static function schema(string $kind, Model $record): array
    {
        $panelId = (string) self::panelId();
        $scopes = AffiliateFichaAccess::scopesFor(self::user(), $panelId);
        $options = [];
        $descriptions = [];

        foreach ($scopes as $scope) {
            $options[$scope->value] = $scope->label();
            $descriptions[$scope->value] = $scope->description();
        }

        return [
            Radio::make('ficha_scope')
                ->label('Información de la ficha')
                ->options($options)
                ->descriptions($descriptions)
                ->columns(count($options) > 1 ? 3 : 1)
                ->required()
                ->live(),
            Text::make('La información de operaciones y telemedicina requiere el permiso «Ficha del afiliado: datos clínicos». Pídalo a un SUPERADMIN si lo necesita.')
                ->color('gray')
                ->visible(count($scopes) === 1),
            View::make('filament.shared.affiliations.affiliate-ficha-preview')
                ->viewData(function (Get $get) use ($kind, $record, $panelId): array {
                    $scope = self::scopeFrom($get('ficha_scope'), $panelId);
                    $id = (int) $record->getKey();

                    return [
                        'fichaScopeKey' => $kind.'-'.$id.'-'.$scope->value,
                        'fichaScopeLabel' => $scope->label(),
                        'fichaPreviewUrl' => AffiliateFichaPdfController::url($kind, $id, $scope, $panelId, AffiliateFichaPdfController::MODE_PREVIEW),
                        'fichaDownloadUrl' => AffiliateFichaPdfController::url($kind, $id, $scope, $panelId, AffiliateFichaPdfController::MODE_DOWNLOAD),
                    ];
                }),
            Tabs::make('ficha_send')
                ->label('Enviar la ficha')
                ->tabs([
                    Tab::make('Enviar por correo')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->columns(2)
                        ->schema([
                            TagsInput::make('ficha_to')
                                ->label('Para')
                                ->placeholder('Escriba un correo y pulse Enter')
                                ->helperText('Uno o varios, hasta '.AffiliateFichaDelivery::MAX_RECIPIENTS.'. Se precarga el correo del afiliado si lo tiene.')
                                ->nestedRecursiveRules(['email:rfc', 'max:150'])
                                ->splitKeys(['Tab', ',', ' ', ';'])
                                ->columnSpanFull(),
                            TagsInput::make('ficha_cc')
                                ->label('Con copia (CC)')
                                ->placeholder('Correos visibles para todos')
                                ->helperText('Hasta '.AffiliateFichaDelivery::MAX_COPIES.'.')
                                ->nestedRecursiveRules(['email:rfc', 'max:150'])
                                ->splitKeys(['Tab', ',', ' ', ';']),
                            TagsInput::make('ficha_bcc')
                                ->label('Con copia oculta (CCO)')
                                ->placeholder('Nadie más ve estos correos')
                                ->helperText('Hasta '.AffiliateFichaDelivery::MAX_COPIES.'.')
                                ->nestedRecursiveRules(['email:rfc', 'max:150'])
                                ->splitKeys(['Tab', ',', ' ', ';']),
                            Textarea::make('ficha_email_message')
                                ->label('Mensaje (opcional)')
                                ->rows(2)
                                ->maxLength(1000)
                                ->columnSpanFull(),
                        ]),
                    Tab::make('Enviar por WhatsApp')
                        ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                        ->columns(2)
                        ->schema([
                            TextInput::make('ficha_phone')
                                ->label('Número de WhatsApp')
                                ->placeholder('04121234567 o +584121234567')
                                ->helperText('Se precarga el teléfono del afiliado si lo tiene.')
                                ->tel()
                                ->maxLength(30),
                            Textarea::make('ficha_whatsapp_message')
                                ->label('Mensaje (opcional)')
                                ->rows(2)
                                ->maxLength(1000),
                        ]),
                ])
                ->columnSpanFull(),
            Text::make('Se envía la ficha con la información elegida arriba. El envío corre en segundo plano y le avisaremos en las notificaciones cuando salga.')
                ->color('gray'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function send(Action $action, string $kind, Model $record, array $data, string $channel): void
    {
        $user = self::user();
        $panelId = self::panelId();

        if ($user === null || $panelId === null) {
            Notification::make()->danger()->title('Sesión no válida')->body('Vuelva a iniciar sesión e intente de nuevo.')->send();
            $action->halt();

            return;
        }

        $scope = AffiliateFichaScope::tryFrom((string) ($data['ficha_scope'] ?? ''));

        if ($scope === null) {
            Notification::make()->danger()->title('Elija qué información lleva la ficha')->send();
            $action->halt();

            return;
        }

        $error = match ($channel) {
            self::CHANNEL_EMAIL => AffiliateFichaDelivery::queueEmail(
                $user,
                $kind,
                (int) $record->getKey(),
                $scope,
                $panelId,
                (array) ($data['ficha_to'] ?? []),
                (array) ($data['ficha_cc'] ?? []),
                (array) ($data['ficha_bcc'] ?? []),
                $data['ficha_email_message'] ?? null,
            ),
            self::CHANNEL_WHATSAPP => AffiliateFichaDelivery::queueWhatsApp(
                $user,
                $kind,
                (int) $record->getKey(),
                $scope,
                $panelId,
                $data['ficha_phone'] ?? null,
                $data['ficha_whatsapp_message'] ?? null,
            ),
            default => 'Elija cómo enviar la ficha: por correo o por WhatsApp.',
        };

        if ($error !== null) {
            Notification::make()->danger()->title('No se envió la ficha')->body($error)->send();
            $action->halt();

            return;
        }

        Notification::make()
            ->success()
            ->title($channel === self::CHANNEL_EMAIL ? 'Ficha en camino por correo' : 'Ficha en camino por WhatsApp')
            ->body('La estamos preparando y enviando. Le avisaremos en las notificaciones cuando salga.')
            ->send();
    }

    private static function scopeFrom(mixed $value, string $panelId): AffiliateFichaScope
    {
        $scope = AffiliateFichaScope::tryFrom(is_string($value) ? $value : '')
            ?? AffiliateFichaAccess::defaultScopeFor(self::user(), $panelId);

        return AffiliateFichaAccess::canUse(self::user(), $panelId, $scope) ? $scope : AffiliateFichaScope::Administration;
    }

    private static function name(string $kind, Model $record): string
    {
        $name = match (true) {
            $kind === AffiliateFichaData::KIND_INDIVIDUAL && $record instanceof Affiliate => (string) $record->full_name,
            $kind === AffiliateFichaData::KIND_CORPORATE && $record instanceof AffiliateCorporate => trim($record->first_name.' '.$record->last_name),
            default => '',
        };

        return trim($name) !== '' ? trim($name) : 'afiliado';
    }

    private static function panelId(): ?string
    {
        return AffiliateFichaAccess::currentPanelId();
    }

    private static function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
