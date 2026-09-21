<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Models\IndividualQuote;
use App\Models\IndividualQuotePaymentReceipt;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

final class StorefrontQuoteReceipt
{
    public const DISK = 'public';

    public const MAX_KILOBYTES = 8192;

    /**
     * @var list<string>
     */
    public const MIMES = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'pdf'];

    public static function findOwned(User $user, string $code): IndividualQuote
    {
        $quote = IndividualQuote::query()
            ->with(['detailsQuote.coverage', 'paymentReceipts'])
            ->where('storefront_user_id', (int) $user->id)
            ->where('code', $code)
            ->first();

        abort_unless($quote instanceof IndividualQuote, 404);

        return $quote;
    }

    /**
     * @param  list<int>|null  $coverageIds
     * @return array<string, mixed>
     */
    public static function summary(
        IndividualQuote $quote,
        string $frequency = StorefrontQuoteFrequency::Annual,
        ?array $coverageIds = null,
    ): array {
        $plan = Plan::query()->find((int) $quote->plan);
        $planModel = $plan instanceof Plan ? $plan : null;
        $persons = StorefrontQuotesIndex::personsFromDetails($quote->detailsQuote);
        $status = strtoupper(trim((string) $quote->status));
        $latest = $quote->paymentReceipts->sortByDesc('id')->first();
        $frequency = StorefrontQuoteFrequency::normalize($frequency) ?: StorefrontQuoteFrequency::Annual;
        $selected = $coverageIds ?? StorefrontQuoteCoverages::resolve($quote, $planModel);
        $selection = StorefrontQuoteCoverages::needsSelection($planModel, $quote->detailsQuote)
            ? $selected
            : null;
        $payment = StorefrontQuotesIndex::paymentFromDetails(
            $quote->detailsQuote,
            $planModel,
            $frequency,
            $selection,
        );
        $options = StorefrontQuoteCoverages::options($quote->detailsQuote, $planModel);
        $coveragesLabel = $selection === null
            ? ''
            : StorefrontQuoteCoverages::selectionLabel($options, $selected);

        return [
            'id' => (int) $quote->id,
            'code' => (string) $quote->code,
            'client' => StorefrontPlanNarrative::personName((string) $quote->full_name),
            'phone' => StorefrontPlanNarrative::phoneLabel((string) $quote->phone),
            'email' => (string) $quote->email,
            'plan' => $planModel instanceof Plan
                ? StorefrontPlanNarrative::planLabel((string) StorefrontPlanNarrative::for($planModel)['title'])
                : 'Plan',
            'status' => StorefrontQuotesIndex::statusLabel($status),
            'status_tone' => StorefrontQuotesIndex::statusTone($status),
            'persons' => $persons,
            'persons_label' => StorefrontQuotesIndex::personsLabel($persons),
            'coverages_label' => $coveragesLabel,
            'coverage_ids' => $selected,
            'has_amount' => $payment['has_amount'],
            'amount' => $payment['amount'],
            'amount_label' => $payment['amount_label'],
            'amount_prefix' => $payment['amount_prefix'],
            'amount_period' => $payment['amount_period'],
            'frequency' => $payment['frequency'],
            'frequency_label' => $payment['frequency_label'],
            'installments' => $payment['installments'],
            'annual_total' => $payment['annual_total'],
            'annual_label' => $payment['annual_label'],
            'breakdown' => $payment['breakdown'],
            'has_receipt' => $latest instanceof IndividualQuotePaymentReceipt,
            'pay_url' => StorefrontQuoteCoverages::route(
                'storefront.quote.pay',
                ['code' => $quote->code, 'frequency' => $frequency],
                $selected,
            ),
            'frequency_url' => StorefrontQuoteCoverages::route(
                'storefront.quote.frequency',
                ['code' => $quote->code],
                $selected,
            ),
            'coverages_url' => route('storefront.quote.coverages', ['code' => $quote->code]),
            'receipt_url' => StorefrontQuoteCoverages::route(
                'storefront.quote.receipt',
                ['code' => $quote->code, 'frequency' => $frequency],
                $selected,
            ),
            'success_url' => route('storefront.quote.receipt.success', ['code' => $quote->code]),
            'proposal_url' => route('storefront.quote.proposal', ['code' => $quote->code]),
            'pdf_url' => route('storefront.quote.pdf', ['code' => $quote->code]),
        ];
    }

    public static function store(
        IndividualQuote $quote,
        User $user,
        TemporaryUploadedFile|UploadedFile $file,
    ): IndividualQuotePaymentReceipt {
        self::assertFile($file);

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension === '') {
            $extension = strtolower((string) $file->extension());
        }
        if ($extension === '' || ! in_array($extension, self::MIMES, true)) {
            $extension = 'jpg';
        }

        $directory = 'storefront/quote-receipts/'.$quote->getKey();
        $filename = 'comprobante-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$extension;

        try {
            $stored = DB::transaction(function () use ($quote, $user, $file, $directory, $filename): IndividualQuotePaymentReceipt {
                $path = $file->storeAs($directory, $filename, self::DISK);

                if (! is_string($path) || $path === '') {
                    throw ValidationException::withMessages([
                        'receipt' => ['No pudimos guardar el comprobante. Inténtalo de nuevo.'],
                    ]);
                }

                $receipt = new IndividualQuotePaymentReceipt;
                $receipt->individual_quote_id = (int) $quote->getKey();
                $receipt->storefront_user_id = (int) $user->id;
                $receipt->disk = self::DISK;
                $receipt->path = $path;
                $receipt->original_name = (string) $file->getClientOriginalName();
                $receipt->mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());
                $receipt->size = (int) $file->getSize();
                $receipt->channel = 'pwa';
                $receipt->save();

                return $receipt;
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'receipt' => ['No pudimos guardar el comprobante. Inténtalo de nuevo.'],
            ]);
        }

        StorefrontQuotePaymentReceiptNotifier::notify((int) $stored->getKey());

        return $stored;
    }

    /**
     * @return array{label: string, hint: string, url: string|null}
     */
    public static function administrationWhatsApp(string $code): array
    {
        $phone = StorefrontQuotePaymentReceiptNotifier::contactPhone();
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        $message = 'Hola, cargué el comprobante de la cotización '.$code.' desde la app Tu Dr En Casa. Quedo atento.';

        return [
            'label' => 'Escribir a Administración',
            'hint' => $digits === ''
                ? 'Administración aún no configuró un teléfono'
                : StorefrontPlanNarrative::phoneLabel($digits),
            'url' => $digits === ''
                ? null
                : 'https://wa.me/'.$digits.'?text='.rawurlencode($message),
        ];
    }

    private static function assertFile(TemporaryUploadedFile|UploadedFile $file): void
    {
        $size = (int) $file->getSize();

        if ($size <= 0) {
            throw ValidationException::withMessages([
                'receipt' => ['Elige una foto o un archivo del comprobante.'],
            ]);
        }

        if ($size > self::MAX_KILOBYTES * 1024) {
            throw ValidationException::withMessages([
                'receipt' => ['El archivo debe pesar menos de 8 MB.'],
            ]);
        }

        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension()));
        $mime = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType()));
        $allowedMime = str_starts_with($mime, 'image/') || $mime === 'application/pdf';

        if (! in_array($extension, self::MIMES, true) && ! $allowedMime) {
            throw ValidationException::withMessages([
                'receipt' => ['Usa una foto (JPG, PNG, HEIC) o un PDF del comprobante.'],
            ]);
        }
    }
}
