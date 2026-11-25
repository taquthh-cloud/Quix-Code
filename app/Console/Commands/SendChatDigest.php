<?php

namespace App\Console\Commands;

use App\Services\ChatDigestService;
use Illuminate\Console\Command;

class SendChatDigest extends Command
{
    protected $signature = 'chat:digest';

    protected $description = 'Kirim digest email untuk pesan chat negosiasi yang belum dibaca (T3-09)';

    public function handle(ChatDigestService $digest): int
    {
        $count = $digest->sendDue();

        $this->info("{$count} email digest chat terkirim.");

        return self::SUCCESS;
    }
}
