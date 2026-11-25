<?php

use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Models\Invoice;
use App\Models\OrderChat;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Quotation;
use App\Models\RevisionTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->withTwoFactor()->create();
    $this->staff = User::factory()->staff()->create();       // ditugaskan di proyek A
    $this->otherStaff = User::factory()->staff()->create();  // tidak ditugaskan
    $this->clientA = User::factory()->client()->create();
    $this->clientB = User::factory()->client()->create();

    $this->projectA = Project::factory()->active()->for($this->clientA, 'client')->create();
    $this->projectB = Project::factory()->active()->for($this->clientB, 'client')->create();
    $this->projectA->staff()->attach($this->staff->id);
});

// ---------------------------------------------------------------- isolasi klien

it('klien A tidak bisa membuka data klien B', function () {
    $quotationB = Quotation::factory()->sent()->for($this->projectB)->create();
    $invoiceB = Invoice::factory()->for($this->projectB)->create();
    $chatB = OrderChat::factory()->for($this->projectB)->create(['sender_id' => $this->clientB->id]);
    $ticketB = RevisionTicket::factory()->for($this->projectB)->create(['created_by' => $this->clientB->id]);
    $milestoneB = ProjectMilestone::factory()->for($this->projectB)->create();

    expect($this->clientA->can('view', $this->projectB))->toBeFalse()
        ->and($this->clientA->can('viewPrice', $this->projectB))->toBeFalse()
        ->and($this->clientA->can('view', $quotationB))->toBeFalse()
        ->and($this->clientA->can('respond', $quotationB))->toBeFalse()
        ->and($this->clientA->can('view', $invoiceB))->toBeFalse()
        ->and($this->clientA->can('uploadProof', $invoiceB))->toBeFalse()
        ->and($this->clientA->can('view', $chatB))->toBeFalse()
        ->and($this->clientA->can('create', [OrderChat::class, $this->projectB]))->toBeFalse()
        ->and($this->clientA->can('view', $ticketB))->toBeFalse()
        ->and($this->clientA->can('comment', $ticketB))->toBeFalse()
        ->and($this->clientA->can('create', [RevisionTicket::class, $this->projectB]))->toBeFalse()
        ->and($this->clientA->can('view', $milestoneB))->toBeFalse();
});

it('klien A bisa membuka data miliknya sendiri', function () {
    $quotation = Quotation::factory()->sent()->for($this->projectA)->create();
    $invoice = Invoice::factory()->for($this->projectA)->create();
    $chat = OrderChat::factory()->for($this->projectA)->create(['sender_id' => $this->clientA->id]);
    $ticket = RevisionTicket::factory()->for($this->projectA)->create(['created_by' => $this->clientA->id]);

    expect($this->clientA->can('view', $this->projectA))->toBeTrue()
        ->and($this->clientA->can('viewPrice', $this->projectA))->toBeTrue()
        ->and($this->clientA->can('view', $quotation))->toBeTrue()
        ->and($this->clientA->can('view', $invoice))->toBeTrue()
        ->and($this->clientA->can('view', $chat))->toBeTrue()
        ->and($this->clientA->can('create', [OrderChat::class, $this->projectA]))->toBeTrue()
        ->and($this->clientA->can('view', $ticket))->toBeTrue()
        ->and($this->clientA->can('comment', $ticket))->toBeTrue()
        ->and($this->clientA->can('create', [RevisionTicket::class, $this->projectA]))->toBeTrue();
});

it('query klien tidak pernah memuat data klien lain', function () {
    Invoice::factory()->for($this->projectA)->create();
    Invoice::factory()->for($this->projectB)->create();
    OrderChat::factory()->for($this->projectB)->create(['sender_id' => $this->clientB->id]);
    RevisionTicket::factory()->for($this->projectB)->create(['created_by' => $this->clientB->id]);

    expect(Invoice::visibleTo($this->clientA)->count())->toBe(1)
        ->and(OrderChat::visibleTo($this->clientA)->count())->toBe(0)
        ->and(RevisionTicket::visibleTo($this->clientA)->count())->toBe(0);
});

// ---------------------------------------------------------------- staf & harga

