<?php

use App\Enums\ActivityAction;
use App\Enums\ProjectStatus;
use App\Livewire\Chat\Room;
use App\Models\ActivityLog;
use App\Models\OrderChat;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('id');
    $this->admin = User::factory()->admin()->withTwoFactor()->create();
    $this->staff = User::factory()->staff()->create();
    $this->client = User::factory()->client()->create();
    $this->project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    $quotation = Quotation::factory()->sent()->for($this->project)->create();
    QuotationItem::factory()->for($quotation)->create();
});

it('klien mengirim pesan: tersimpan apa adanya (dipangkas) dan proyek masuk negosiasi', function () {
    $this->actingAs($this->client);

    Livewire::test(Room::class, ['projectId' => $this->project->id])
        ->set('message', '  Bisa kurang sedikit? ')
        ->call('send')
        ->assertHasNoErrors()
        ->assertDispatched('chat-sent')
        ->assertSet('message', '');

    $chat = OrderChat::firstOrFail();

    expect($chat->message)->toBe('Bisa kurang sedikit?')
        ->and($chat->sender_id)->toBe($this->client->id)
        ->and($chat->project_id)->toBe($this->project->id)
        ->and($this->project->fresh()->status)->toBe(ProjectStatus::Negotiating)
        ->and(ActivityLog::where('action', ActivityAction::StatusChanged->value)->first()->properties)
        ->toEqual(['old' => 'quoted', 'new' => 'negotiating']);
});

it('pesan berikutnya tidak mencatat perpindahan status lagi', function () {
    $this->actingAs($this->client);
    $room = Livewire::test(Room::class, ['projectId' => $this->project->id]);

    $room->set('message', 'Satu')->call('send');
    $room->set('message', 'Dua')->call('send');

    expect(OrderChat::count())->toBe(2)
        ->and(ActivityLog::where('action', ActivityAction::StatusChanged->value)->count())->toBe(1);
});

it('pesan admin tidak mengubah status proyek', function () {
    $this->actingAs($this->admin);

    Livewire::test(Room::class, ['projectId' => $this->project->id])->set('message', 'Halo, ada yang bisa dibantu?')->call('send');

    expect(OrderChat::count())->toBe(1)->and($this->project->fresh()->status)->toBe(ProjectStatus::Quoted);
});

it('pesan kosong atau terlalu panjang ditolak', function () {
    $this->actingAs($this->client);
    $room = Livewire::test(Room::class, ['projectId' => $this->project->id]);

    $room->set('message', '   ')->call('send')->assertHasErrors('message');
    $room->set('message', str_repeat('a', Room::MAX_LENGTH + 1))->call('send')->assertHasErrors('message');

    expect(OrderChat::count())->toBe(0);
});

it('staf tidak bisa membuka chat negosiasi, walau ditugaskan di proyek', function () {
    $this->project->staff()->attach($this->staff->id);

    $this->actingAs($this->staff);
    Livewire::test(Room::class, ['projectId' => $this->project->id])->assertForbidden();

    $this->get(route('admin.projects.negotiation', $this->project->id))->assertForbidden();
});

it('klien lain tidak bisa membuka halaman yang memuat chat', function () {
    $other = User::factory()->client()->create();

    $this->actingAs($other)->get(route('portal.projects.negotiation', $this->project->id))->assertNotFound();
});

it('chat proyek yang sudah selesai atau dibatalkan tidak bisa dikirimi pesan', function () {
    $this->project->forceFill(['status' => ProjectStatus::Cancelled])->save();

    $this->actingAs($this->client);
    Livewire::test(Room::class, ['projectId' => $this->project->id])
        ->set('message', 'Halo')
        ->call('send')
        ->assertForbidden();

    expect(OrderChat::count())->toBe(0);
});

it('membuka chat menandai pesan lawan bicara sebagai terbaca, bukan pesan sendiri', function () {
    $fromClient = OrderChat::factory()->for($this->project)->create(['sender_id' => $this->client->id]);
    $fromAdmin = OrderChat::factory()->for($this->project)->create(['sender_id' => $this->admin->id]);

    $this->actingAs($this->admin);
    Livewire::test(Room::class, ['projectId' => $this->project->id]);

    expect($fromClient->fresh()->read_at)->not->toBeNull()
        ->and($fromAdmin->fresh()->read_at)->toBeNull();
});

it('pesan di-escape dan hanya 100 pesan terbaru yang tampil', function () {
    foreach (range(1, 105) as $n) {
        OrderChat::factory()->for($this->project)->create([
            'sender_id' => $this->client->id,
            'message' => sprintf('msg-%03d', $n),
        ]);
    }
    OrderChat::factory()->for($this->project)->create([
        'sender_id' => $this->client->id,
        'message' => '<script>alert(1)</script>',
    ]);

    $this->actingAs($this->admin);
    Livewire::test(Room::class, ['projectId' => $this->project->id])
        ->assertSee('msg-105')
        ->assertDontSee('msg-001')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

it('halaman negosiasi admin dan klien memuat chat', function () {
    $this->actingAs($this->admin)->get(route('admin.projects.negotiation', $this->project->id))
        ->assertOk()->assertSee('Chat negosiasi');

    $this->actingAs($this->client)->get(route('portal.projects.negotiation', $this->project->id))
        ->assertOk()->assertSee('Chat negosiasi');
});

it('semua teks Tahap 3 tersedia dalam dua bahasa', function () {
    $flatten = function (array $a, string $prefix = '') use (&$flatten): array {
        $out = [];
        foreach ($a as $k => $v) {
            is_array($v) ? $out += $flatten($v, "{$prefix}{$k}.") : $out["{$prefix}{$k}"] = true;
        }

        return $out;
    };

    foreach (['quotations', 'chat', 'portal', 'activity'] as $group) {
        $id = array_keys($flatten(require lang_path("id/{$group}.php")));
        $en = array_keys($flatten(require lang_path("en/{$group}.php")));

        expect($en)->toEqualCanonicalizing($id);
    }
});
