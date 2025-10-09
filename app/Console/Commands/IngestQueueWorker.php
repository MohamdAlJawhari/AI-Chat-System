<?php 
// app/Console/Commands/IngestQueueWorker.php
namespace App\Console\Commands;

use App\Services\OllamaEmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IngestQueueWorker extends Command
{
    protected $signature = 'ingest:queue {--once} {--page=50} {--batch=64}';
    protected $description = 'Drain news_item_ingest_queue: chunk + embed + fill news_item_chunks';

    public function handle(OllamaEmbeddingService $emb)
    {
        $once = (bool)$this->option('once');
        $PAGE = (int)$this->option('page');
        $BATCH = (int)$this->option('batch');

        $chunkText = function (string $txt): array {
            $txt = trim($txt ?? '');
            if ($txt === '') return [];
            $TARGET = 2800; $OVERLAP = 300;
            $paras = array_values(array_filter(array_map('trim', preg_split("/\n\s*\n/u", $txt))));
            $chunks = []; $buf = '';
            foreach ($paras as $p) {
                $cand = $buf ? trim($buf."\n\n".$p) : $p;
                if (mb_strlen($cand) <= $TARGET) { $buf = $cand; }
                else {
                    if ($buf) $chunks[] = $buf;
                    while (mb_strlen($p) > $TARGET) {
                        $chunks[] = mb_substr($p, 0, $TARGET);
                        $p = mb_substr($p, $TARGET - $OVERLAP);
                    }
                    $buf = $p;
                }
            }
            if ($buf) $chunks[] = $buf;
            $withOverlap = [];
            foreach ($chunks as $i => $c) {
                if ($i === 0) $withOverlap[] = $c;
                else $withOverlap[] = mb_substr($chunks[$i-1], -$OVERLAP) . $c;
            }
            return $withOverlap;
        };

        do {
            $ids = DB::table('news_item_ingest_queue')
                ->orderBy('enqueued_at')->limit($PAGE)
                ->pluck('news_item_id')->all();

            if (!$ids) {
                if ($once) return Command::SUCCESS;
                usleep(500_000); // 0.5s
                continue;
            }

            foreach ($ids as $nid) {
                try {
                    $row = DB::table('news_items')->selectRaw("
                        language, category, country, city, date_sent,
                        coalesce(title,'')||E'\\n\\n'||coalesce(introduction,'')||E'\\n\\n'||coalesce(body,'') AS full
                    ")->where('id',$nid)->first();

                    if (!$row) {
                        DB::table('news_item_ingest_queue')->where('news_item_id',$nid)->delete();
                        continue;
                    }

                    DB::transaction(function () use ($nid) {
                        DB::table('news_item_chunks')->where('news_item_id',$nid)->delete();
                    });

                    $pieces = $chunkText($row->full ?? '');
                    $inserted = [];
                    DB::beginTransaction();
                    foreach ($pieces as $i => $content) {
                        $cid = DB::table('news_item_chunks')->insertGetId([
                            'news_item_id'=>$nid,'chunk_no'=>$i,'content'=>$content,
                            'token_count'=>mb_strlen($content) ?: null,
                            'language'=>$row->language,'category'=>$row->category,
                            'country'=>$row->country,'city'=>$row->city,'date_sent'=>$row->date_sent,
                        ], 'chunk_id');
                        $inserted[] = [$cid, $content];
                    }
                    DB::commit();

                    for ($i=0; $i<count($inserted); $i += $BATCH) {
                        $batch = array_slice($inserted, $i, $BATCH);
                        $vecs = $emb->embed(array_map(fn($x)=>$x[1], $batch)); // list<vec>
                        // build updates
                        DB::beginTransaction();
                        foreach ($batch as $j => [$cid, $_text]) {
                            $vec = $vecs[$j];
                            $vecStr = 'ARRAY['.implode(',', array_map(fn($x)=>sprintf('%.7f',$x), $vec)).']::vector(1024)';
                            DB::update("UPDATE news_item_chunks SET embedding = {$vecStr} WHERE chunk_id = ?", [$cid]);
                        }
                        DB::commit();
                    }

                    DB::table('news_item_ingest_queue')->where('news_item_id',$nid)->delete();
                    $this->info("[ok] {$nid} : ".count($inserted)." chunks");
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $this->error("[err] {$nid} : ".$e->getMessage());
                    DB::table('news_item_ingest_queue')
                        ->where('news_item_id',$nid)->increment('tries');
                }
            }
        } while (!$once);

        return Command::SUCCESS;
    }
}
