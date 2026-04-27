<?php

namespace Tests\Feature\Borme;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Borme\Jobs\ProcessBormePdfJob;
use Modules\Borme\Models\BormeEntry;
use Modules\Borme\Models\BormePdf;
use Modules\Contracts\Models\Company;
use Tests\TestCase;

/**
 * E2E test for the operational guarantee the user asked for: "1 a 1, eliminar
 * antes del siguiente". Each ProcessBormePdfJob must remove its temp PDF in
 * `finally` whether it succeeds or fails — otherwise disk usage grows
 * unbounded as the queue progresses.
 */
class ProcessBormePdfJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_persists_entries_and_unlinks_pdf_on_success(): void
    {
        $pdfBytes = file_get_contents(base_path('tests/Fixtures/Borme/pdfs/BORME-A-2026-78-28.pdf'));

        Http::fake([
            'example.test/madrid.pdf' => Http::response($pdfBytes, 200, ['Content-Type' => 'application/pdf']),
        ]);

        $pdf = BormePdf::create([
            'date' => '2026-04-24',
            'cve' => 'BORME-A-2026-78-28',
            'section' => 'A',
            'province_ine' => '28',
            'province_name' => 'MADRID',
            'source_url' => 'https://example.test/madrid.pdf',
            'status' => 'pending',
        ]);

        $disk = Storage::disk('local');

        $this->app->call([new ProcessBormePdfJob($pdf->id), 'handle']);

        $pdf->refresh();
        $this->assertSame('parsed', $pdf->status);
        $this->assertSame(64, strlen($pdf->pdf_sha256));
        $this->assertNotNull($pdf->parsed_at);
        $this->assertGreaterThan(0, BormeEntry::count());
        $this->assertGreaterThan(0, Company::count());

        $this->assertFalse(
            $disk->exists("borme/tmp/{$pdf->cve}.pdf"),
            'PDF temp file must be removed in finally — "1 a 1, eliminar antes del siguiente".'
        );
    }

    public function test_job_unlinks_pdf_even_when_parser_throws(): void
    {
        Http::fake([
            'example.test/broken.pdf' => Http::response('not a pdf', 200, ['Content-Type' => 'application/pdf']),
        ]);

        $pdf = BormePdf::create([
            'date' => '2026-04-24',
            'cve' => 'BORME-A-2026-78-99',
            'section' => 'A',
            'province_ine' => '99',
            'province_name' => 'INVALID',
            'source_url' => 'https://example.test/broken.pdf',
            'status' => 'pending',
        ]);

        $disk = Storage::disk('local');

        try {
            $this->app->call([new ProcessBormePdfJob($pdf->id), 'handle']);
            $this->fail('Expected job to throw on broken PDF.');
        } catch (\Throwable $e) {
            // expected
        }

        $pdf->refresh();
        $this->assertSame('failed', $pdf->status);
        $this->assertNotNull($pdf->error_message);

        $this->assertFalse(
            $disk->exists("borme/tmp/{$pdf->cve}.pdf"),
            'PDF temp file must still be removed when parser fails.'
        );
    }

    public function test_job_skips_non_section_a_pdfs(): void
    {
        Http::fake([
            'example.test/section-b.pdf' => Http::response('%PDF-1.4 dummy', 200),
        ]);

        $pdf = BormePdf::create([
            'date' => '2026-04-24',
            'cve' => 'BORME-B-2026-78-99',
            'section' => 'B',
            'province_ine' => '99',
            'province_name' => 'WHATEVER',
            'source_url' => 'https://example.test/section-b.pdf',
            'status' => 'pending',
        ]);

        $disk = Storage::disk('local');

        $this->app->call([new ProcessBormePdfJob($pdf->id), 'handle']);

        $pdf->refresh();
        $this->assertSame('skipped', $pdf->status);
        $this->assertSame(0, BormeEntry::count());
        $this->assertFalse($disk->exists("borme/tmp/{$pdf->cve}.pdf"));
    }
}
