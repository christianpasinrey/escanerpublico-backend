<?php

namespace Modules\Borme\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Borme\Models\BormePdf;
use Modules\Borme\Services\BormeApiClient;
use Modules\Borme\Services\Parsers\SectionOneParser;
use Modules\Borme\Services\PersistBormeEntry;

class ProcessBormePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    public function __construct(public readonly int $bormePdfId) {}

    /**
     * Download → parse → persist → unlink, in that order. Single PDF lifetime is
     * scoped to this job so disk usage never accumulates beyond one file at a
     * time, matching the "1-a-1, borrar antes del siguiente" requirement.
     */
    public function handle(
        BormeApiClient $client,
        SectionOneParser $parser,
        PersistBormeEntry $persister,
    ): void {
        $pdf = BormePdf::findOrFail($this->bormePdfId);

        if ($pdf->status === 'parsed') {
            return; // idempotent re-run guard
        }

        $disk = Storage::disk('local');
        $relativePath = "borme/tmp/{$pdf->cve}.pdf";
        $absolutePath = $disk->path($relativePath);

        try {
            $bytes = $client->downloadPdf($pdf->source_url);
            $disk->put($relativePath, $bytes);

            $sha256 = hash('sha256', $bytes);
            $pdf->update([
                'pdf_sha256' => $sha256,
                'downloaded_at' => now(),
                'status' => 'downloaded',
            ]);

            // Section A only for now — other sections (B mostly notices) are
            // not yet on the parser radar; mark and skip.
            if ($pdf->section !== 'A') {
                $pdf->update(['status' => 'skipped']);

                return;
            }

            $entries = $parser->parseFile($absolutePath);
            foreach ($entries as $entry) {
                $persister->persist($pdf, $entry);
            }

            $pdf->update([
                'parser_version' => SectionOneParser::PARSER_VERSION,
                'parsed_at' => now(),
                'status' => 'parsed',
            ]);
        } catch (\Throwable $e) {
            Log::error('BORME PDF processing failed', [
                'cve' => $pdf->cve,
                'error' => $e->getMessage(),
            ]);
            $pdf->update([
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 5000),
            ]);
            throw $e;
        } finally {
            // Always remove the local copy — we keep the URL + sha256, which is
            // enough to refetch on demand and avoids storage drift.
            if ($disk->exists($relativePath)) {
                $disk->delete($relativePath);
            }
        }
    }
}
