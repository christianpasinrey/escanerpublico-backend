<?php

namespace Tests\Feature\Officials;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Organization;
use Modules\Legislation\Models\BoeItem;
use Modules\Legislation\Models\BoeSummary;
use Modules\Officials\Models\Appointment;
use Modules\Officials\Models\CargoExtractionError;
use Modules\Officials\Models\PublicOfficial;
use Modules\Officials\Services\OfficialIngestor;
use Tests\TestCase;

class OfficialIngestorTest extends TestCase
{
    use RefreshDatabase;

    private OfficialIngestor $ingestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ingestor = $this->app->make(OfficialIngestor::class);
    }

    public function test_extracts_appointment_and_creates_official(): void
    {
        $item = $this->makeBoeItem('Real Decreto 123/2026, de 12 de marzo, por el que se nombra a Don Juan Pérez Director General de Tributos.');

        $result = $this->ingestor->ingestFromBoeItem($item);

        $this->assertSame('extracted', $result['result']);
        $this->assertSame(1, PublicOfficial::count());
        $this->assertSame(1, Appointment::count());

        $official = PublicOfficial::first();
        $this->assertSame('Juan Pérez', $official->full_name);
        $this->assertSame('juan perez', $official->normalized_name);
        $this->assertSame(1, $official->appointments_count);
    }

    public function test_idempotent_same_item_processed_twice_no_duplicate(): void
    {
        $item = $this->makeBoeItem('Por el que se nombra a Don Pedro García Director General.');

        $this->ingestor->ingestFromBoeItem($item);
        $this->ingestor->ingestFromBoeItem($item);

        $this->assertSame(1, PublicOfficial::count());
        $this->assertSame(1, Appointment::count());
    }

    public function test_same_official_two_different_items_two_appointments(): void
    {
        $item1 = $this->makeBoeItem('Por el que se nombra a Don Pedro García Director General.');
        $item2 = $this->makeBoeItem('Por el que se dispone el cese de Don Pedro García como Director General.');

        $this->ingestor->ingestFromBoeItem($item1);
        $this->ingestor->ingestFromBoeItem($item2);

        $this->assertSame(1, PublicOfficial::count());
        $this->assertSame(2, Appointment::count());
        $official = PublicOfficial::first();
        $this->assertSame(2, $official->appointments_count);
    }

    public function test_records_extraction_error_when_pattern_not_matched(): void
    {
        $item = $this->makeBoeItem('Resolución administrativa que no contiene un nombramiento parseable.');

        $result = $this->ingestor->ingestFromBoeItem($item);

        $this->assertSame('pattern_not_matched', $result['result']);
        $this->assertSame(1, CargoExtractionError::count());
    }

    public function test_appointment_inherits_organization_from_boe_item(): void
    {
        $org = Organization::factory()->create();
        $item = $this->makeBoeItem('Por el que se nombra a Don Carlos López Director del Gabinete.');
        $item->update(['organization_id' => $org->id]);

        $this->ingestor->ingestFromBoeItem($item);

        $appt = Appointment::first();
        $this->assertSame($org->id, $appt->organization_id);
    }

    private function makeBoeItem(string $titulo): BoeItem
    {
        $summary = BoeSummary::factory()->create();

        return BoeItem::factory()->for($summary, 'summary')->create([
            'seccion_code' => '2A',
            'titulo' => $titulo,
        ]);
    }
}
