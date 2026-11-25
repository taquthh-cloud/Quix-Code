<?php

use App\Enums\ActivityAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Admin\Projects\InvoiceManager;
use App\Livewire\Portal\Invoices;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

function portalPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAIAAABLbSncAAAAFUlEQVR42mM8ISfHgA0wMeAAg1MCANDKARQfGKMRAAAAAElFTkSuQmCC');
}

function portalProof(string $name = 'bukti.png', ?string $content = null): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content ?? portalPng());
}

/** @return list<string> semua file di disk privat; ditampilkan utuh di diff bila tes gagal. */
function portalFiles(): array
{
    return array_values(Storage::disk('local')->allFiles());
}

beforeEach(function () {
    app()->setLocale('id');
    Storage::fake('local');
    Storage::fake('public');
    $this->admin = User::factory()->admin()->withTwoFactor()->create();
    $this->staff = User::factory()->staff()->create();
    $this->client = User::factory()->client()->create();
    $this->other = User::factory()->client()->create();
    $this->project = Project::factory()->for($this->client, 'client')->create();
    $this->invoice = Invoice::factory()->for($this->project)->create(['amount' => 10_000_000, 'paid_amount' => 0]);
});

function fillPortalProofForm($component, array $overrides = [])
{
    $data = [
        'paidAt' => now()->toDateString(),
        'bankName' => 'BCA',
        'senderName' => 'Budi Santoso',
        'referenceNumber' => 'TRX-123',
        ...$overrides,
    ];

    foreach ($data as $field => $value) {
        $component->set($field, $value);
    }

    return $component;
}

it('klien melihat tagihan proyeknya dengan sisa tagihan, dan tidak melihat milik klien lain', function () {
    $otherProject = Project::factory()->for($this->other, 'client')->create();
    $foreign = Invoice::factory()->for($otherProject)->create(['invoice_number' => 'QC/2026/99999']);
    Invoice::factory()->for($this->project)->create(['invoice_number' => 'QC/2026/DRAF1', 'status' => InvoiceStatus::Draft]);

    $this->actingAs($this->client)
        ->get(route('portal.projects.invoices', $this->project->id))
        ->assertOk()
        ->assertSee($this->invoice->invoice_number)
        ->assertSee('Rp 10.000.000')
        ->assertDontSee($foreign->invoice_number)
        ->assertDontSee('QC/2026/DRAF1');
});

it('klien lain, staf, dan admin tidak bisa membuka halaman tagihan portal', function () {
    $this->actingAs($this->other)->get(route('portal.projects.invoices', $this->project->id))->assertNotFound();
    $this->actingAs($this->staff)->get(route('portal.projects.invoices', $this->project->id))->assertForbidden();
    $this->actingAs($this->admin)->get(route('portal.projects.invoices', $this->project->id))->assertForbidden();
    auth()->logout();
    $this->get(route('portal.projects.invoices', $this->project->id))->assertRedirect(route('login'));
});

it('beranda proyek menautkan ke halaman tagihan', function () {
    $this->actingAs($this->client)
        ->get(route('portal.projects.show', $this->project->id))
        ->assertOk()
        ->assertSee(route('portal.projects.invoices', $this->project->id), false);
});

it('klien mengunggah bukti bayar: pembayaran pending, invoice menunggu verifikasi, file di disk privat', function () {
    $this->actingAs($this->client);

    $component = Livewire::test(Invoices::class, ['project' => $this->project->id])
        ->call('startUpload', $this->invoice->id)
        ->assertSet('amount', '10000000')
        ->assertSet('payingInvoiceId', $this->invoice->id);

    fillPortalProofForm($component)
        ->set('proof', portalProof())
        ->call('submitProof')
        ->assertHasNoErrors()
        ->assertSet('payingInvoiceId', null)
        ->assertSet('proof', null);

    $payment = Payment::query()->firstOrFail();

    expect($payment->invoice_id)->toBe($this->invoice->id)
        ->and($payment->amount)->toBe(10_000_000)
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->method)->toBe('manual_transfer')
        ->and($payment->bank_name)->toBe('BCA')
        ->and($payment->sender_name)->toBe('Budi Santoso')
        ->and($payment->reference_number)->toBe('TRX-123')
        ->and($payment->paid_at->toDateString())->toBe(now()->toDateString())
        ->and($payment->proof_path)->toMatch('#^payments/[0-9a-z]{26}\.png$#D')
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::AwaitingVerification);

    Storage::disk('local')->assertExists($payment->proof_path);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();

    $log = ActivityLog::query()->where('action', ActivityAction::PaymentSubmitted->value)->firstOrFail();
    expect($log->user_id)->toBe($this->client->id)->and($log->subject_id)->toBe((string) $payment->id);
});

