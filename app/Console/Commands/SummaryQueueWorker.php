<?php

namespace App\Console\Commands;

use App\Models\NewsSummary;
use App\Services\NewsSummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SummaryQueueWorker extends Command
{
    protected $signature = 'summaries:queue {--once} {--page=50} {--sleep=500} {--pause=500}';
    protected $description = 'Drain news_summary_queue: summarize + fill news_summaries';

    public function handle(NewsSummaryService $summarizer)
    {
        $once = (bool) $this->option('once');
        $page = max(1, (int) $this->option('page'));
        $sleepMs = max(50, (int) $this->option('sleep'));
        $pauseMs = max(50, (int) $this->option('pause'));
        $pauseKey = (string) config('rag.summary.pause_key', 'chat.active');

        do {
            if ($this->chatActive($pauseKey)) {
                usleep($pauseMs * 1000);
                if ($once) {
                    return Command::SUCCESS;
                }
                continue;
            }

            $ids = DB::table('news_summary_queue')
                ->where('tries', '<', 3)
                ->orderBy('enqueued_at')
                ->limit($page)
                ->pluck('news_id')
                ->all();

            if (!$ids) {
                if ($once) {
                    return Command::SUCCESS;
                }
                usleep($sleepMs * 1000);
                continue;
            }

            foreach ($ids as $nid) {
                if ($this->chatActive($pauseKey)) {
                    usleep($pauseMs * 1000);
                    if ($once) {
                        return Command::SUCCESS;
                    }
                    continue;
                }

                try {
                    $row = DB::table('news')
                        ->select('id', 'content')
                        ->where('id', $nid)
                        ->first();

                    if (!$row) {
                        DB::table('news_summary_queue')
                            ->where('news_id', $nid)
                            ->delete();
                        continue;
                    }

                    $content = trim((string) ($row->content ?? ''));
                    if ($content === '') {
                        NewsSummary::where('news_id', $nid)->update([
                            'status' => 'skipped',
                            'error' => 'Empty content',
                            'updated_at' => now(),
                        ]);
                        DB::table('news_summary_queue')
                            ->where('news_id', $nid)
                            ->delete();
                        $this->warn("[skip] {$nid} : empty content");
                        continue;
                    }

                    $result = $summarizer->summarize($content);
                    $summary = trim((string) ($result['summary'] ?? ''));
                    if ($summary === '') {
                        throw new \RuntimeException('Summary empty');
                    }

                    NewsSummary::updateOrCreate(
                        ['news_id' => $nid],
                        [
                            'summary' => $summary,
                            'model' => $result['model'] ?? null,
                            'status' => 'ready',
                            'error' => null,
                            'summarized_at' => now(),
                        ]
                    );

                    DB::table('news_summary_queue')
                        ->where('news_id', $nid)
                        ->delete();

                    $this->info("[ok] {$nid}");
                } catch (\Throwable $e) {
                    NewsSummary::where('news_id', $nid)->update([
                        'status' => 'error',
                        'error' => mb_substr($e->getMessage(), 0, 1000),
                        'updated_at' => now(),
                    ]);

                    DB::table('news_summary_queue')
                        ->where('news_id', $nid)
                        ->increment('tries');

                    $this->error("[err] {$nid} : " . $e->getMessage());
                }
            }
        } while (!$once);

        return Command::SUCCESS;
    }

    private function chatActive(string $pauseKey): bool
    {
        if ($pauseKey === '') {
            return false;
        }

        return (bool) Cache::get($pauseKey, false);
    }
}
