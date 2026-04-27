<?php

namespace Modules\Borme\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Borme\Models\BormeIngestRun;
use Modules\Borme\Models\BormePdf;
use Modules\Borme\Services\BormeApiClient;
use Modules\Borme\Services\SumarioParser;

class FetchBormeSumarioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public function __construct(
        public readonly string $yyyymmdd,
        public readonly int $ingestRunId,
    ) {}

    /**
     * Fetch the daily JSON summary, materialise BormePdf rows, and chain
     * ProcessBormePdfJob instances sequentially (one PDF in flight at a time).
     */
    public function handle(BormeApiClient $client, SumarioParser $parser): void
    {
        $sumario = $client->getDailySummary($this->yyyymmdd);
        if ($sumario === null) {
            Log::info('BORME has no publication for date', ['date' => $this->yyyymmdd]);

            return;
        }

        $rows = $parser->flattenPdfs($sumario);

        $pdfIds = [];
        foreach ($rows as $row) {
            $pdf = BormePdf::updateOrCreate(
                ['cve' => $row['cve']],
                [
                    'borme_ingest_run_id' => $this->ingestRunId,
                    'date' => $row['date'],
                    'bulletin_no' => $row['bulletin_no'],
                    'section' => $row['section'],
                    'province_ine' => $row['province_ine'],
                    'province_name' => $row['province_name'],
                    'source_url' => $row['source_url'],
                    'status' => 'pending',
                ]
            );
            $pdfIds[] = $pdf->id;
        }

        BormeIngestRun::where('id', $this->ingestRunId)
            ->increment('total_pdfs', count($pdfIds));

        // Independent dispatch (not Bus::chain) — sequentiality is enforced by
        // running queue 'borme' with a single worker process (Supervisor
        // numprocs=1 / Horizon processes=1). Each job is then resilient to
        // redis restarts because no parent chain holds shared state.
        foreach ($pdfIds as $id) {
            ProcessBormePdfJob::dispatch($id)->onQueue('borme');
        }
    }
}
