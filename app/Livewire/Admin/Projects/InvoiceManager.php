<?php

namespace App\Livewire\Admin\Projects;

use App\Enums\ActivityAction;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Support\ActivityLogger;
use App\Support\InvoiceNumber;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

#[Layout('layouts::admin')]
class InvoiceManager extends Component
{
    #[Locked]
    public string $projectId;

    public string $type = 'termin';

    public string $dueDate = '';

    /** @var array<int, string> */
    public array $paymentRejectionReasons = [];

    /** @var list<array{title: string, qty: string, unit_price: string}> */
    public array $items = [['title' => '', 'qty' => '1', 'unit_price' => '']];

    public function mount(string $project): void
    {
        $this->projectId = $project;
        $this->authorize('viewAny', [Invoice::class, $this->project]);
        $this->type = match ($this->project->status) {
            ProjectStatus::Deal, ProjectStatus::AwaitingDp => InvoiceType::Dp->value,
            ProjectStatus::AwaitingFinal => InvoiceType::Final->value,
            default => InvoiceType::Termin->value,
        };
    }

    #[Computed]
    public function project(): Project
    {
        return Project::query()->visibleTo(auth()->user())->findOrFail($this->projectId);
    }

    public function addItem(): void
    {
        $this->items[] = ['title' => '', 'qty' => '1', 'unit_price' => ''];
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) > 1 && array_key_exists($index, $this->items)) {
            array_splice($this->items, $index, 1);
        }
    }

    public function createInvoice(): void
    {
        $project = $this->project;
        $this->authorize('create', [Invoice::class, $project]);

        $data = $this->validate([
            'type' => ['required', Rule::enum(InvoiceType::class)],
            'dueDate' => ['nullable', 'date', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.title' => ['required', 'string', 'max:180'],
            'items.*.qty' => ['required', 'regex:/^\\d{1,9}(?:\\.\\d{1,3})?$/', 'numeric', 'gt:0', 'max:1000000'],
            'items.*.unit_price' => ['required', 'string', 'max:24', 'regex:/^\\d{1,16}(?:\\.\\d{1,2})?$/],
        ]);

        $invoiceType = InvoiceType::from($data['type']);
        $allowedTypes = match ($project->status) {
            ProjectStatus::Deal => [InvoiceType::Dp],
            ProjectStatus::AwaitingDp => [InvoiceType::Dp, InvoiceType::Termin],
            ProjectStatus::Active => [InvoiceType::Termin],
            ProjectStatus::AwaitingFinal => [InvoiceType::Termin, InvoiceType::Final],
            default => [],
        };
        if (! in_array($invoiceType, $allowedTypes, true)) {
            $this->addError('type', __('projects.invoice.errors.project_not_billable'));

            return;
        }

        try {
            $calculatedItems = [];
            $subtotal = Money::zero($project->currency);

            foreach ($data['items'] as $index => $item) {
                $unitPrice = Money::fromMajor($item['unit_price'], $project->currency);
                if ($unitPrice->amount <= 0) {
                    $this->addError("items.{$index}.unit_price", __('projects.invoice.errors.price_positive'));

                    return;
                }

                $amount = $unitPrice->multiply($item['qty']);
                if ($amount->amount <= 0) {
                    $this->addError("items.{$index}.qty", __('projects.invoice.errors.amount_positive'));

                    return;
                }

                $subtotal = $subtotal->add($amount);
                $calculatedItems[] = [
                    'title' => trim($item['title']),
                    'qty' => $item['qty'],
                    'unit_price' => $unitPrice->amount,
                    'amount' => $amount->amount,
                    'sort_order' => $index,
                ];
            }
        } catch (\InvalidArgumentException) {
            $this->addError('items', __('projects.invoice.errors.invalid_amount'));

            return;
        }

        try {
            $invoice = DB::transaction(function () use ($project, $data, $calculatedItems, $subtotal): Invoice {
                $invoice = $project->invoices()->make([
                    'invoice_number' => InvoiceNumber::next(),
                    'type' => $data['type'],
                    'currency' => $project->currency,
                    'amount' => $subtotal->amount,
                    'due_date' => $data['dueDate'] ?: null,
                ]);
                $invoice->forceFill([
                    'subtotal' => $subtotal->amount,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'paid_amount' => 0,
                    'status' => InvoiceStatus::Draft,
                    'issued_at' => null,
                ])->save();

                foreach ($calculatedItems as $item) {
                    $invoice->items()->create($item);
                }

                ActivityLogger::log(ActivityAction::InvoiceCreated, $invoice, [
                    'invoice_number' => $invoice->invoice_number,
                    'type' => $invoice->type->value,
                    'amount' => $invoice->amount,
                    'currency' => $invoice->currency->value,
                    'source' => 'manual',
                ]);

                return $invoice;
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('items', __('projects.invoice.errors.create_failed'));

            return;
        }

        $this->reset('type', 'dueDate');
        $this->items = [['title' => '', 'qty' => '1', 'unit_price' => '']];
        unset($this->invoices);
        Flux::toast(variant: 'success', text: __('projects.invoice.draft_saved'));
        $this->redirectRoute('admin.projects.invoices', ['project' => $project->id], navigate: true);
    }

    public function issueInvoice(string $invoiceId): void
    {
        $project = $this->project;
        $invoice = $project->invoices()->findOrFail($invoiceId);
        $this->authorize('update', $invoice);

        DB::transaction(function () use ($project, $invoiceId): void {
            $invoice = Invoice::query()
                ->where('project_id', $project->id)
                ->lockForUpdate()
                ->findOrFail($invoiceId);
            $this->authorize('update', $invoice);
            abort_unless($invoice->status === InvoiceStatus::Draft, 409);

            $invoice->forceFill([
                'status' => InvoiceStatus::Sent,
                'issued_at' => now(),
            ])->save();
            ActivityLogger::log(ActivityAction::InvoiceSent, $invoice, [
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->amount,
                'currency' => $invoice->currency->value,
            ]);

            if ($invoice->type === InvoiceType::Dp && $project->status === ProjectStatus::Deal) {
                $project->transitionTo(ProjectStatus::AwaitingDp, auth()->user());
                ActivityLogger::changed(ActivityAction::StatusChanged, $project, ProjectStatus::Deal, ProjectStatus::AwaitingDp);
            }
        });

        unset($this->invoices);
        Flux::toast(variant: 'success', text: __('projects.invoice.sent'));
    }

    /** Tandai bukti sebagai terverifikasi dengan invoice lock untuk mencegah overpayment saat request bersamaan. */
    public function verifyPayment(int $paymentId): void
    {
        $payment = $this->paymentForProject($paymentId);
        $this->authorize('verify', $payment);

        DB::transaction(function () use ($paymentId, $payment): void {
            $invoice = Invoice::query()
                ->where('project_id', $this->projectId)
                ->lockForUpdate()
                ->findOrFail($payment->invoice_id);
            $lockedPayment = Payment::query()
                ->where('invoice_id', $invoice->id)
                ->lockForUpdate()
                ->findOrFail($paymentId);
            $this->authorize('verify', $lockedPayment);

            $remaining = $invoice->amount - $invoice->paid_amount;
            if ($lockedPayment->amount <= 0 || $lockedPayment->amount > $remaining) {
                throw ValidationException::withMessages([
                    'payment' => __('projects.invoice.errors.payment_exceeds_balance'),
                ]);
            }

            $paidAmount = $invoice->paid_amount + $lockedPayment->amount;
            $isPaid = $paidAmount >= $invoice->amount;
            $lockedPayment->forceFill([
                'status' => PaymentStatus::Verified,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ])->save();

            $hasPending = $invoice->payments()
                ->where('status', PaymentStatus::Pending->value)
                ->exists();
            $invoice->forceFill([
                'paid_amount' => $paidAmount,
                'status' => $isPaid
                    ? InvoiceStatus::Paid
                    : ($hasPending
                        ? InvoiceStatus::AwaitingVerification
                        : ($invoice->issued_at !== null ? InvoiceStatus::Sent : InvoiceStatus::Unpaid)),
                'paid_at' => $isPaid ? now() : null,
                'payment_method' => $lockedPayment->method,
            ])->save();

            ActivityLogger::log(ActivityAction::PaymentVerified, $lockedPayment, [
                'invoice_id' => $invoice->id,
                'amount' => $lockedPayment->amount,
                'paid_amount' => $paidAmount,
            ]);

            if ($isPaid) {
                ActivityLogger::log(ActivityAction::InvoicePaid, $invoice, ['paid_amount' => $paidAmount]);
                $project = $this->project;
                if ($invoice->type === InvoiceType::Dp && $project->status === ProjectStatus::AwaitingDp) {
                    $project->transitionTo(ProjectStatus::Active, auth()->user());
                    ActivityLogger::changed(ActivityAction::StatusChanged, $project, ProjectStatus::AwaitingDp, ProjectStatus::Active);
                } elseif ($invoice->type === InvoiceType::Final && $project->status === ProjectStatus::AwaitingFinal) {
                    $project->transitionTo(ProjectStatus::Handover, auth()->user());
                    ActivityLogger::changed(ActivityAction::StatusChanged, $project, ProjectStatus::AwaitingFinal, ProjectStatus::Handover);
                }
            }
        });

        unset($this->invoices);
        Flux::toast(variant: 'success', text: __('projects.invoice.payment_verified'));
    }

    public function rejectPayment(int $paymentId): void
    {
        $payment = $this->paymentForProject($paymentId);
        $this->authorize('verify', $payment);
        $field = "paymentRejectionReasons.{$paymentId}";
        $this->validate([$field => ['required', 'string', 'max:1000']]);
        $reason = trim($this->paymentRejectionReasons[$paymentId]);

        DB::transaction(function () use ($paymentId, $payment, $reason): void {
            $invoice = Invoice::query()
                ->where('project_id', $this->projectId)
                ->lockForUpdate()
                ->findOrFail($payment->invoice_id);
            $lockedPayment = Payment::query()
                ->where('invoice_id', $invoice->id)
                ->lockForUpdate()
                ->findOrFail($paymentId);
            $this->authorize('verify', $lockedPayment);

            $lockedPayment->forceFill([
                'status' => PaymentStatus::Rejected,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            $hasPending = $invoice->payments()->where('status', PaymentStatus::Pending->value)->exists();
            if ($invoice->status === InvoiceStatus::AwaitingVerification && ! $hasPending) {
                $invoice->forceFill([
                    'status' => $invoice->issued_at !== null ? InvoiceStatus::Sent : InvoiceStatus::Unpaid,
                ])->save();
            }

            ActivityLogger::log(ActivityAction::PaymentRejected, $lockedPayment, [
                'invoice_id' => $invoice->id,
                'reason' => $reason,
            ]);
        });

        unset($this->invoices);
        $this->paymentRejectionReasons[$paymentId] = '';
        Flux::toast(variant: 'success', text: __('projects.invoice.payment_rejected'));
    }

    private function paymentForProject(int $paymentId): Payment
    {
        return Payment::query()
            ->whereHas('invoice', fn ($query) => $query->where('project_id', $this->projectId))
            ->with('invoice')
            ->findOrFail($paymentId);
    }

    #[Computed]
    public function invoices()
    {
        return $this->project->invoices()->with(['items', 'payments'])->latest('issued_at')->get();
    }

    public function render(): View
    {
        $this->authorize('viewAny', [Invoice::class, $this->project]);

        return view('livewire.admin.projects.invoice-manager', [
            'project' => $this->project,
            'invoices' => $this->invoices,
            'types' => InvoiceType::cases(),
        ])->title(__('projects.invoice.title'));
    }
}