it('staf tidak bisa melihat harga, invoice, quotation, maupun chat negosiasi', function () {
    $quotation = Quotation::factory()->sent()->for($this->projectA)->create();
    $invoice = Invoice::factory()->for($this->projectA)->create();
    $chat = OrderChat::factory()->for($this->projectA)->create(['sender_id' => $this->clientA->id]);

    expect($this->staff->can('view', $this->projectA))->toBeTrue() // proyeknya boleh dibuka
        ->and($this->staff->can('viewPrice', $this->projectA))->toBeFalse()
        ->and($this->staff->can('view', $invoice))->toBeFalse()
        ->and($this->staff->can('download', $invoice))->toBeFalse()
        ->and($this->staff->can('viewAny', [Invoice::class, $this->projectA]))->toBeFalse()
        ->and($this->staff->can('view', $quotation))->toBeFalse()
        ->and($this->staff->can('create', [Quotation::class, $this->projectA]))->toBeFalse()
        ->and($this->staff->can('view', $chat))->toBeFalse()
        ->and($this->staff->can('viewAny', [OrderChat::class, $this->projectA]))->toBeFalse()
        ->and($this->staff->can('create', [OrderChat::class, $this->projectA]))->toBeFalse();
});

it('harga disembunyikan dari staf di level query, bukan hanya tampilan', function () {
    Quotation::factory()->sent()->for($this->projectA)->create();
    Invoice::factory()->for($this->projectA)->create();
    OrderChat::factory()->for($this->projectA)->create(['sender_id' => $this->clientA->id]);

    $forStaff = Project::visibleTo($this->staff)->first();
    $forAdmin = Project::visibleTo($this->admin)->find($this->projectA->id);

    expect($forAdmin->final_price)->toBe(25_000_000)
        ->and($forStaff->getAttributes())->not->toHaveKey('final_price')
        ->and($forStaff->getAttributes())->not->toHaveKey('budget_reference')
        ->and(fn () => $forStaff->final_price)->toThrow(MissingAttributeException::class)
        ->and($forStaff->toArray())->not->toHaveKey('final_price')
        ->and(Invoice::visibleTo($this->staff)->count())->toBe(0)
        ->and(Quotation::visibleTo($this->staff)->count())->toBe(0)
        ->and(OrderChat::visibleTo($this->staff)->count())->toBe(0);
});

it('staf hanya melihat proyek yang ditugaskan', function () {
    expect($this->staff->can('view', $this->projectA))->toBeTrue()
        ->and($this->staff->can('view', $this->projectB))->toBeFalse()
        ->and($this->otherStaff->can('view', $this->projectA))->toBeFalse()
        ->and(Project::visibleTo($this->otherStaff)->count())->toBe(0);
});

it('staf bisa membalas tiket proyeknya tetapi tidak bisa membuat tiket', function () {
    $ticket = RevisionTicket::factory()->for($this->projectA)->create(['created_by' => $this->clientA->id]);

    expect($this->staff->can('comment', $ticket))->toBeTrue()
        ->and($this->staff->can('updateStatus', $ticket))->toBeTrue()
        ->and($this->staff->can('create', [RevisionTicket::class, $this->projectA]))->toBeFalse()
        ->and($this->otherStaff->can('comment', $ticket))->toBeFalse()
        ->and($this->otherStaff->can('view', $ticket))->toBeFalse()
        ->and(RevisionTicket::visibleTo($this->staff)->count())->toBe(1)
        ->and(RevisionTicket::visibleTo($this->otherStaff)->count())->toBe(0);
});

it('staf hanya boleh menggeser milestone yang ditugaskan kepadanya', function () {
    $mine = ProjectMilestone::factory()->for($this->projectA)->create(['assigned_to' => $this->staff->id]);
    $notMine = ProjectMilestone::factory()->for($this->projectA)->create(['assigned_to' => null]);

    expect($this->staff->can('move', $mine))->toBeTrue()
        ->and($this->staff->can('move', $notMine))->toBeFalse()
        ->and($this->staff->can('update', $mine))->toBeFalse()
        ->and($this->otherStaff->can('move', $mine))->toBeFalse()
        ->and($this->clientA->can('move', $mine))->toBeFalse()
        ->and($this->admin->can('move', $notMine))->toBeTrue();
});

// ---------------------------------------------------------------- quotation

it('klien tidak bisa melihat draf quotation dan hanya bisa merespons yang masih berlaku', function () {
    $draft = Quotation::factory()->for($this->projectA)->create(['version' => 1]);
    $sent = Quotation::factory()->sent()->for($this->projectA)->create(['version' => 2]);
    $expired = Quotation::factory()->sent()->for($this->projectA)->create([
        'version' => 3, 'valid_until' => now()->subDays(2),
    ]);

    expect($this->clientA->can('view', $draft))->toBeFalse()
        ->and($this->clientA->can('view', $sent))->toBeTrue()
        ->and($this->clientA->can('respond', $sent))->toBeTrue()
        ->and($this->clientA->can('respond', $expired))->toBeFalse()
        ->and($this->clientA->can('respond', $draft))->toBeFalse()
        ->and(Quotation::visibleTo($this->clientA)->count())->toBe(2);
});

