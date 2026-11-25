<?php

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Admin\Projects\InvoiceManager;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('id');
    $this->admin = User::factory()->admin()->withTwoFactor()->create();
    $this->client = User::factory()->client()->create();
    $this->staff = User::factory()->staff()->create();
});

it('admin can issue an itemized invoice using project currency and sequential number', function () {
    $project = Project::factory()->for($this->client, 'client')->create([
        'status' => ProjectStatus::Deal,
        'currency' => Currency::IDR,
    ]);

    $this->actingAs($this->admin);
    Livewire::test(InvoiceManager::class, ['project' => $project->id])
        ->set('type', 'dp')
        ->set('dueDate', now()->addDays(10)->toDateString())
        ->set('items', [
            ['title' => 'Implementasi modul', 'qty' => '1.5', 'unit_price' => '20000'],
            ['title' => 'Konfigurasi', 'qty' => '2', 'unit_price' => '5000'],
        ])
        ->call('createInvoice')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.projects.invoices', ['project' => $project->id]));

    $invoice = Invoice::with('items')->firstOrFail();
    expect($invoice->invoice_number)->toBe('QC/'.now()->year.'/0001')
        ->and($invoice->type->value)->toBe('dp')
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->issued_at)->toBeNull()
        ->and($project->fresh()->status)->toBe(ProjectStatus::Deal)
        ->and($invoice->amount)->toBe(40_000)
        ->and($invoice->subtotal)->toBe(40_000)
        ->and($invoice->items)->toHaveCount(2)
        ->and($invoice->items->pluck('amount')->all())->toBe([30_000, 10_000]);

    Livewire::test(InvoiceManager::class, ['project' => $project->id])
        ->call('issueInvoice', $invoice->id)
        ->assertHasNoErrors();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent)
        ->and($invoice->fresh()->issued_at)->not->toBeNull()
        ->and($project->fresh()->status)->toBe(ProjectStatus::AwaitingDp);
});

it('invoice amounts use currency minor units for USD', function () {
    $project = Project::factory()->usd()->for($this->client, 'client')->create(['status' => ProjectStatus::Active]);

    $this->actingAs($this->admin);
    Livewire::test(InvoiceManager::class, ['project' => $project->id])
        ->set('type', 'termin')
        ->set('items', [['title' => 'Cloud service', 'qty' => '2.5', 'unit_price' => '12.40']])
        ->call('createInvoice')
        ->assertHasNoErrors();

    $invoice = Invoice::firstOrFail();
    expect($invoice->currency)->toBe(Currency::USD)
        ->and($invoice->amount)->toBe(3_100);
});

it('invalid invoice lines and pre-deal projects cannot issue an invoice', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Lead]);

    $this->actingAs($this->admin);
    Livewire::test(InvoiceManager::class, ['project' => $project->id])
        ->set('items', [['title' => '', 'qty' => '0', 'unit_price' => '-100']])
        ->call('createInvoice')
        ->assertHasErrors(['items.0.title', 'items.0.qty', 'items.0.unit_price']);

    expect(Invoice::count())->toBe(0);

    Livewire::test(InvoiceManager::class, ['project' => $project->id])
        ->set('items', [['title' => 'Valid line', 'qty' => '1', 'unit_price' => '1000']])
        ->call('createInvoice')
        ->assertHasErrors('type');

    $billable = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Deal]);
    Livewire::test(InvoiceManager::class, ['project' => $billable->id])
        ->set('items', [['title' => 'Line', 'qty' => '1', 'unit_price' => '0']])
        ->call('createInvoice')
        ->assertHasErrors('items.0.unit_price');

    expect(Invoice::count())->toBe(0);
});

