<?php

namespace Tests\Feature\Borme;

use Modules\Borme\DTOs\BormeEntryDTO;
use Modules\Borme\Services\Extractors\SmalotPdfTextExtractor;
use Modules\Borme\Services\Parsers\SectionOneParser;
use Modules\Borme\Services\Support\PageSplitter;
use Modules\Borme\Services\Support\TextNormalizer;
use Tests\TestCase;

/**
 * Snapshot tests run the full SectionOneParser pipeline against a real BORME-A
 * fixture (Madrid, 24/04/2026, 718 entries). Expectations are JSON files in
 * tests/Fixtures/Borme/expected/ and were generated from the parser itself.
 *
 * Manual ground-truth verification (compared field-by-field against the PDF
 * raw text on 2026-04-27): entries 199800, 199801, 200428 — all correct.
 * The remaining 3 fixtures + aggregate are regression baselines anchored on
 * those three.
 *
 * `raw_text` is excluded from per-entry comparisons because it depends on the
 * exact PDF→text extraction (smalot/pdfparser version, locale).
 */
class SectionOneParserSnapshotTest extends TestCase
{
    private const FIXTURE_PDF = 'tests/Fixtures/Borme/pdfs/BORME-A-2026-78-28.pdf';

    private const EXPECTED_DIR = 'tests/Fixtures/Borme/expected';

    /** @var BormeEntryDTO[] */
    private static array $cachedEntries;

    private function entries(): array
    {
        if (! isset(self::$cachedEntries)) {
            $parser = $this->app->make(SectionOneParser::class);
            self::$cachedEntries = $parser->parseFile(base_path(self::FIXTURE_PDF));
        }

        return self::$cachedEntries;
    }

    private function entry(int $number): BormeEntryDTO
    {
        foreach ($this->entries() as $entry) {
            if ($entry->entryNumber === $number) {
                return $entry;
            }
        }

        $this->fail("Entry {$number} not found in fixture.");
    }

    public function test_total_entries_count_is_stable(): void
    {
        $this->assertCount(718, $this->entries());
    }

    /**
     * Invariant: every block found by PageSplitter must produce one parsed entry.
     * Catches silent drops where headerExtractor->extract() returns null and the
     * entry vanishes — without this assert the count test would still pass on
     * a snapshot of 718 because 718 is whatever the parser produced.
     */
    public function test_no_entry_dropped_silently(): void
    {
        $extractor = $this->app->make(SmalotPdfTextExtractor::class);
        $normalizer = $this->app->make(TextNormalizer::class);
        $splitter = $this->app->make(PageSplitter::class);

        $raw = $extractor->extract(base_path(self::FIXTURE_PDF));
        $blocks = $splitter->split($normalizer->normalize($raw));

        $this->assertCount(
            count($blocks),
            $this->entries(),
            sprintf(
                'PageSplitter found %d blocks but parser returned %d entries — silent drop.',
                count($blocks),
                count($this->entries())
            )
        );
    }

    public function test_aggregate_snapshot_is_stable(): void
    {
        $entries = $this->entries();
        $actTypes = $legalForms = $officerActions = $officerRoles = [];
        $officerKinds = ['person' => 0, 'company' => 0];

        foreach ($entries as $e) {
            foreach ($e->actTypes as $t) {
                $actTypes[$t->value] = ($actTypes[$t->value] ?? 0) + 1;
            }
            if ($e->legalForm) {
                $legalForms[$e->legalForm->value] = ($legalForms[$e->legalForm->value] ?? 0) + 1;
            }
            foreach ($e->officers as $o) {
                $officerKinds[$o->kind]++;
                $officerActions[$o->action->value] = ($officerActions[$o->action->value] ?? 0) + 1;
                $officerRoles[$o->role->value] = ($officerRoles[$o->role->value] ?? 0) + 1;
            }
        }
        ksort($actTypes);
        ksort($legalForms);
        ksort($officerActions);
        ksort($officerRoles);

        $aggregate = [
            'total_entries' => count($entries),
            'act_types' => $actTypes,
            'legal_forms' => $legalForms,
            'officer_kinds' => $officerKinds,
            'officer_actions' => $officerActions,
            'officer_roles' => $officerRoles,
        ];

        $expected = json_decode(file_get_contents(base_path(self::EXPECTED_DIR.'/aggregate.json')), true);
        $this->assertSame($expected, $aggregate);
    }

    public function test_no_entry_lacks_registry_data(): void
    {
        foreach ($this->entries() as $e) {
            $this->assertNotNull($e->registryLetter, "Entry {$e->entryNumber} lacks registry letter");
            $this->assertNotNull($e->registrySheet, "Entry {$e->entryNumber} lacks registry sheet");
        }
    }

    public function test_no_entry_lacks_act_types(): void
    {
        foreach ($this->entries() as $e) {
            $this->assertNotEmpty($e->actTypes, "Entry {$e->entryNumber} lacks act types");
        }
    }

    public function test_no_entry_lacks_legal_form(): void
    {
        foreach ($this->entries() as $e) {
            $this->assertNotNull($e->legalForm, "Entry {$e->entryNumber} ({$e->companyNameRaw}) lacks legal form");
        }
    }

    /**
     * @dataProvider entrySnapshotProvider
     */
    public function test_entry_snapshot(int $entryNumber): void
    {
        $entry = $this->entry($entryNumber);
        $expected = json_decode(
            file_get_contents(base_path(self::EXPECTED_DIR."/entry_{$entryNumber}.json")),
            true
        );

        $actual = $entry->toArray();

        // raw_text is PDF-extraction dependent — compare structured fields only.
        unset($actual['raw_text'], $expected['raw_text']);

        $this->assertSame($expected, $actual);
    }

    public static function entrySnapshotProvider(): array
    {
        return [
            'constitution + joint admins' => [199800],
            'multi-act unipersonalidad+disolución+extinción' => [199801],
            'fusión por absorción' => [199942],
            'revocaciones múltiples roles' => [199948],
            'transformación de sociedad' => [199985],
            'constitución + company-officer + representante' => [200428],
        ];
    }
}
