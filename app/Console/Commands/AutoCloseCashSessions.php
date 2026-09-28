<?php

namespace App\Console\Commands;

use App\Models\CashSession;
use Illuminate\Console\Command;

class AutoCloseCashSessions extends Command
{
    protected $signature = 'cash-sessions:auto-close';

    protected $description = 'Auto-close cash sessions left open from a previous business day and flag them for reconciliation';

    public function handle(): int
    {
        $count = CashSession::closeStale();
        $this->info("Auto-closed {$count} cash session(s).");

        return self::SUCCESS;
    }
}
