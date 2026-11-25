<?php

namespace App\Console\Commands;

use App\Services\QuotationService;
use Illuminate\Console\Command;

class ExpireQuotations extends Command
{
    protected $signature = 'quotations:expire';

    protected $description = 'Tandai quotation terkirim yang melewati masa berlaku sebagai kedaluwarsa (T3-06)';

    public function handle(QuotationService $quotations): int
    {
        $count = $quotations->expireOverdue();

        $this->info("{$count} quotation ditandai kedaluwarsa.");

        return self::SUCCESS;
    }
}
