<?php

namespace App\Console\Commands;

use App\Models\AccountMigration;
use Illuminate\Console\Command;

class PruneCommand extends Command
{
    protected $signature = 'transit:prune {--days= : Days after expiry to keep unsealed intents}';

    protected $description = 'Delete stored intents that expired without ever being sealed';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('transit.prune_after_days'));

        $deleted = AccountMigration::whereNull('sealed_at')
            ->where('expires_at', '<', now()->subDays(max(0, $days)))
            ->delete();

        $this->components->info('Pruned '.$deleted.' unsealed intent(s). Their log entries are kept.');

        return self::SUCCESS;
    }
}
