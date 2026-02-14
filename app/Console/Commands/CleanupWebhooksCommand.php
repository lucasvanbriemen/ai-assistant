<?php

namespace App\Console\Commands;

use App\Models\WebhookLog;
use Illuminate\Console\Command;

class CleanupWebhooksCommand extends Command
{
    protected $signature = 'cleanup:webhooks {--days=30 : Delete webhooks older than this many days}';
    protected $description = 'Clean up old webhook logs';

    public function handle()
    {
        $days = $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up webhooks older than {$days} days ({$cutoffDate->toDateString()})...");

        $deleted = WebhookLog::where('created_at', '<', $cutoffDate)->delete();

        $this->info("Deleted {$deleted} webhook logs.");

        return Command::SUCCESS;
    }
}
