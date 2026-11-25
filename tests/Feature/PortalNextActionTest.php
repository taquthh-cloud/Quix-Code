<?php

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use App\Support\ProjectNextActions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    app()->setLocale('id');
    $this->client = User::factory()->client()->create();
    $this->other = User::factory()->client()->create();
});

function nextActionProject(User $client, ProjectStatus $status): Project
{
    return Project::factory()->for($client, 'client')->create(['status' => $status]);
}

/** @return list<string> */
function nextActionKeys(Project $project): array
{
    return array_map(fn ($action) => $action->key, ProjectNextActions::for($project->fresh()));
}

it('lead tanpa quotation: menunggu quotation, mengarah ke chat', function () {
    $project = nextActionProject($this->client, ProjectStatus::Lead);

    $action = ProjectNextActions::primary($project->fresh());

    expect($action->key)->toBe('awaiting_quotation')
        ->and($action->tone)->toBe('waiting')
        ->and($action->url)->toBe(route('portal.projects.negotiation', $project->id));
});

it('quotation terkirim dan masih berlaku: klien diminta meninjau, mengarah ke halaman negosiasi', function () {
    $project = nextActionProject($this->client, ProjectStatus::Quoted);
    Quotation::factory()->sent()->for($project)->create(['valid_until' => now()->addDays(14)]);

    $action = ProjectNextActions::primary($project->fresh());

    expect($action->key)->toBe('review_quotation')
        ->and($action->tone)->toBe('action')
        ->and($action->url)->toBe(route('portal.projects.negotiation', $project->id));
});

it('quotation yang hampir berakhir menyebut sisa hari, dan berubah pesan pada hari terakhir', function () {
    $project = nextActionProject($this->client, ProjectStatus::Negotiating);
    $quotation = Quotation::factory()->sent()->for($project)->create(['valid_until' => now()->addDays(2)]);

    $soon = ProjectNextActions::primary($project->fresh());
    expect($soon->key)->toBe('review_quotation_soon')->and($soon->description)->toContain('2 hari');

    $quotation->forceFill(['valid_until' => now()])->save();
    expect(ProjectNextActions::primary($project->fresh())->key)->toBe('review_quotation_today');
});

it('quotation kedaluwarsa (lewat tanggal atau sudah ditandai): klien diminta meminta versi baru', function () {
    $project = nextActionProject($this->client, ProjectStatus::Quoted);
    $quotation = Quotation::factory()->sent()->for($project)->create(['valid_until' => now()->subDays(2)]);

    expect(nextActionKeys($project))->toBe(['quotation_expired']);

    $quotation->forceFill(['status' => App\Enums\QuotationStatus::Expired])->save();
    expect(nextActionKeys($project))->toBe(['quotation_expired']);
});

it('draf quotation tidak pernah memicu peringatan untuk klien', function () {
    $project = nextActionProject($this->client, ProjectStatus::Quoted);
    Quotation::factory()->for($project)->create(['status' => App\Enums\QuotationStatus::Draft]);

    expect(nextActionKeys($project))->not->toContain('review_quotation')
        ->and(nextActionKeys($project))->toBe(['awaiting_revision']);
});

it('invoice DP belum dibayar: bayar DP, mengarah ke halaman tagihan', function () {
    $project = nextActionProject($this->client, ProjectStatus::AwaitingDp);
    Invoice::factory()->for($project)->create(['type' => InvoiceType::Dp, 'amount' => 5_000_000, 'paid_amount' => 0, 'status' => InvoiceStatus::Unpaid, 'due_date' => now()->addDays(5)]);

    $action = ProjectNextActions::primary($project->fresh());

    expect($action->key)->toBe('pay_dp')
        ->and($action->description)->toContain('Rp 5.000.000')
        ->and($action->url)->toBe(route('portal.projects.invoices', $project->id));
});

it('bukti bayar menunggu verifikasi: status menunggu, tidak lagi meminta bayar', function () {
    $project = nextActionProject($this->client, ProjectStatus::AwaitingDp);
    Invoice::factory()->for($project)->create(['paid_amount' => 0, 'status' => InvoiceStatus::AwaitingVerification]);

    $keys = nextActionKeys($project);

    expect($keys)->toBe(['payment_verifying'])
        ->and(ProjectNextActions::primary($project->fresh())->tone)->toBe('waiting');
});

it('pembayaran terakhir ditolak: unggah ulang diprioritaskan di atas peringatan lain', function () {
    $project = nextActionProject($this->client, ProjectStatus::AwaitingDp);
    $invoice = Invoice::factory()->for($project)->create(['paid_amount' => 0, 'status' => InvoiceStatus::Unpaid]);
    Payment::factory()->for($invoice)->create(['status' => PaymentStatus::Rejected, 'rejection_reason' => 'Buram']);

    expect(nextActionKeys($project)[0])->toBe('resubmit_payment');

    // Pembayaran yang lebih baru (pending) menggantikan penolakan lama.
    Payment::factory()->for($invoice)->create(['status' => PaymentStatus::Pending]);
    expect(nextActionKeys($project))->not->toContain('resubmit_payment');
});

it('tagihan lewat jatuh tempo diprioritaskan, dan satu invoice hanya menghasilkan satu peringatan', function () {
    $project = nextActionProject($this->client, ProjectStatus::Active);
    Invoice::factory()->for($project)->create(['type' => InvoiceType::Termin, 'paid_amount' => 0, 'status' => InvoiceStatus::Unpaid, 'due_date' => now()->addDays(10)]);
    Invoice::factory()->for($project)->create(['type' => InvoiceType::Dp, 'paid_amount' => 0, 'status' => InvoiceStatus::Unpaid, 'due_date' => now()->subDays(3)]);

    $keys = nextActionKeys($project);

    expect($keys)->toBe(['pay_overdue', 'pay_invoice']);
});

