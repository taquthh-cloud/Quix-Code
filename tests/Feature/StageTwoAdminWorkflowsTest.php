<?php

use App\Enums\ActivityAction;
use App\Enums\InvoiceStatus;
use App\Enums\TicketStatus;
use App\Livewire\Admin\Activity\Index as ActivityIndex;
use App\Livewire\Admin\Projects\Form as ProjectForm;
use App\Livewire\Admin\Projects\Kanban;
use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\RevisionTicket;
use App\Models\Setting;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->withTwoFactor()->create();
    $this->client = User::factory()->client()->create();
});

it('admin command center groups overdue invoices by aging bucket', function () {
    app()->setLocale('id');
    cache()->forget('admin.dashboard.invoice_aging');
    $project = Project::factory()->for($this->client, 'client')->create();
    Invoice::factory()->for($project)->create([
        'status' => InvoiceStatus::Sent,
        'amount' => 20_000,
        'paid_amount' => 5_000,
        'due_date' => now()->subDays(3)->toDateString(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('0–7 hari')
        ->assertSee('Rp 15.000');
});

it('admin can globally search projects and export filtered project csv', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['title' => 'Global search target']);

    $this->actingAs($this->admin)
        ->get(route('admin.search', ['q' => 'Global search target']))
        ->assertOk()
        ->assertSee('Global search target');

    $response = $this->get(route('admin.exports', ['type' => 'projects', 'q' => 'Global search target']));
    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('Global search target');
});

it('admin can manually create a lead with source attribution and reject duplicate email', function () {
    $this->actingAs($this->admin);
    Livewire::test(ProjectForm::class)
        ->set('newClient', true)
        ->set('client_name', 'Prospect One')
        ->set('client_email', 'prospect@example.test')
        ->set('client_whatsapp', '628123456789')
        ->set('lead_source', 'website')
        ->set('title', 'Website redesign')
        ->call('save')
        ->assertHasNoErrors();

    $project = Project::query()->where('title', 'Website redesign')->firstOrFail();
    expect($project->lead_source)->toBe('website')
        ->and($project->client->email)->toBe('prospect@example.test');

    Livewire::test(ProjectForm::class)
        ->set('newClient', true)
        ->set('client_name', 'Duplicate Prospect')
        ->set('client_email', 'prospect@example.test')
        ->set('title', 'Second project')
        ->call('save')
        ->assertHasErrors('client_email');
});

it('admin activity viewer can filter by project, user, and action', function () {
    $project = Project::factory()->for($this->client, 'client')->create();
    $otherProject = Project::factory()->for($this->client, 'client')->create();
    ActivityLog::create([
        'user_id' => $this->admin->id,
        'subject_type' => Project::class,
        'subject_id' => (string) $project->id,
        'action' => ActivityAction::StatusChanged->value,
        'properties' => ['old' => 'lead', 'new' => 'quoted'],
    ]);
    ActivityLog::create([
        'user_id' => $this->admin->id,
        'subject_type' => Project::class,
        'subject_id' => (string) $otherProject->id,
        'action' => ActivityAction::ProjectCreated->value,
        'properties' => ['title' => 'Unrelated project event'],
    ]);

    $this->actingAs($this->admin);
    Livewire::test(ActivityIndex::class)
        ->set('projectId', $project->id)
        ->set('userId', (string) $this->admin->id)
        ->set('action', ActivityAction::StatusChanged->value)
        ->assertSee('quoted')
        ->assertDontSee('Unrelated project event');
});

it('admin system settings persist money in minor units and tax in basis points', function () {
    $this->actingAs($this->admin);
    Livewire::test(SettingsIndex::class)
        ->set('companyName', 'Quix Code')
        ->set('companyAddress', 'Jakarta')
        ->set('companyNpwp', '12.345.678.9-012.000')
        ->set('bankName', 'Bank Example')
        ->set('bankAccountName', 'Quix Code')
        ->set('bankAccountNumber', '1234567890')
        ->set('taxRatePercent', '8.25')
        ->set('defaultRateIdr', '500000')
        ->set('defaultRateUsd', '10.25')
        ->set('invoiceFooterId', 'Terima kasih')
        ->set('invoiceFooterEn', 'Thank you')
        ->set('termsTemplateId', 'Ketentuan berlaku')
        ->set('termsTemplateEn', 'Terms apply')
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::where('key', 'company.name')->firstOrFail()->value['value'])->toBe('Quix Code')
        ->and(Setting::where('key', 'tax.rate_bp')->firstOrFail()->value['basis_points'])->toBe(825)
        ->and(Setting::where('key', 'rate.default_idr_minor')->firstOrFail()->value)->toBe(500_000)
        ->and(Setting::where('key', 'rate.default_usd_minor')->firstOrFail()->value)->toBe(1_025);
});

