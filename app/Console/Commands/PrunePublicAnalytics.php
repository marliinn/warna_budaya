<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PrunePublicAnalytics extends Command
{
    protected $signature = 'analytics:prune';

    protected $description = 'Remove expired anonymous public analytics records';

    public function handle(): int
    {
        if (! Schema::hasTable('public_page_views') || ! Schema::hasTable('analytics_visitors')) {
            $this->warn('Analytics tables are not available; no records were changed.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays(max(1, (int) config('analytics.retention_days', 90)));

        $deletedViews = DB::table('public_page_views')
            ->where('viewed_at', '<', $cutoff)
            ->delete();
        $deletedVisitors = DB::table('analytics_visitors')
            ->where('expires_at', '<=', now())
            ->delete();

        $this->info("Removed {$deletedViews} expired page views and {$deletedVisitors} expired visitor records.");

        return self::SUCCESS;
    }
}