it('pelunasan: bayar pelunasan saat invoice final belum dibayar', function () {
    $project = nextActionProject($this->client, ProjectStatus::AwaitingFinal);
    Invoice::factory()->final()->for($project)->create(['paid_amount' => 0, 'status' => InvoiceStatus::Unpaid]);

    expect(nextActionKeys($project))->toBe(['pay_final']);
});

it('invoice draf, void, batal, dan lunas diabaikan', function () {
    $project = nextActionProject($this->client, ProjectStatus::Active);
    foreach ([InvoiceStatus::Draft, InvoiceStatus::Void, InvoiceStatus::Cancelled] as $status) {
        Invoice::factory()->for($project)->create(['paid_amount' => 0, 'status' => $status]);
    }
    Invoice::factory()->paid()->for($project)->create();

    expect(nextActionKeys($project))->toBe([]);
});

it('serah terima dan ditunda memberi info; proyek berjalan, garansi, selesai, dan batal tanpa peringatan', function () {
    expect(nextActionKeys(nextActionProject($this->client, ProjectStatus::Handover)))->toBe(['handover_ready'])
        ->and(nextActionKeys(nextActionProject($this->client, ProjectStatus::OnHold)))->toBe(['on_hold'])
        ->and(nextActionKeys(nextActionProject($this->client, ProjectStatus::Active)))->toBe([])
        ->and(nextActionKeys(nextActionProject($this->client, ProjectStatus::Warranty)))->toBe([])
        ->and(nextActionKeys(nextActionProject($this->client, ProjectStatus::Closed)))->toBe([])
        ->and(nextActionKeys(nextActionProject($this->client, ProjectStatus::Cancelled)))->toBe([]);
});

it('proyek terminal tidak memunculkan peringatan walau masih ada invoice belum dibayar', function () {
    $project = nextActionProject($this->client, ProjectStatus::Cancelled);
    Invoice::factory()->for($project)->create(['paid_amount' => 0, 'status' => InvoiceStatus::Unpaid]);

    expect(nextActionKeys($project))->toBe([]);
});

it('halaman proyek menampilkan peringatan dengan tautan langsung ke tempat yang tepat', function () {
    $project = nextActionProject($this->client, ProjectStatus::AwaitingDp);
    Invoice::factory()->for($project)->create(['type' => InvoiceType::Dp, 'paid_amount' => 0, 'status' => InvoiceStatus::Unpaid]);

    $this->actingAs($this->client)
        ->get(route('portal.projects.show', $project->id))
        ->assertOk()
        ->assertSee('data-next-action="pay_dp"', false)
        ->assertSee(__('next_action.pay_dp.title'))
        ->assertSee('href="'.route('portal.projects.invoices', $project->id).'"', false);
});

it('beranda portal menampilkan peringatan utama tiap proyek tanpa membocorkan proyek klien lain', function () {
    $mine = nextActionProject($this->client, ProjectStatus::Quoted);
    Quotation::factory()->sent()->for($mine)->create();
    $theirs = nextActionProject($this->other, ProjectStatus::AwaitingDp);
    Invoice::factory()->for($theirs)->create(['paid_amount' => 0, 'status' => InvoiceStatus::Unpaid]);

    $this->actingAs($this->client)
        ->get(route('portal.overview'))
        ->assertOk()
        ->assertSee('data-next-action="review_quotation"', false)
        ->assertDontSee('data-next-action="pay_dp"', false)
        ->assertDontSee($theirs->title);
});

it('peringatan mengikuti bahasa akun klien', function () {
    $project = nextActionProject($this->client, ProjectStatus::AwaitingDp);
    Invoice::factory()->for($project)->create(['type' => InvoiceType::Dp, 'paid_amount' => 0, 'status' => InvoiceStatus::Unpaid]);

    app()->setLocale('en');

    expect(ProjectNextActions::primary($project->fresh())->title)->toBe(__('next_action.pay_dp.title'))
        ->and(ProjectNextActions::primary($project->fresh())->title)->toContain('down payment');
});

it('jumlah query beranda portal tidak bertambah saat proyek dan invoice bertambah (tanpa N+1)', function () {
    $countQueries = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->client)->get(route('portal.overview'))->assertOk();

        return count(DB::getQueryLog());
    };

    $first = nextActionProject($this->client, ProjectStatus::AwaitingDp);
    Invoice::factory()->for($first)->create(['paid_amount' => 0, 'status' => InvoiceStatus::Unpaid]);
    $countQueries(); // pemanasan
    $sedikit = $countQueries();

    foreach ([ProjectStatus::AwaitingDp, ProjectStatus::Quoted, ProjectStatus::Active, ProjectStatus::AwaitingFinal] as $status) {
        $project = nextActionProject($this->client, $status);
        $invoice = Invoice::factory()->for($project)->create(['paid_amount' => 0, 'status' => InvoiceStatus::Unpaid]);
        Payment::factory()->for($invoice)->create(['status' => PaymentStatus::Rejected]);
        Quotation::factory()->sent()->for($project)->create();
    }
    $banyak = $countQueries();

    expect($banyak)->toBe($sedikit);
});

it('semua teks peringatan tersedia dalam dua bahasa dengan kunci yang sama', function () {
    $id = require lang_path('id/next_action.php');
    $en = require lang_path('en/next_action.php');

    expect(array_keys(Arr::dot($id)))->toBe(array_keys(Arr::dot($en)));
});