it('pembayaran sebagian diterima selama tidak melebihi sisa tagihan', function () {
    $this->actingAs($this->client);

    $component = Livewire::test(Invoices::class, ['project' => $this->project->id])
        ->call('startUpload', $this->invoice->id)
        ->set('amount', '4000000');

    fillPortalProofForm($component)->set('proof', portalProof())->call('submitProof')->assertHasNoErrors();

    expect(Payment::query()->firstOrFail()->amount)->toBe(4_000_000);
});

it('menolak nominal melebihi sisa, nol, atau format salah tanpa menyimpan apa pun', function () {
    $this->actingAs($this->client);

    foreach (['10000001' => 'amount', '0' => 'amount', '1.5' => 'amount', 'abc' => 'amount', '' => 'amount'] as $value => $field) {
        $component = Livewire::test(Invoices::class, ['project' => $this->project->id])
            ->call('startUpload', $this->invoice->id)
            ->set('amount', (string) $value);

        fillPortalProofForm($component)->set('proof', portalProof())->call('submitProof')->assertHasErrors([$field]);
    }

    expect(Payment::query()->count())->toBe(0)
        ->and(portalFiles())->toBe([])
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('menolak isian wajib yang kosong, tanggal masa depan, dan bukti yang belum dipilih', function () {
    $this->actingAs($this->client);

    Livewire::test(Invoices::class, ['project' => $this->project->id])
        ->call('startUpload', $this->invoice->id)
        ->set('bankName', '')
        ->set('senderName', '')
        ->set('paidAt', now()->addDay()->toDateString())
        ->call('submitProof')
        ->assertHasErrors(['bankName', 'senderName', 'paidAt', 'proof']);

    expect(Payment::query()->count())->toBe(0);
});

it('menolak file berbahaya: ekstensi palsu, skrip, polyglot, SVG', function () {
    $this->actingAs($this->client);

    $bad = [
        'shell.php' => portalPng(),
        'bukti.png' => '<?php echo shell_exec($_GET["c"]);',
        'gambar.png' => portalPng().'<?php system($_GET["c"]); ?>',
        'logo.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    ];

    foreach ($bad as $name => $content) {
        $component = Livewire::test(Invoices::class, ['project' => $this->project->id])
            ->call('startUpload', $this->invoice->id);

        fillPortalProofForm($component)
            ->set('proof', portalProof($name, $content))
            ->call('submitProof')
            ->assertHasErrors(['proof']);
    }

    expect(Payment::query()->count())->toBe(0)
        ->and(portalFiles())->toBe([]);
});

it('klien tidak bisa membayar invoice proyek lain walau ID-nya dikirim dari browser', function () {
    $otherProject = Project::factory()->for($this->other, 'client')->create();
    $foreign = Invoice::factory()->for($otherProject)->create();

    $this->actingAs($this->client);

    Livewire::test(Invoices::class, ['project' => $this->project->id])
        ->call('startUpload', $foreign->id)
        ->assertNotFound();

    Livewire::actingAs($this->other)
        ->test(Invoices::class, ['project' => $this->project->id])
        ->assertNotFound();

    expect(Payment::query()->count())->toBe(0);
});

it('properti penentu invoice dan proyek terkunci dari manipulasi browser', function () {
    $this->actingAs($this->client);

    $component = Livewire::test(Invoices::class, ['project' => $this->project->id])
        ->call('startUpload', $this->invoice->id);

    expect(fn () => $component->set('payingInvoiceId', 'ID-PALSU'))->toThrow(CannotUpdateLockedPropertyException::class)
        ->and(fn () => $component->set('projectId', 'ID-PALSU'))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('invoice lunas atau menunggu verifikasi tidak menerima bukti baru', function () {
    $paid = Invoice::factory()->paid()->for($this->project)->create();
    $awaiting = Invoice::factory()->for($this->project)->create(['status' => InvoiceStatus::AwaitingVerification]);

    $this->actingAs($this->client);

    Livewire::test(Invoices::class, ['project' => $this->project->id])
        ->call('startUpload', $paid->id)
        ->assertForbidden();

    Livewire::test(Invoices::class, ['project' => $this->project->id])
        ->call('startUpload', $awaiting->id)
        ->assertForbidden();
});

it('dua tab: status berubah setelah form dibuka, kiriman kedua ditolak dan tidak ada pembayaran ganda', function () {
    $this->actingAs($this->client);

    $tabA = Livewire::test(Invoices::class, ['project' => $this->project->id])
        ->call('startUpload', $this->invoice->id);
    $tabB = Livewire::test(Invoices::class, ['project' => $this->project->id])
        ->call('startUpload', $this->invoice->id);

    fillPortalProofForm($tabA)->set('proof', portalProof())->call('submitProof')->assertHasNoErrors();

    fillPortalProofForm($tabB)->set('proof', portalProof())->call('submitProof')->assertForbidden();

    expect(Payment::query()->count())->toBe(1)
        ->and(portalFiles())->toBe([Payment::query()->firstOrFail()->proof_path]);
});

it('mengirim ulang setelah ditolak: alasan terlihat, bukti baru diterima', function () {
    $this->invoice->forceFill(['status' => InvoiceStatus::Unpaid])->save();
    Payment::factory()->for($this->invoice)->create([
        'status' => PaymentStatus::Rejected,
        'rejection_reason' => 'Nominal pada bukti tidak terbaca',
        'bank_name' => 'Mandiri',
        'paid_at' => now()->subDay(),
    ]);

    $this->actingAs($this->client);

    $component = Livewire::test(Invoices::class, ['project' => $this->project->id])
        ->assertSee('Nominal pada bukti tidak terbaca')
        ->assertSee(__('payments.resubmit_hint'))
        ->call('startUpload', $this->invoice->id);

    fillPortalProofForm($component)->set('proof', portalProof())->call('submitProof')->assertHasNoErrors();

    expect(Payment::query()->count())->toBe(2)
        ->and(Payment::query()->where('status', PaymentStatus::Pending->value)->count())->toBe(1)
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::AwaitingVerification);
});

it('setelah kiriman berhasil, kiriman ulang tanpa membuka form lagi tidak membuat pembayaran baru', function () {
    $this->actingAs($this->client);

    $component = Livewire::test(Invoices::class, ['project' => $this->project->id])
        ->call('startUpload', $this->invoice->id);

    fillPortalProofForm($component)->set('proof', portalProof())->call('submitProof')->assertHasNoErrors();
    $component->call('submitProof');

    expect(Payment::query()->count())->toBe(1);
});

it('alur penuh: klien kirim bukti, admin verifikasi, DP lunas memindahkan proyek ke active', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::AwaitingDp]);
    $invoice = Invoice::factory()->for($project)->create(['amount' => 5_000_000, 'paid_amount' => 0, 'type' => 'dp']);

    $this->actingAs($this->client);
    $component = Livewire::test(Invoices::class, ['project' => $project->id])->call('startUpload', $invoice->id);
    fillPortalProofForm($component)->set('proof', portalProof())->call('submitProof')->assertHasNoErrors();

    $payment = Payment::query()->where('invoice_id', $invoice->id)->firstOrFail();

    $this->actingAs($this->admin);
    Livewire::test(InvoiceManager::class, ['project' => $project->id])
        ->assertSee('BCA')
        ->assertSee('Budi Santoso')
        ->call('verifyPayment', $payment->id)
        ->assertHasNoErrors();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($project->fresh()->status)->toBe(ProjectStatus::Active);
});

it('semua teks pembayaran tersedia dalam dua bahasa dengan kunci yang sama', function () {
    $id = require lang_path('id/payments.php');
    $en = require lang_path('en/payments.php');

    expect(array_keys(Arr::dot($id)))->toBe(array_keys(Arr::dot($en)));
});
