<?php 
// app/Console/Commands/IngestQueueWorker.php
namespace App\Console\Commands;

use App\Services\OllamaEmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IngestQueueWorker extends Command
{
    protected $signature = 'ingest:queue {--once} {--page=50} {--batch=64}';
    protected $description = 'Drain news_ingest_queue: chunk + embed + fill news_chunks';

    public function handle(OllamaEmbeddingService $emb)
    {
        $once  = (bool) $this->option('once');
        $PAGE  = (int) $this->option('page');
        $BATCH = (int) $this->option('batch');

        // Paragraph-based chunker with overlap
        $chunkText = function (string $txt): array {
            $txt = trim($txt ?? '');
            if ($txt === '') return [];

            $TARGET  = 1800; # 2800 tokens ~= 1800 words 
            $OVERLAP = 200; # 300 tokens ~= 200 words

            // split on blank lines (paragraphs)
            $paras = array_values(array_filter(
                array_map('trim', preg_split("/\n\s*\n/u", $txt))
            ));

            $chunks = [];
            $buf = '';

            foreach ($paras as $p) {
                $cand = $buf ? trim($buf . "\n\n" . $p) : $p;

                if (mb_strlen($cand) <= $TARGET) {
                    $buf = $cand;
                } else {
                    if ($buf) {
                        $chunks[] = $buf;
                    }

                    // hard-split long paragraph
                    while (mb_strlen($p) > $TARGET) {
                        $chunks[] = mb_substr($p, 0, $TARGET);
                        $p = mb_substr($p, $TARGET - $OVERLAP);
                    }
                    $buf = $p;
                }
            }

            if ($buf) {
                $chunks[] = $buf;
            }

            // add overlap from previous chunk
            $withOverlap = [];
            foreach ($chunks as $i => $c) {
                if ($i === 0) {
                    $withOverlap[] = $c;
                } else {
                    $withOverlap[] = mb_substr($chunks[$i - 1], -$OVERLAP) . $c;
                }
            }

            return $withOverlap;
        };

        do {
            // 1) Read pending news IDs from queue
            $ids = DB::table('news_ingest_queue')
                ->where('tries', '<', 3)
                ->orderBy('enqueued_at')
                ->limit($PAGE)
                ->pluck('news_id')
                ->all();

            if (!$ids) {
                if ($once) {
                    return Command::SUCCESS;
                }
                usleep(500_000); // 0.5s
                continue;
            }

            foreach ($ids as $nid) {
                try {
                    // 2) Load the news row (content already merged by trigger)
                    $row = DB::table('news')
                        ->select('id', 'content', 'category', 'country', 'city', 'date_sent')
                        ->where('id', $nid)
                        ->first();

                    if (!$row) {
                        DB::table('news_ingest_queue')
                            ->where('news_id', $nid)
                            ->delete();
                        continue;
                    }

                    // 3) Delete old chunks for this news
                    DB::transaction(function () use ($nid) {
                        DB::table('news_chunks')
                            ->where('news_id', $nid)
                            ->delete();
                    });

                    // 4) Chunk the content
                    $pieces = $chunkText($row->content ?? '');
                    $inserted = [];

                    DB::beginTransaction();
                    foreach ($pieces as $i => $content) {
                        $cid = DB::table('news_chunks')->insertGetId([
                            'news_id'     => $nid,
                            'chunk_no'    => $i,
                            'content'     => $content,
                            'token_count' => mb_strlen($content) ?: null,
                            'category'    => $row->category,
                            'country'     => $row->country,
                            'city'        => $row->city,
                            'date_sent'   => $row->date_sent,
                        ]); // PK column is "id" by default

                        $inserted[] = [$cid, $content];
                    }
                    DB::commit();

                    // 5) Embed chunks in batches
                    for ($i = 0; $i < count($inserted); $i += $BATCH) {
                        $batch = array_slice($inserted, $i, $BATCH);

                        // texts to embed
                        $texts = array_map(fn($x) => $x[1], $batch);
                        $vecs  = $emb->embed($texts); // list<vec>

                        DB::beginTransaction();
                        foreach ($batch as $j => [$cid, $_text]) {
                            $vec = $vecs[$j] ?? null;
                            if (!$vec) {
                                continue;
                            }

                            // build Postgres vector literal (768 dims)
                            $vecStr = 'ARRAY[' . implode(',', array_map(
                                fn($x) => sprintf('%.7f', $x),
                                $vec
                            )) . ']::vector(768)';

                            DB::update(
                                "UPDATE news_chunks SET embedding = {$vecStr} WHERE id = ?",
                                [$cid]
                            );
                        }
                        DB::commit();
                    }

                    // 6) Remove from queue on success
                    DB::table('news_ingest_queue')
                        ->where('news_id', $nid)
                        ->delete();

                    $this->info("[ok] {$nid} : " . count($inserted) . " chunks");
                } catch (\Throwable $e) {
                    // best effort rollback
                    try {
                        DB::rollBack();
                    } catch (\Throwable $ignored) {}

                    $this->error("[err] {$nid} : " . $e->getMessage());

                    DB::table('news_ingest_queue')
                        ->where('news_id', $nid)
                        ->increment('tries');
                }
            }
        } while (!$once);

        return Command::SUCCESS;
    }
}
