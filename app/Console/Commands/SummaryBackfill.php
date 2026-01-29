<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SummaryBackfill extends Command
{
    protected $signature = 'summaries:backfill {--limit=100} {--dry-run}';
    protected $description = 'Enqueue the most recent N news rows for summarization';

    public function handle()
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $ids = DB::table('news')
            ->select('id')
            ->whereNotNull('content')
            ->whereRaw("length(trim(content)) > 0")
            ->orderByRaw('date_sent DESC NULLS LAST')
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id')
            ->all();

        if (!$ids) {
            $this->info('No news rows found for backfill.');
            return Command::SUCCESS;
        }

        $now = now();
        $rows = [];
        foreach ($ids as $id) {
            $rows[] = [
                'news_id' => $id,
                'enqueued_at' => $now,
                'reason' => 'backfill',
                'tries' => 0,
            ];
        }

        if ($dryRun) {
            $this->info('Would enqueue ' . count($rows) . ' news rows.');
            return Command::SUCCESS;
        }

        DB::table('news_summary_queue')->upsert(
            $rows,
            ['news_id'],
            ['enqueued_at', 'reason', 'tries']
        );

        $this->info('Enqueued ' . count($rows) . ' news rows.');

        return Command::SUCCESS;
    }
}
