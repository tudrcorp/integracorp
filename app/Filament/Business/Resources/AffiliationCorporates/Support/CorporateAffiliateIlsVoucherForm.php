<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporates\Support;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Support\AffiliationCorporates\CorporateAffiliateIlsVoucherManager;
use App\Support\AffiliationCorporates\CorporateAffiliateVoucherIlsUpdater;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Modal «Vouchers ILS por beneficio» de los afiliados corporativos de Negocios.
 *
 * Arma un bloque por cada beneficio con tope en USD de la cobertura de los
 * seleccionados. El encabezado del bloque dice beneficio, cobertura y tope; el
 * analista solo escribe número, vigencia y comprobante. Las reglas viven en
 * `CorporateAffiliateIlsVoucherManager`: esta clase solo las presenta.
 */
final class CorporateAffiliateIlsVoucherForm
{
    /**
     * Valida la selección al abrir: si mezcla coberturas o no tiene beneficios
     * con tope, se avisa y el modal no se abre.
     *
     * @param  Collection<int, AffiliateCorporate>  $affiliates
     */
    public static function mount(Action $action, ?Schema $schema, Collection $affiliates): void
    {
        try {
            $selection = CorporateAffiliateIlsVoucherManager::resolveSelection($affiliates);
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title('No se pueden cargar vouchers para esta selección')
                ->body($exception->getMessage())
                ->warning()
                ->persistent()
                ->send();

            $action->halt();

            return;
        }

        $schema?->fill(['vouchers' => CorporateAffiliateIlsVoucherManager::formDefaults($selection)]);
    }

    /**
     * @param  Collection<int, AffiliateCorporate>  $affiliates
     */
    public static function description(Collection $affiliates): string
    {
        try {
            $selection = CorporateAffiliateIlsVoucherManager::resolveSelection($affiliates);
        } catch (InvalidArgumentException) {
            return '';
        }

        $count = $selection['affiliates']->count();

        return 'Cobertura '.$selection['coverage_label'].' · '.$count.' afiliado(s). '
            .'Cada beneficio lleva su propio voucher. Complete solo los que va a cargar: los que deje vacíos no se modifican.';
    }

    /**
     * @param  Collection<int, AffiliateCorporate>  $affiliates
     * @return array<int, Section>
     */
    public static function components(Collection $affiliates): array
    {
        try {
            $selection = CorporateAffiliateIlsVoucherManager::resolveSelection($affiliates);
        } catch (InvalidArgumentException) {
            return [];
        }

        $existing = CorporateAffiliateIlsVoucherManager::existingCounts($selection);
        $total = $selection['affiliates']->count();
        $sections = [];

        foreach ($selection['eligible'] as $item) {
            $already = $existing[$item['key']] ?? 0;

            $status = match (true) {
                $already === 0 => 'Sin voucher cargado.',
                $total === 1 => 'Ya tiene voucher: si lo cambia, se reemplaza.',
                $already === $total => 'Los '.$total.' ya tienen voucher: si carga uno, se reemplaza para todos.',
                default => $already.' de '.$total.' ya tienen voucher: si carga uno, se reemplaza para todos.',
            };

            $sections[] = Section::make($item['benefit'])
                ->description($selection['coverage_label'].' · Límite '.CorporateAffiliateIlsVoucherManager::money($item['limit']).' · '.$status)
                ->icon(Heroicon::Ticket)
                ->iconColor($already === $total ? 'success' : ($already > 0 ? 'warning' : 'gray'))
                ->compact()
                ->statePath('vouchers.'.$item['key'])
                ->columnSpanFull()
                ->schema([
                    Grid::make(['default' => 1, 'md' => 4])->schema([
                        TextInput::make('voucher_code')
                            ->label('Número de voucher')
                            ->placeholder('Ej. ILS-000123')
                            ->maxLength(100)
                            ->required(fn (Get $get): bool => self::blockStarted($get))
                            ->validationMessages(['required' => 'Falta el número de voucher.'])
                            ->columnSpan(['default' => 1, 'md' => 1]),
                        DatePicker::make('date_init')
                            ->label('Desde')
                            ->displayFormat('d/m/Y')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, Get $get): mixed => $set('number_days', self::days($get)))
                            ->required(fn (Get $get): bool => self::blockStarted($get))
                            ->validationMessages(['required' => 'Falta la fecha desde.']),
                        DatePicker::make('date_end')
                            ->label('Hasta')
                            ->displayFormat('d/m/Y')
                            ->live(onBlur: true)
                            ->afterOrEqual('date_init')
                            ->afterStateUpdated(fn (Set $set, Get $get): mixed => $set('number_days', self::days($get)))
                            ->required(fn (Get $get): bool => self::blockStarted($get))
                            ->validationMessages([
                                'required' => 'Falta la fecha hasta.',
                                'after_or_equal' => 'No puede ser anterior a la fecha desde.',
                            ]),
                        TextInput::make('number_days')
                            ->label('Días de vigencia')
                            ->suffix('días')
                            ->disabled()
                            ->dehydrated(false),
                    ]),
                    FileUpload::make('document_path')
                        ->label('Comprobante del voucher')
                        ->helperText('PDF o imagen, máximo 10 MB.')
                        ->disk(CorporateAffiliateIlsVoucherManager::DOCUMENT_DISK)
                        ->directory(CorporateAffiliateIlsVoucherManager::DOCUMENT_DIRECTORY)
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(10240)
                        ->downloadable()
                        ->openable()
                        ->required(fn (Get $get): bool => self::blockStarted($get))
                        ->validationMessages(['required' => 'Falta el comprobante.']),
                ]);
        }

        return $sections;
    }

    /**
     * Guarda y avisa. Ante un error de datos el modal queda abierto para que el
     * analista corrija sin volver a cargar todo.
     *
     * @param  array<int, int|string>  $affiliateIds
     * @param  array<string, mixed>  $data
     */
    public static function save(Action $action, AffiliationCorporate $owner, array $affiliateIds, array $data): void
    {
        try {
            $result = CorporateAffiliateIlsVoucherManager::save(
                $owner,
                $affiliateIds,
                is_array($data['vouchers'] ?? null) ? $data['vouchers'] : [],
                Auth::id(),
            );
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title('No se guardaron los vouchers')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $action->halt();

            return;
        }

        Notification::make()
            ->title('Vouchers ILS guardados')
            ->body(implode(' · ', $result['benefits']).' para '.$result['affiliates'].' afiliado(s).')
            ->success()
            ->send();
    }

    private static function blockStarted(Get $get): bool
    {
        return filled($get('voucher_code'))
            || filled($get('date_init'))
            || filled($get('date_end'))
            || filled($get('document_path'));
    }

    private static function days(Get $get): ?int
    {
        return CorporateAffiliateVoucherIlsUpdater::calculateNumberDays($get('date_init'), $get('date_end'));
    }
}
