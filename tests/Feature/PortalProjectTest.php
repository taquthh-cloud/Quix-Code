<?php

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Livewire\Portal\ProjectOverview;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

it('shows project progress and milestones only to the owning client', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->active()->for($client, 'client')->create([
        'title' => 'Portal Klien',
        'progress_percent' => 65,
    ]);
    $project->milestones()->make([
        'title' => 'Implementasi',
        'target_progress' => 60,
        'sort_order' => 0,
    ])->forceFill(['status' => 'done'])->save();

    $this->actingAs($client)
        ->get(route('portal.projects.show', $project->id))
        ->assertOk()
        ->assertSee('Portal Klien')
        ->assertSee('65%')
        ->assertSee('Implementasi');

    $otherClient = User::factory()->client()->create();
    $this->actingAs($otherClient)
        ->get(route('portal.projects.show', $project->id))
        ->assertNotFound();
});

it('lets a client submit a validated revision or bug request for their project', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->active()->for($client, 'client')->create();

    $this->actingAs($client);

    Livewire::test(ProjectOverview::class, ['project' => $project->id])
        ->set('title', 'Tombol simpan tidak merespons')
        ->set('description', 'Saat ditekan, halaman tetap tidak berubah.')
        ->set('category', TicketCategory::BugReport->value)
        ->set('priority', TicketPriority::High->value)
        ->call('submitRequest')
        ->assertHasNoErrors()
        ->assertSee('Tombol simpan tidak merespons');

    $ticket = $project->tickets()->firstOrFail();

    expect($ticket->created_by)->toBe($client->id)
        ->and($ticket->category)->toBe(TicketCategory::BugReport)
        ->and($ticket->priority)->toBe(TicketPriority::High);
});

it('rejects invalid request fields without creating a ticket', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->active()->for($client, 'client')->create();

    $this->actingAs($client);

    Livewire::test(ProjectOverview::class, ['project' => $project->id])
        ->set('title', '')
        ->set('description', str_repeat('x', 5001))
        ->set('category', 'unexpected')
        ->set('priority', 'urgent')
        ->call('submitRequest')
        ->assertHasErrors(['title', 'description', 'category', 'priority']);

    expect($project->tickets()->count())->toBe(0);
});
