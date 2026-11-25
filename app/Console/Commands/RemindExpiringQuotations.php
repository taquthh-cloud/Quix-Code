<?php

namespace App\Console\Commands;

use App\Models\Quotation;
use App\Services\QuotationService;
use Illuminate\Console\Command;

class RemindExpiringQuotations extends Command
{
    protected $signature = 'quotations:remind';

    protected $description = 'Catat pengingat untuk quotation terkirim yang masa berlakunya tinggal 3 hari atau kurang (T3-06)';

    public function handle(QuotationService $quotations): int
    {
        $count = $quotations->remindExpiring();

        $this->info("{$count} pengingat dicatat (batas ".Quotation::REMINDER_DAYS.' hari).');

        return self::SUCCESS;
    }
}
