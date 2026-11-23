<?php

namespace Database\Seeders;

use App\Enums\MilestoneStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Data demo LOKAL: 1 admin, 1 staf, 1 klien, 1 proyek. Password semua akun: "password". */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin Quix', 'email' => 'admin@quixcode.test']);
        $staff = User::factory()->staff()->create(['name' => 'Staf Quix', 'email' => 'staff@quixcode.test']);
        $client = User::factory()->client()->create(['name' => 'Klien Demo', 'email' => 'klien@quixcode.test']);

        $project = Project::factory()->active()->for($client, 'client')->create([
            'title' => 'Sistem Kasir Toko Demo',
        ]);

        $project->staff()->attach($staff->id, ['task_role' => 'backend']);

        foreach ([25, 50, 75, 100] as $i => $target) {
            ProjectMilestone::factory()->for($project)->create([
                'title' => 'Tahap '.($i + 1),
                'target_progress' => $target,
                'sort_order' => $i,
                'status' => $target <= $project->progress_percent ? MilestoneStatus::Done : MilestoneStatus::Pending,
            ]);
        }

        $quotation = Quotation::factory()->accepted()->for($project)->create();
        QuotationItem::factory()->count(3)->for($quotation)->sequence(
            ['title' => 'Modul kasir', 'sort_order' => 0],
            ['title' => 'Manajemen stok', 'sort_order' => 1],
            ['title' => 'Laporan penjualan', 'sort_order' => 2],
        )->create();

        Invoice::factory()->paid()->for($project)->create(['invoice_number' => 'INV-DEMO-0001']);
    }
}