it('admin verification records payment and moves a fully paid DP project into delivery', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::AwaitingDp]);
    $invoice = Invoice::factory()->for($project)->create([
        'type' => 'dp',
        'amount' => 80_000,
        'paid_amount' => 0,
        'status' => InvoiceStatus::AwaitingVerification,
    ]);
    $payment = Payment::factory()->for($invoice)->create(['amount' => 80_000]);

    $this->actingAs($this->admin);
    Livewire::test(InvoiceManager::class, ['project' => $project->id])
        ->call('verifyPayment', $payment->id)
        ->assertHasNoErrors();

    expect($payment->fresh()->status->value)->toBe('verified')
        ->and($payment->fresh()->verified_by)->toBe($this->admin->id)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()->paid_amount)->toBe(80_000)
        ->and($project->fresh()->status)->toBe(ProjectStatus::Active);
});

it('a verified partial payment reduces the balance without completing the invoice', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Active]);
    $invoice = Invoice::factory()->for($project)->create([
        'amount' => 100_000,
        'paid_amount' => 0,
        'status' => InvoiceStatus::AwaitingVerification,
    ]);
    $payment = Payment::factory()->for($invoice)->create(['amount' => 30_000]);

    $this->actingAs($this->admin);
    Livewire::test(InvoiceManager::class, ['project' => $project->id])
        ->call('verifyPayment', $payment->id)
        ->assertHasNoErrors();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid)
        ->and($invoice->fresh()->paid_amount)->toBe(30_000)
        ->and($invoice->fresh()->paid_at)->toBeNull()
        ->and($project->fresh()->status)->toBe(ProjectStatus::Active);
});

it('admin can reject a pending payment with an audited reason and restore invoice acceptance', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Active]);
    $invoice = Invoice::factory()->for($project)->create([
        'amount' => 100_000,
        'status' => InvoiceStatus::AwaitingVerification,
        'issued_at' => now(),
    ]);
    $payment = Payment::factory()->for($invoice)->create(['amount' => 100_000]);

    $this->actingAs($this->admin);
    Livewire::test(InvoiceManager::class, ['project' => $project->id])
        ->set("paymentRejectionReasons.{$payment->id}", 'Bukti tidak terbaca')
        ->call('rejectPayment', $payment->id)
        ->assertHasNoErrors();

    expect($payment->fresh()->status->value)->toBe('rejected')
        ->and($payment->fresh()->rejection_reason)->toBe('Bukti tidak terbaca')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

it('rejects an overpayment to prevent the invoice balance from exceeding its total', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Active]);
    $invoice = Invoice::factory()->for($project)->create([
        'amount' => 100_000,
        'status' => InvoiceStatus::AwaitingVerification,
    ]);
    $payment = Payment::factory()->for($invoice)->create(['amount' => 100_001]);

    $this->actingAs($this->admin);
    Livewire::test(InvoiceManager::class, ['project' => $project->id])
        ->call('verifyPayment', $payment->id)
        ->assertHasErrors('payment');

    expect($payment->fresh()->status->value)->toBe('pending')
        ->and($invoice->fresh()->paid_amount)->toBe(0);
});

it('only admins can access manual invoice management', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Deal]);

    $this->actingAs($this->staff)->get(route('admin.projects.invoices', $project->id))->assertForbidden();
    $this->actingAs($this->client)->get(route('admin.projects.invoices', $project->id))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.projects.invoices', $project->id))->assertOk();
});

it('payment proof is downloaded from private storage only for a project owner or admin', function () {
    Storage::fake('local');
    $project = Project::factory()->for($this->client, 'client')->create();
    $invoice = Invoice::factory()->for($project)->create();
    $path = 'payments/private-proof.jpg';
    Storage::disk('local')->put($path, 'private proof bytes');
    $payment = Payment::factory()->for($invoice)->create(['proof_path' => $path]);
    $otherClient = User::factory()->client()->create();

    $this->actingAs($this->client)
        ->get(route('admin.payments.proof', ['payment' => $payment->id]))
        ->assertOk()
        ->assertDownload('private-proof.jpg');

    $this->actingAs($otherClient)->get(route('admin.payments.proof', ['payment' => $payment->id]))->assertNotFound();
});

