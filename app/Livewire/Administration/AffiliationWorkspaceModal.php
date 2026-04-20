<?php

declare(strict_types=1);

namespace App\Livewire\Administration;

use App\Http\Controllers\AffiliationController;
use App\Http\Controllers\PaidMembershipController;
use App\Models\Affiliation;
use App\Models\Collection;
use App\Models\PaidMembership;
use App\Support\SecurityAudit;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class AffiliationWorkspaceModal extends Component
{
    use WithFileUploads;

    /** @var array<string, string> */
    public const PAYMENT_METHOD_OPTIONS = [
        'ZELLE' => 'ZELLE',
        'TRANSFERENCIA US$' => 'TRANSFERENCIA (US$)',
        'EFECTIVO US$' => 'EFECTIVO US$',
        'MULTIPLE' => 'MULTIPLE',
        'PAGO MOVIL VES' => 'PAGO MOVIL (VES)',
        'TRANSFERENCIA VES' => 'TRANSFERENCIA (VES)',
        'LINK DE PAGO' => 'LINK DE PAGO',
    ];

    /** @var array<string, string> */
    public const USD_METHOD_OPTIONS = [
        'ZELLE' => 'ZELLE',
        'TRANSFERENCIA US$' => 'TRANSFERENCIA (US$)',
        'EFECTIVO US$' => 'EFECTIVO US$',
    ];

    /** @var array<string, string> */
    public const USD_BANK_OPTIONS = [
        'CHASE BANK' => 'CHASE BANK',
        'BANK OF AMERICA' => 'BANK OF AMERICA',
        'BANESCO, S.A-US$' => 'BANESCO, S.A - US$',
        'BANCAMIGA - US$' => 'BANCAMIGA - US$',
        'BANCO DE VENEZUELA - US$' => 'BANCO DE VENEZUELA - US$',
    ];

    /** @var array<string, string> */
    public const USD_BANK_CASH_OPTIONS = [
        'BANCAMIGA - US$' => 'BANCAMIGA - US$',
        'BANCO DE VENEZUELA - US$' => 'BANCO DE VENEZUELA - US$',
    ];

    /** @var array<string, string> */
    public const VES_METHOD_OPTIONS = [
        'PAGO MOVIL VES' => 'PAGO MOVIL (VES)',
        'TRANSFERENCIA VES' => 'TRANSFERENCIA (VES)',
    ];

    /** @var array<string, string> */
    public const VES_BANK_OPTIONS = [
        'BANCAMIGA(VES)' => 'BANCAMIGA',
        'BANCO DE VENEZUELA(VES)' => 'BANCO DE VENEZUELA',
    ];

    /** @var array<string, string> */
    public const VES_BANK_MULTIPLE_OPTIONS = [
        'BANCAMIGA - VES' => 'BANCAMIGA - VES',
        'BANCO DE VENEZUELA - VES' => 'BANCO DE VENEZUELA - VES',
    ];

    public int $affiliationId;

    /** @var array<string, mixed> */
    public array $paymentForm = [
        'total_amount' => null,
        'date_payment_voucher' => null,
        'payment_method' => null,
        'tasa_bcv' => null,
        'pay_amount_usd' => null,
        'pay_amount_ves' => null,
        'payment_method_usd' => null,
        'payment_method_ves' => null,
        'name_ti_usd' => null,
        'bank_usd' => null,
        'bank_ves' => null,
        'reference_payment_usd' => null,
        'reference_payment_ves' => null,
        'document_usd' => null,
        'document_ves' => null,
        'observations_payment' => null,
    ];

    public ?int $approvingPaidMembershipId = null;

    /** @var array<int> */
    public array $approveCollections = [];

    /** @var array<int, string> */
    public array $availableCollections = [];

    public string $approveCollectionsSearch = '';

    public string $paymentsStatusFilter = 'all';

    public string $paymentsMethodFilter = 'all';

    public string $paymentsReferenceFilter = '';

    public function mount(Affiliation|int $affiliation): void
    {
        $this->affiliationId = $affiliation instanceof Affiliation ? (int) $affiliation->getKey() : (int) $affiliation;
        $this->hydrateDefaults();
        $this->auditWorkspaceAction('AUDIT_ADMIN_AFFILIATION_WORKSPACE_OPENED');
    }

    public function updatedPaymentFormPaymentMethod(string $value): void
    {
        $this->auditWorkspaceAction('AUDIT_ADMIN_AFFILIATION_WORKSPACE_PAYMENT_METHOD_UPDATED', [
            'payment_method' => $value,
        ]);

        if ($value !== 'MULTIPLE') {
            $this->paymentForm['payment_method_usd'] = null;
            $this->paymentForm['payment_method_ves'] = null;
            $this->paymentForm['pay_amount_usd'] = null;
        }

        if (! in_array($value, ['PAGO MOVIL VES', 'TRANSFERENCIA VES', 'MULTIPLE'], true)) {
            $this->paymentForm['tasa_bcv'] = null;
            $this->paymentForm['pay_amount_ves'] = null;
            $this->paymentForm['bank_ves'] = null;
            $this->paymentForm['reference_payment_ves'] = null;
            $this->paymentForm['document_ves'] = null;
        }

        if (! in_array($value, ['ZELLE', 'TRANSFERENCIA US$', 'EFECTIVO US$', 'LINK DE PAGO', 'MULTIPLE'], true)) {
            $this->paymentForm['name_ti_usd'] = null;
            $this->paymentForm['bank_usd'] = null;
            $this->paymentForm['reference_payment_usd'] = null;
            $this->paymentForm['document_usd'] = null;
        }

        $this->recalculateVesAmount();
    }

    public function updatedPaymentFormTasaBcv(): void
    {
        $this->recalculateVesAmount();
    }

    public function updatedPaymentFormTotalAmount(): void
    {
        $this->recalculateVesAmount();
    }

    public function updatedPaymentFormPayAmountUsd(): void
    {
        $this->recalculateVesAmount();
    }

    public function savePayment(): void
    {
        $this->auditWorkspaceAction('AUDIT_ADMIN_AFFILIATION_WORKSPACE_PAYMENT_UPLOAD_ATTEMPTED', [
            'payment_method' => $this->paymentForm['payment_method'] ?? null,
        ]);

        try {
            $this->validate($this->paymentValidationRules(), $this->paymentValidationMessages());
        } catch (ValidationException $exception) {
            $this->auditWorkspaceAction('AUDIT_ADMIN_AFFILIATION_WORKSPACE_PAYMENT_UPLOAD_FAILED', [
                'payment_method' => $this->paymentForm['payment_method'] ?? null,
                'reason' => 'validation_failed',
                'error_fields' => array_keys($exception->errors()),
            ]);

            throw $exception;
        }

        $authUser = Auth::user();
        $affiliation = $this->resolveAffiliation();

        try {
            $data = $this->buildPaymentPayload();

            $saved = AffiliationController::uploadPayment($affiliation, $data, 'AGENTE');
            if (! $saved) {
                SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_PAYMENT_VOUCHER_UPLOAD_FAILED', 'administration.affiliations.workspace.upload-payment', [
                    'panel' => 'administration',
                    'affiliation_id' => $affiliation->id,
                    'affiliation_code' => $affiliation->code,
                    'payment_method' => $data['payment_method'] ?? null,
                    'reason' => 'controller_returned_false',
                ], $authUser);

                Notification::make()
                    ->title('No se pudo registrar')
                    ->body('No fue posible cargar el comprobante de pago.')
                    ->danger()
                    ->send();

                return;
            }

            SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_PAYMENT_VOUCHER_UPLOADED', 'administration.affiliations.workspace.upload-payment', [
                'panel' => 'administration',
                'affiliation_id' => $affiliation->id,
                'affiliation_code' => $affiliation->code,
                'payment_method' => $data['payment_method'] ?? null,
                'voucher_date' => $data['date_payment_voucher'] ?? null,
                'uploaded_by' => $authUser?->name,
            ], $authUser);

            $this->hydrateDefaults();

            Notification::make()
                ->title('Comprobante registrado')
                ->body('La carga del comprobante fue exitosa.')
                ->success()
                ->send();
        } catch (\Throwable $th) {
            SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_PAYMENT_VOUCHER_UPLOAD_FAILED', 'administration.affiliations.workspace.upload-payment', [
                'panel' => 'administration',
                'affiliation_id' => $affiliation->id,
                'affiliation_code' => $affiliation->code,
                'payment_method' => $this->paymentForm['payment_method'] ?? null,
                'error_message' => $th->getMessage(),
                'error_class' => $th::class,
                'error_file' => $th->getFile(),
                'error_line' => $th->getLine(),
            ], $authUser);

            Notification::make()
                ->title('Error al cargar comprobante')
                ->body($th->getMessage())
                ->danger()
                ->send();
        }
    }

    public function openApprove(int $paidMembershipId): void
    {
        $membership = $this->resolveMembership($paidMembershipId);
        if ($membership === null) {
            $this->auditWorkspaceAction('AUDIT_ADMIN_AFFILIATION_WORKSPACE_APPROVAL_OPEN_SKIPPED', [
                'paid_membership_id' => $paidMembershipId,
                'reason' => 'membership_not_found',
            ]);

            return;
        }

        if ($membership->status === 'APROBADO') {
            $this->auditWorkspaceAction('AUDIT_ADMIN_AFFILIATION_WORKSPACE_APPROVAL_OPEN_SKIPPED', [
                'paid_membership_id' => $paidMembershipId,
                'reason' => 'membership_already_approved',
            ]);

            return;
        }

        $affiliationCode = (string) ($membership->affiliation?->code ?? '');
        $this->availableCollections = Collection::query()
            ->where('affiliation_code', $affiliationCode)
            ->where('status', 'POR PAGAR')
            ->orderBy('next_payment_date')
            ->get()
            ->mapWithKeys(fn (Collection $collection): array => [
                (int) $collection->id => (string) ($collection->next_payment_date ?? 'N/A'),
            ])
            ->all();

        $this->approvingPaidMembershipId = $membership->id;
        $this->approveCollections = [];
        $this->approveCollectionsSearch = '';

        $this->auditWorkspaceAction('AUDIT_ADMIN_AFFILIATION_WORKSPACE_APPROVAL_OPENED', [
            'paid_membership_id' => $membership->id,
            'available_collections_count' => count($this->availableCollections),
        ]);
    }

    public function cancelApprove(): void
    {
        $this->auditWorkspaceAction('AUDIT_ADMIN_AFFILIATION_WORKSPACE_APPROVAL_CANCELLED', [
            'paid_membership_id' => $this->approvingPaidMembershipId,
            'selected_collections_count' => count($this->approveCollections),
        ]);

        $this->approvingPaidMembershipId = null;
        $this->approveCollections = [];
        $this->availableCollections = [];
        $this->approveCollectionsSearch = '';
    }

    public function selectAllApproveCollections(): void
    {
        $filtered = $this->filterAvailableCollections($this->approveCollectionsSearch);
        $this->approveCollections = array_values(array_map('intval', array_keys($filtered)));

        $this->auditWorkspaceAction('AUDIT_ADMIN_AFFILIATION_WORKSPACE_APPROVAL_SELECT_ALL', [
            'selected_collections_count' => count($this->approveCollections),
            'filtered_collections_count' => count($filtered),
            'search_term' => $this->approveCollectionsSearch,
        ]);
    }

    public function clearApproveCollections(): void
    {
        $previousSelections = count($this->approveCollections);
        $this->approveCollections = [];

        $this->auditWorkspaceAction('AUDIT_ADMIN_AFFILIATION_WORKSPACE_APPROVAL_SELECTION_CLEARED', [
            'previous_selected_collections_count' => $previousSelections,
        ]);
    }

    public function approvePayment(): void
    {
        if ($this->approvingPaidMembershipId === null) {
            $this->auditWorkspaceAction('AUDIT_ADMIN_AFFILIATION_WORKSPACE_APPROVAL_SKIPPED', [
                'reason' => 'no_active_paid_membership',
            ]);

            return;
        }

        $membership = $this->resolveMembership($this->approvingPaidMembershipId);
        if ($membership === null) {
            $this->auditWorkspaceAction('AUDIT_ADMIN_AFFILIATION_WORKSPACE_APPROVAL_SKIPPED', [
                'paid_membership_id' => $this->approvingPaidMembershipId,
                'reason' => 'membership_not_found',
            ]);
            $this->cancelApprove();

            return;
        }

        $authUser = Auth::user();
        $data = [];
        if (count($this->approveCollections) > 0) {
            $data['collections'] = array_values(array_map('intval', $this->approveCollections));
        }

        $this->auditWorkspaceAction('AUDIT_ADMIN_AFFILIATION_WORKSPACE_APPROVAL_ATTEMPTED', [
            'paid_membership_id' => $membership->id,
            'selected_collections_count' => count($data['collections'] ?? []),
        ]);

        try {
            $result = PaidMembershipController::approvePayment($membership, $data);

            SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_PAYMENT_APPROVAL_TRIGGERED', 'administration.affiliations.workspace.approve-payment', [
                'panel' => 'administration',
                'affiliation_id' => $this->affiliationId,
                'paid_membership_id' => $membership->id,
                'selected_collections_count' => count($data['collections'] ?? []),
                'has_first_register' => (bool) ($result['firstRegister'] ?? false),
                'has_next_register' => (bool) ($result['nextRegister'] ?? false),
                'approved_by' => $authUser?->name,
            ], $authUser);

            $this->cancelApprove();

            Notification::make()
                ->title('Pago aprobado')
                ->body('El pago fue procesado exitosamente.')
                ->success()
                ->send();
        } catch (\Throwable $th) {
            SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_PAYMENT_APPROVAL_FAILED', 'administration.affiliations.workspace.approve-payment', [
                'panel' => 'administration',
                'affiliation_id' => $this->affiliationId,
                'paid_membership_id' => $membership->id,
                'selected_collections_count' => count($data['collections'] ?? []),
                'approved_by' => $authUser?->name,
                'error_message' => $th->getMessage(),
                'error_class' => $th::class,
                'error_file' => $th->getFile(),
                'error_line' => $th->getLine(),
            ], $authUser);

            Notification::make()
                ->title('Error al aprobar pago')
                ->body($th->getMessage())
                ->danger()
                ->send();
        }
    }

    public function render(): View
    {
        $affiliation = $this->resolveAffiliation();
        $paidMembershipsQuery = $affiliation->paid_memberships()
            ->with(['plan', 'coverage'])
            ->latest();

        if ($this->paymentsStatusFilter !== 'all') {
            $paidMembershipsQuery->where('status', $this->paymentsStatusFilter);
        }

        if ($this->paymentsMethodFilter !== 'all') {
            $paidMembershipsQuery->where('payment_method', $this->paymentsMethodFilter);
        }

        $referenceTerm = trim($this->paymentsReferenceFilter);
        if ($referenceTerm !== '') {
            $paidMembershipsQuery->where(function ($query) use ($referenceTerm): void {
                $query->where('reference_payment_usd', 'like', '%'.$referenceTerm.'%')
                    ->orWhere('reference_payment_ves', 'like', '%'.$referenceTerm.'%')
                    ->orWhere('invoice_number', 'like', '%'.$referenceTerm.'%');
            });
        }

        $paidMemberships = $paidMembershipsQuery
            ->limit(50)
            ->get();

        return view('livewire.administration.affiliation-workspace-modal', [
            'affiliation' => $affiliation,
            'paidMemberships' => $paidMemberships,
            'paymentMethodOptions' => self::PAYMENT_METHOD_OPTIONS,
            'usdMethodOptions' => self::USD_METHOD_OPTIONS,
            'usdBankOptions' => self::USD_BANK_OPTIONS,
            'usdBankCashOptions' => self::USD_BANK_CASH_OPTIONS,
            'vesMethodOptions' => self::VES_METHOD_OPTIONS,
            'vesBankOptions' => self::VES_BANK_OPTIONS,
            'vesBankMultipleOptions' => self::VES_BANK_MULTIPLE_OPTIONS,
        ]);
    }

    private function hydrateDefaults(): void
    {
        $affiliation = $this->resolveAffiliation();
        $this->paymentForm['total_amount'] = (float) ($affiliation->total_amount ?? 0);
        $this->paymentForm['date_payment_voucher'] = now()->format('Y-m-d');
    }

    private function resolveAffiliation(): Affiliation
    {
        return Affiliation::query()
            ->with(['agency', 'agent', 'plan', 'coverage', 'country', 'state', 'city'])
            ->findOrFail($this->affiliationId);
    }

    private function resolveMembership(int $membershipId): ?PaidMembership
    {
        return PaidMembership::query()
            ->with('affiliation')
            ->where('affiliation_id', $this->affiliationId)
            ->whereKey($membershipId)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPaymentPayload(): array
    {
        $data = $this->paymentForm;
        $this->recalculateVesAmount();
        $data['pay_amount_ves'] = $this->paymentForm['pay_amount_ves'];

        if (($data['payment_method'] ?? null) === 'PAGO MOVIL VES' || ($data['payment_method'] ?? null) === 'TRANSFERENCIA VES') {
            $tasa = (float) ($data['tasa_bcv'] ?? 0);
            $total = (float) ($data['total_amount'] ?? 0);
            $data['pay_amount_ves'] = $tasa > 0 ? $tasa * $total : ($data['pay_amount_ves'] ?? 0);
        }

        if (($data['payment_method'] ?? null) === 'MULTIPLE') {
            $usd = (float) ($data['pay_amount_usd'] ?? 0);
            $total = (float) ($data['total_amount'] ?? 0);
            $tasa = (float) ($data['tasa_bcv'] ?? 0);
            $restante = max(0, $total - $usd);
            $data['pay_amount_ves'] = $tasa > 0 ? $restante * $tasa : ($data['pay_amount_ves'] ?? 0);
        }

        $data['document_usd'] = $this->storeIfTemporaryUploadedFile($data['document_usd'] ?? null);
        $data['document_ves'] = $this->storeIfTemporaryUploadedFile($data['document_ves'] ?? null);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentValidationRules(): array
    {
        $method = (string) ($this->paymentForm['payment_method'] ?? '');
        $multipleUsdMethod = (string) ($this->paymentForm['payment_method_usd'] ?? '');

        return [
            'paymentForm.total_amount' => ['required', 'numeric', 'min:0'],
            'paymentForm.date_payment_voucher' => ['required', 'date'],
            'paymentForm.payment_method' => ['required', 'string'],
            'paymentForm.tasa_bcv' => in_array($method, ['MULTIPLE', 'PAGO MOVIL VES', 'TRANSFERENCIA VES'], true) ? ['required', 'numeric', 'min:0'] : ['nullable', 'numeric', 'min:0'],
            'paymentForm.name_ti_usd' => in_array($method, ['ZELLE', 'TRANSFERENCIA US$', 'LINK DE PAGO'], true) || ($method === 'MULTIPLE' && in_array($multipleUsdMethod, ['ZELLE', 'TRANSFERENCIA US$'], true)) ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],
            'paymentForm.bank_usd' => in_array($method, ['TRANSFERENCIA US$', 'EFECTIVO US$'], true) || ($method === 'MULTIPLE' && in_array($multipleUsdMethod, ['TRANSFERENCIA US$', 'EFECTIVO US$'], true)) ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],
            'paymentForm.bank_ves' => in_array($method, ['PAGO MOVIL VES', 'TRANSFERENCIA VES', 'MULTIPLE'], true) ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],
            'paymentForm.document_usd' => in_array($method, ['ZELLE', 'TRANSFERENCIA US$', 'EFECTIVO US$', 'LINK DE PAGO', 'MULTIPLE'], true) ? ['required'] : ['nullable'],
            'paymentForm.document_ves' => in_array($method, ['PAGO MOVIL VES', 'TRANSFERENCIA VES', 'MULTIPLE'], true) ? ['required'] : ['nullable'],
            'paymentForm.reference_payment_ves' => in_array($method, ['PAGO MOVIL VES', 'TRANSFERENCIA VES', 'MULTIPLE'], true) ? ['required', 'string', 'max:30'] : ['nullable', 'string', 'max:30'],
            'paymentForm.reference_payment_usd' => in_array($method, ['ZELLE', 'LINK DE PAGO'], true) || ($method === 'MULTIPLE' && $multipleUsdMethod === 'ZELLE') ? ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-]+$/'] : ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-]+$/'],
            'paymentForm.pay_amount_usd' => $method === 'MULTIPLE' ? ['required', 'numeric', 'min:0'] : ['nullable', 'numeric', 'min:0'],
            'paymentForm.payment_method_usd' => $method === 'MULTIPLE' ? ['required', 'string'] : ['nullable', 'string'],
            'paymentForm.payment_method_ves' => $method === 'MULTIPLE' ? ['required', 'string'] : ['nullable', 'string'],
            'paymentForm.pay_amount_ves' => in_array($method, ['PAGO MOVIL VES', 'TRANSFERENCIA VES', 'MULTIPLE'], true) ? ['required', 'numeric', 'min:0'] : ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function paymentValidationMessages(): array
    {
        return [
            'paymentForm.total_amount.required' => 'El total a pagar es requerido.',
            'paymentForm.date_payment_voucher.required' => 'La fecha del comprobante es requerida.',
            'paymentForm.payment_method.required' => 'Seleccione un método de pago.',
            'paymentForm.tasa_bcv.required' => 'Debe indicar la tasa BCV.',
            'paymentForm.name_ti_usd.required' => 'El nombre del titular es requerido.',
            'paymentForm.bank_usd.required' => 'Seleccione/indique el banco en US$.',
            'paymentForm.bank_ves.required' => 'Seleccione/indique el banco en VES.',
            'paymentForm.document_usd.required' => 'Debe cargar el comprobante en US$.',
            'paymentForm.document_ves.required' => 'Debe cargar el comprobante en VES.',
            'paymentForm.reference_payment_ves.required' => 'La referencia en VES es requerida.',
            'paymentForm.reference_payment_usd.required' => 'La referencia en US$ es requerida.',
            'paymentForm.reference_payment_usd.regex' => 'Solo se permiten letras, números y guion (-).',
            'paymentForm.pay_amount_usd.required' => 'El monto en US$ es requerido para pago múltiple.',
            'paymentForm.pay_amount_ves.required' => 'El monto en VES es requerido.',
            'paymentForm.payment_method_usd.required' => 'Seleccione el método de pago en US$.',
            'paymentForm.payment_method_ves.required' => 'Seleccione el método de pago en VES.',
        ];
    }

    private function recalculateVesAmount(): void
    {
        $method = (string) ($this->paymentForm['payment_method'] ?? '');
        $tasa = (float) ($this->paymentForm['tasa_bcv'] ?? 0);
        $total = (float) ($this->paymentForm['total_amount'] ?? 0);
        $usd = (float) ($this->paymentForm['pay_amount_usd'] ?? 0);

        if ($method === 'PAGO MOVIL VES' || $method === 'TRANSFERENCIA VES') {
            $this->paymentForm['pay_amount_ves'] = $tasa > 0 ? round($tasa * $total, 2) : null;

            return;
        }

        if ($method === 'MULTIPLE') {
            $restante = max(0, $total - $usd);
            $this->paymentForm['pay_amount_ves'] = $tasa > 0 ? round($restante * $tasa, 2) : null;

            return;
        }

        $this->paymentForm['pay_amount_ves'] = null;
    }

    /**
     * @return array<int, string>
     */
    public function getFilteredAvailableCollectionsProperty(): array
    {
        return $this->filterAvailableCollections($this->approveCollectionsSearch);
    }

    public function updatedApproveCollectionsSearch(string $value): void
    {
        $term = trim($value);
        if ($term === '' || mb_strlen($term) >= 3) {
            $this->auditWorkspaceAction('AUDIT_ADMIN_AFFILIATION_WORKSPACE_APPROVAL_SEARCH_UPDATED', [
                'search_term' => $term,
                'results_count' => count($this->filterAvailableCollections($term)),
            ]);
        }
    }

    public function updatedPaymentsStatusFilter(string $value): void
    {
        $this->auditWorkspaceAction('AUDIT_ADMIN_AFFILIATION_WORKSPACE_PAYMENTS_FILTER_UPDATED', [
            'filter' => 'status',
            'value' => $value,
        ]);
    }

    public function updatedPaymentsMethodFilter(string $value): void
    {
        $this->auditWorkspaceAction('AUDIT_ADMIN_AFFILIATION_WORKSPACE_PAYMENTS_FILTER_UPDATED', [
            'filter' => 'method',
            'value' => $value,
        ]);
    }

    public function updatedPaymentsReferenceFilter(string $value): void
    {
        $term = trim($value);
        if ($term === '' || mb_strlen($term) >= 3) {
            $this->auditWorkspaceAction('AUDIT_ADMIN_AFFILIATION_WORKSPACE_PAYMENTS_FILTER_UPDATED', [
                'filter' => 'reference',
                'value' => $term,
            ]);
        }
    }

    /**
     * @param  array<int>  $value
     */
    public function updatedApproveCollections(array $value): void
    {
        $this->auditWorkspaceAction('AUDIT_ADMIN_AFFILIATION_WORKSPACE_APPROVAL_SELECTION_UPDATED', [
            'selected_collections_count' => count($value),
            'selected_collection_ids' => array_slice(array_values(array_map('intval', $value)), 0, 50),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function filterAvailableCollections(string $search): array
    {
        $term = mb_strtolower(trim($search));
        if ($term === '') {
            return $this->availableCollections;
        }

        return collect($this->availableCollections)
            ->filter(function (string $label, int|string $id) use ($term): bool {
                return str_contains(mb_strtolower((string) $id), $term)
                    || str_contains(mb_strtolower($label), $term);
            })
            ->mapWithKeys(fn (string $label, int|string $id): array => [(int) $id => $label])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function auditWorkspaceAction(string $action, array $details = []): void
    {
        $affiliationCode = Affiliation::query()
            ->whereKey($this->affiliationId)
            ->value('code');

        SecurityAudit::log($action, 'administration.affiliations.workspace', [
            'panel' => 'administration',
            'affiliation_id' => $this->affiliationId,
            'affiliation_code' => $affiliationCode,
            ...$details,
        ], Auth::user());
    }

    private function storeIfTemporaryUploadedFile(mixed $file): mixed
    {
        if (! $file instanceof TemporaryUploadedFile) {
            return $file;
        }

        return $file->store('paid-memberships', 'public');
    }
}
