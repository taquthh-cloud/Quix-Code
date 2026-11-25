<?php

use App\Enums\ProjectStatus;
use App\Livewire\Admin\Leads\Index;
use App\Livewire\Chat\Room;
use App\Models\OrderChat;
use App\Models\Project;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('id');
    $this->admin = User::factory()->admin()->withTwoFactor()->create();
    $this->staff = User::factory()->staff()->create();
    $this->client = User::factory()->client()->create();
    $this->project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
});

it('interval polling naik bertahap saat percakapan sepi', function () {
    expect(Room::intervalFor(0))->toBe(Room::POLL_FAST)
        ->and(Room::intervalFor(Room::FAST_TICKS - 1))->toBe(Room::POLL_FAST)
        ->and(Room::intervalFor(Room::FAST_TICKS))->toBe(Room::POLL_MEDIUM)
        ->and(Room::intervalFor(Room::MEDIUM_TICKS - 1))->toBe(Room::POLL_MEDIUM)
        ->and(Room::intervalFor(Room::MEDIUM_TICKS))->toBe(Room::POLL_SLOW)
        ->and(Room::intervalFor(10_000))->toBe(Room::POLL_SLOW);
});

it('chat sepi merenggangkan polling di halaman, pesan baru mengembalikannya ke rapat', function () {
    $this->actingAs($this->client);

    $room = Livewire::test(Room::class, ['projectId' => $this->project->id])
        ->assertSeeHtml('wire:poll.5s.visible');

    for ($i = 0; $i < Room::FAST_TICKS; $i++) {
        $room->call('$refresh');
    }
    $room->assertSeeHtml('wire:poll.15s.visible');

    for ($i = 0; $i < Room::MEDIUM_TICKS; $i++) {
        $room->call('$refresh');
    }
    $room->assertSeeHtml('wire:poll.30s.visible');

    $chat = OrderChat::factory()->create(['project_id' => $this->project->id, 'sender_id' => $this->admin->id]);

    $room->call('$refresh')
        ->assertSet('idleTicks', 0)
        ->assertSet('lastSeenId', $chat->id)
        ->assertSeeHtml('wire:poll.5s.visible');
});

it('mengirim pesan mengembalikan polling ke interval rapat', function () {
    $this->actingAs($this->client);

    $room = Livewire::test(Room::class, ['projectId' => $this->project->id]);

    for ($i = 0; $i < Room::MEDIUM_TICKS; $i++) {
        $room->call('$refresh');
    }
    $room->assertSeeHtml('wire:poll.30s.visible');

    $room->set('message', 'Halo')->call('send')->assertSeeHtml('wire:poll.5s.visible');
});

it('penghitung polling tidak bisa dimanipulasi dari browser', function () {
    $this->actingAs($this->client);

    $room = Livewire::test(Room::class, ['projectId' => $this->project->id]);

    expect(fn () => $room->set('idleTicks', 999))->toThrow(CannotUpdateLockedPropertyException::class)
        ->and(fn () => $room->set('lastSeenId', 999))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('hitungan belum dibaca per pengguna: klien hanya pesan admin, admin hanya pesan klien, staf selalu nol', function () {
    OrderChat::factory()->count(2)->create(['project_id' => $this->project->id, 'sender_id' => $this->admin->id]);
    OrderChat::factory()->create(['project_id' => $this->project->id, 'sender_id' => $this->admin->id, 'read_at' => now()]);
    OrderChat::factory()->count(3)->create(['project_id' => $this->project->id, 'sender_id' => $this->client->id]);

    expect(OrderChat::unreadFor($this->client)->count())->toBe(2)
        ->and(OrderChat::unreadFor($this->admin)->count())->toBe(3)
        ->and(OrderChat::unreadFor($this->staff)->count())->toBe(0);
});

it('klien tidak menghitung pesan di proyek klien lain', function () {
    $other = Project::factory()->for(User::factory()->client()->create(), 'client')->create();
    OrderChat::factory()->create(['project_id' => $other->id, 'sender_id' => $this->admin->id]);

    expect(OrderChat::unreadFor($this->client)->count())->toBe(0);
});

it('beranda portal menampilkan jumlah pesan belum dibaca per proyek, dan hilang setelah chat dibuka', function () {
    OrderChat::factory()->count(2)->create(['project_id' => $this->project->id, 'sender_id' => $this->admin->id]);

    $this->actingAs($this->client)
        ->get(route('portal.overview'))
        ->assertOk()
        ->assertSee('2 pesan baru', false);

    Livewire::test(Room::class, ['projectId' => $this->project->id]);

    $this->get(route('portal.overview'))->assertOk()->assertDontSee('pesan baru', false);
});

it('daftar prospek admin menghitung pesan klien yang belum dibaca', function () {
    OrderChat::factory()->count(2)->create(['project_id' => $this->project->id, 'sender_id' => $this->client->id]);
    OrderChat::factory()->create(['project_id' => $this->project->id, 'sender_id' => $this->admin->id]);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertViewHas('leads', fn ($leads) => $leads->first()->unread_chats_count === 2);
});