it('invoice print view is authorized for the owner and hidden from other users', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['title' => 'Website Acme']);
    $invoice = Invoice::factory()->for($project)->create(['invoice_number' => 'QC/'.now()->year.'/0042']);
    $otherClient = User::factory()->client()->create();

    $this->actingAs($this->client)
        ->get(route('admin.invoices.print', ['invoice' => $invoice->id]))
        ->assertOk()
        ->assertSee('Website Acme')
        ->assertSee($invoice->invoice_number)
        ->assertSee('Ditagihkan kepada / Bill to')
        ->assertSee('Jumlah / Amount')
        ->assertSee('Belum Dibayar / Unpaid')
        ->assertSee('window.print()');

    $this->actingAs($otherClient)->get(route('admin.invoices.print', ['invoice' => $invoice->id]))->assertNotFound();
    $this->actingAs($this->staff)->get(route('admin.invoices.print', ['invoice' => $invoice->id]))->assertNotFound();
});

it('invoice manual menerapkan diskon lalu PPN dari pengaturan', function () {
    Setting::query()->create(['key' => 'tax.rate_bp', 'value' => ['basis_points' => 1100]]);
    $project = Project::factory()->for($this->client, 'client')->create([
        'status' => ProjectStatus::Active,
        'currency' => Currency::IDR,
    ]);

    $this->actingAs($this->admin);
    Livewire::test(InvoiceManager::class, ['project' => $project->id])
        ->assertSet('applyTax', true)
        ->set('type', 'termin')
        ->set('items', [
            ['title' => 'Implementasi modul', 'qty' => '1.5', 'unit_price' => '20000'],
            ['title' => 'Konfigurasi', 'qty' => '2', 'unit_price' => '5000'],
        ])
        ->set('discount', '4000')
        ->call('createInvoice')
        ->assertHasNoErrors();

    $invoice = Invoice::firstOrFail();

    expect($invoice->subtotal)->toBe(40_000)
        ->and($invoice->discount_amount)->toBe(4_000)
        ->and($invoice->tax_amount)->toBe(3_960)  // 11% x 36.000
        ->and($invoice->amount)->toBe(39_960);
});

it('invoice manual tanpa PPN bila opsi dimatikan, dan diskon sebesar subtotal ditolak', function () {
    Setting::query()->create(['key' => 'tax.rate_bp', 'value' => ['basis_points' => 1100]]);
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Active]);
    $items = [['title' => 'Jasa', 'qty' => '1', 'unit_price' => '10000']];

    $this->actingAs($this->admin);
    Livewire::test(InvoiceManager::class, ['project' => $project->id])
        ->set('type', 'termin')
        ->set('items', $items)
        ->set('discount', '10000')
        ->call('createInvoice')
        ->assertHasErrors('discount');

    expect(Invoice::count())->toBe(0);

    Livewire::test(InvoiceManager::class, ['project' => $project->id])
        ->set('type', 'termin')
        ->set('items', $items)
        ->set('applyTax', false)
        ->set('discount', '1000')
        ->call('createInvoice')
        ->assertHasNoErrors();

    $invoice = Invoice::firstOrFail();

    expect($invoice->tax_amount)->toBe(0)->and($invoice->amount)->toBe(9_000);
});

it('invoice cetak menampilkan subtotal, diskon, dan PPN, serta baris porsi termin untuk invoice otomatis', function () {
    $project = Project::factory()->for($this->client, 'client')->create();
    $invoice = Invoice::factory()->for($project)->create(['amount' => 1_665_000]);
    $invoice->forceFill([
        'subtotal' => 1_620_000, 'discount_amount' => 120_000, 'tax_amount' => 165_000, 'term_percent_bp' => 3000,
    ])->save();

    $this->actingAs($this->client)
        ->get(route('admin.invoices.print', ['invoice' => $invoice->id]))
        ->assertOk()
        ->assertSee('Diskon / Discount')
        ->assertSee('PPN / VAT')
        ->assertSee('Subtotal / Subtotal')
        ->assertSee('Porsi termin: 30% / Term share: 30%');
});