it('admin can open the global ticket kanban and billing directory', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['title' => 'Website Quix']);
    RevisionTicket::factory()->for($project)->create(['title' => 'Align mobile header']);

    $this->actingAs($this->admin)
        ->get(route('admin.kanban'))
        ->assertOk()
        ->assertSee('Align mobile header')
        ->assertSee('Website Quix');

    $this->get(route('admin.billing.index'))->assertOk();
});

it('ticket status changes are authorized and audited', function () {
    $project = Project::factory()->for($this->client, 'client')->create();
    $ticket = RevisionTicket::factory()->for($project)->create();

    $this->actingAs($this->admin);
    Livewire::test(Kanban::class)
        ->call('setTicketStatus', $ticket->id, TicketStatus::InProgress->value)
        ->assertHasNoErrors();

    expect($ticket->fresh()->status)->toBe(TicketStatus::InProgress)
        ->and(ActivityLog::where('subject_type', RevisionTicket::class)
            ->where('subject_id', (string) $ticket->id)
            ->where('action', ActivityAction::TicketStatusChanged->value)
            ->exists())->toBeTrue();
});

it('staff only see and move tickets in assigned projects', function () {
    $assignedProject = Project::factory()->for($this->client, 'client')->create();
    $otherProject = Project::factory()->for($this->client, 'client')->create();
    $staff = User::factory()->staff()->create();
    $assignedProject->staff()->attach($staff->id);
    $assignedTicket = RevisionTicket::factory()->for($assignedProject)->create(['title' => 'Assigned ticket']);
    $otherTicket = RevisionTicket::factory()->for($otherProject)->create(['title' => 'Private ticket']);

    $this->actingAs($staff)
        ->get(route('admin.kanban'))
        ->assertOk()
        ->assertSee('Assigned ticket')
        ->assertDontSee('Private ticket');

    Livewire::test(Kanban::class)
        ->call('setTicketStatus', $assignedTicket->id, TicketStatus::InProgress->value)
        ->assertHasNoErrors();

    expect($assignedTicket->fresh()->status)->toBe(TicketStatus::InProgress)
        ->and($otherTicket->fresh()->status)->toBe(TicketStatus::Open);
});

it('sortable kanban updates status and order for a drop destination', function () {
    $project = Project::factory()->for($this->client, 'client')->create();
    $first = RevisionTicket::factory()->for($project)->create(['sort_order' => 8]);
    $second = RevisionTicket::factory()->for($project)->create(['sort_order' => 4]);

    $this->actingAs($this->admin);
    Livewire::test(Kanban::class)
        ->call('reorderTickets', TicketStatus::InProgress->value, [$second->id, $first->id])
        ->assertHasNoErrors();

    expect($second->fresh()->status)->toBe(TicketStatus::InProgress)
        ->and($second->fresh()->sort_order)->toBe(0)
        ->and($first->fresh()->status)->toBe(TicketStatus::InProgress)
        ->and($first->fresh()->sort_order)->toBe(1);
});
