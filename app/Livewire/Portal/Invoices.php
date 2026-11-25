<?php

namespace App\Livewire\Portal;

use App\Enums\ActivityAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\UnsafeUploadException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Rules\SafeUpload;
use App\Support\ActivityLogger;
use App\Support\Money;
use App\Support\SecureUpload;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/** T4-04: tagihan klien dan unggah bukti bayar. Semua data lewat query yang dibatasi pemilik. */
#[Layout('layouts::portal')]
class Invoices extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $projectId;

    /** Invoice yang sedang dibayar; hanya server yang boleh mengisinya. */
    #[Locked]
    public ?string $payingInvoiceId = null;

    public string $amount = '';

    public string $paidAt = '';

    public string $bankName = '';

    public string $senderName = '';

    public string $referenceNumber = '';

    /** @var TemporaryUploadedFile|null */
    public $proof = null;

    public function mount(string $project): void
    {
        $this->projectId = $project;
        $this->authorize('viewAny', [Invoice::class, $this->ownedProject()]);
    }

    public function startUpload(string $invoiceId): void
    {
        $invoice = $this->invoiceFor($invoiceId);
        $this->authorize('uploadProof', $invoice);

        $this->resetValidation();
        $this->reset('proof', 'bankName', 'senderName', 'referenceNumber');
        $this->payingInvoiceId = $invoiceId;
        $this->amount = $this->remaining($invoice)->toMajorString();
        $this->paidAt = now()->toDateString();
    }

    public function cancelUpload(): void
    {
        $this->resetValidation();
        $this->reset('payingInvoiceId', 'proof', 'amount', 'paidAt', 'bankName', 'senderName', 'referenceNumber');
    }

    /** Periksa file segera setelah dipilih; file yang ditolak langsung dibuang dari form. */
    public function updatedProof(): void
    {
        if ($this->payingInvoiceId === null) {
            $this->proof = null;

            return;
        }

        try {
            $this->validateOnly('proof', ['proof' => ['required', new SafeUpload(SecureUpload::KIND_PROOF)]]);
        } catch (ValidationException $e) {
            $this->proof = null;

            throw $e;
        }
    }

    public function submitProof(): void
    {
        $invoiceId = $this->payingInvoiceId;

        if ($invoiceId === null) {
            abort(404);
        }

        $invoice = $this->invoiceFor($invoiceId);
        $this->authorize('uploadProof', $invoice);

        $this->validate([
            'amount' => ['required', 'string', 'max:24', 'regex:/^\d{1,16}(?:\.\d{1,2})?$/'],
            'paidAt' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'bankName' => ['required', 'string', 'max:100'],
            'senderName' => ['required', 'string', 'max:160'],
            'referenceNumber' => ['nullable', 'string', 'max:100'],
            'proof' => ['required', new SafeUpload(SecureUpload::KIND_PROOF)],
        ]);

        try {
            $paid = Money::fromMajor($this->amount, $invoice->currency);
        } catch (InvalidArgumentException) {
            $this->addError('amount', __('payments.errors.amount_invalid'));

            return;
        }

        if ($paid->amount <= 0) {
            $this->addError('amount', __('payments.errors.amount_positive'));

            return;
        }

        $remaining = $this->remaining($invoice);

        if ($paid->amount > $remaining->amount) {
            $this->addError('amount', __('payments.errors.amount_exceeds', ['remaining' => $remaining->format()]));

            return;
        }

        $file = $this->proof;

        if ($file === null) {
            abort(422);
        }

        try {
            $stored = SecureUpload::store($file, 'payments', SecureUpload::KIND_PROOF);
        } catch (UnsafeUploadException $e) {
            $this->proof = null;
            $this->addError('proof', $e->translated(SecureUpload::maxMegabytes(SecureUpload::KIND_PROOF)));

            return;
        }

        $projectId = $this->projectId;
        $reference = trim($this->referenceNumber);
        $details = [
            'bank_name' => trim($this->bankName),
            'sender_name' => trim($this->senderName),
            'paid_at' => $this->paidAt,
        ];

        try {
            DB::transaction(function () use ($invoiceId, $projectId, $paid, $stored, $reference, $details): void {
                // Dikunci agar dua tab/klik ganda tidak membuat dua pembayaran untuk saldo yang sama.
                $locked = Invoice::query()
                    ->where('project_id', $projectId)
                    ->lockForUpdate()
                    ->findOrFail($invoiceId);

                /** @var InvoiceStatus $status */
                $status = $locked->status;

                if (! $status->acceptsPayment() || $paid->amount > $this->remaining($locked)->amount) {
                    throw ValidationException::withMessages(['amount' => __('payments.errors.not_payable')]);
                }

                $payment = new Payment;
                $payment->forceFill([
                    'invoice_id' => $locked->id,
                    'amount' => $paid->amount,
                    'method' => 'manual_transfer',
                    'proof_path' => $stored->path,
                    'status' => PaymentStatus::Pending,
                    'reference_number' => $reference !== '' ? $reference : null,
                    'bank_name' => $details['bank_name'],
                    'sender_name' => $details['sender_name'],
                    'paid_at' => $details['paid_at'],
                ])->save();

                $locked->forceFill(['status' => InvoiceStatus::AwaitingVerification])->save();

                ActivityLogger::log(ActivityAction::PaymentSubmitted, $payment, [
                    'invoice_id' => $locked->id,
                    'amount' => $paid->amount,
                    'bank_name' => $details['bank_name'],
                ]);
            });
        } catch (Throwable $e) {
            Storage::disk($stored->disk)->delete($stored->path);

            throw $e;
        }

        $this->cancelUpload();
        Flux::toast(variant: 'success', text: __('payments.submitted'));
    }

    public function render(): View
    {
        $project = $this->ownedProject();
        $this->authorize('viewAny', [Invoice::class, $project]);

        return view('livewire.portal.invoices', [
            'project' => $project,
            'invoices' => Invoice::query()
                ->visibleTo(auth()->user())
                ->where('project_id', $this->projectId)
                ->with(['project', 'payments' => fn ($query) => $query->latest('id')])
                ->orderBy('created_at')
                ->get(),
        ])->title(__('payments.title'));
    }

    private function ownedProject(): Project
    {
        return Project::query()->visibleTo(auth()->user())->findOrFail($this->projectId);
    }

    /** Invoice milik proyek ini yang boleh dilihat pengguna; ID dari browser tidak pernah dipercaya. */
    private function invoiceFor(string $invoiceId): Invoice
    {
        return Invoice::query()
            ->visibleTo(auth()->user())
            ->where('project_id', $this->projectId)
            ->findOrFail($invoiceId);
    }

    private function remaining(Invoice $invoice): Money
    {
        return Money::of((int) $invoice->amount, $invoice->currency)
            ->subtract(Money::of((int) $invoice->paid_amount, $invoice->currency));
    }
}