it('hanya admin yang membuat dan mengubah quotation, dan hanya saat masih draf', function () {
    $draft = Quotation::factory()->for($this->projectA)->create(['version' => 1]);
    $sent = Quotation::factory()->sent()->for($this->projectA)->create(['version' => 2]);

    expect($this->admin->can('create', [Quotation::class, $this->projectA]))->toBeTrue()
        ->and($this->admin->can('update', $draft))->toBeTrue()
        ->and($this->admin->can('update', $sent))->toBeFalse()
        ->and($this->clientA->can('create', [Quotation::class, $this->projectA]))->toBeFalse()
        ->and($this->clientA->can('update', $draft))->toBeFalse();
});

// ---------------------------------------------------------------- invoice & handover

it('klien hanya bisa mengunggah bukti bayar untuk invoice yang belum dibayar', function () {
    $unpaid = Invoice::factory()->for($this->projectA)->create();
    $paid = Invoice::factory()->paid()->for($this->projectA)->create();

    expect($this->clientA->can('uploadProof', $unpaid))->toBeTrue()
        ->and($this->clientA->can('uploadProof', $paid))->toBeFalse()
        ->and($this->clientB->can('uploadProof', $unpaid))->toBeFalse()
        ->and($this->admin->can('uploadProof', $unpaid))->toBeFalse();
});

it('arsip handover tidak bisa diunduh sebelum invoice final lunas', function () {
    $final = Invoice::factory()->final()->for($this->projectA)->create(); // unpaid

    expect($this->clientA->can('downloadHandover', $this->projectA))->toBeFalse();

    $final->forceFill(['status' => InvoiceStatus::AwaitingVerification])->save();
    expect($this->clientA->can('downloadHandover', $this->projectA))->toBeFalse();

    $final->forceFill(['status' => InvoiceStatus::Paid, 'paid_amount' => $final->amount])->save();
    expect($this->clientA->can('downloadHandover', $this->projectA))->toBeTrue()
        ->and($this->clientB->can('downloadHandover', $this->projectA))->toBeFalse()
        ->and($this->staff->can('downloadHandover', $this->projectA))->toBeFalse();
});

it('tanpa invoice final sama sekali, arsip juga tidak bisa diunduh', function () {
    expect($this->clientA->can('downloadHandover', $this->projectA))->toBeFalse();
});

it('hanya klien pemilik yang memilih metode handover, dan hanya saat status handover', function () {
    expect($this->clientA->can('chooseHandover', $this->projectA))->toBeFalse();

    $this->projectA->forceFill(['status' => ProjectStatus::Handover])->save();

    expect($this->clientA->can('chooseHandover', $this->projectA))->toBeTrue()
        ->and($this->clientB->can('chooseHandover', $this->projectA))->toBeFalse()
        ->and($this->admin->can('chooseHandover', $this->projectA))->toBeFalse()
        ->and($this->staff->can('chooseHandover', $this->projectA))->toBeFalse();
});

// ---------------------------------------------------------------- admin & gate

it('hanya admin yang mengatur integrasi, penugasan, dan publikasi case study', function () {
    foreach (['manageIntegration', 'assignStaff', 'publishCaseStudy', 'transition', 'update', 'delete'] as $ability) {
        expect($this->admin->can($ability, $this->projectA))->toBeTrue("admin {$ability}")
            ->and($this->staff->can($ability, $this->projectA))->toBeFalse("staf {$ability}")
            ->and($this->clientA->can($ability, $this->projectA))->toBeFalse("klien {$ability}");
    }

    expect($this->admin->can('create', Project::class))->toBeTrue()
        ->and($this->staff->can('create', Project::class))->toBeFalse()
        ->and($this->clientA->can('create', Project::class))->toBeFalse();
});

it('chat tidak bisa dikirim ke proyek yang sudah selesai atau dibatalkan', function () {
    $this->projectA->forceFill(['status' => ProjectStatus::Cancelled])->save();

    expect($this->clientA->can('create', [OrderChat::class, $this->projectA]))->toBeFalse()
        ->and($this->admin->can('create', [OrderChat::class, $this->projectA]))->toBeFalse();
});

it('gate admin dan access-internal bekerja per peran', function () {
    expect(Gate::forUser($this->admin)->allows('admin'))->toBeTrue()
        ->and(Gate::forUser($this->staff)->allows('admin'))->toBeFalse()
        ->and(Gate::forUser($this->clientA)->allows('admin'))->toBeFalse()
        ->and(Gate::forUser($this->admin)->allows('access-internal'))->toBeTrue()
        ->and(Gate::forUser($this->staff)->allows('access-internal'))->toBeTrue()
        ->and(Gate::forUser($this->clientA)->allows('access-internal'))->toBeFalse();
});
