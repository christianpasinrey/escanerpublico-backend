<?php

namespace Tests\Feature\Officials;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Organization;
use Modules\Legislation\Models\BoeItem;
use Modules\Officials\Models\Appointment;
use Modules\Officials\Models\PublicOfficial;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_official_unique_normalized_name(): void
    {
        PublicOfficial::factory()->create(['normalized_name' => 'juan perez']);
        $this->expectException(UniqueConstraintViolationException::class);
        PublicOfficial::factory()->create(['normalized_name' => 'juan perez']);
    }

    public function test_appointment_belongs_to_official_and_boe_item(): void
    {
        $official = PublicOfficial::factory()->create();
        $boeItem = BoeItem::factory()->create();
        $org = Organization::factory()->create();

        $appt = Appointment::factory()
            ->for($official, 'publicOfficial')
            ->for($boeItem, 'boeItem')
            ->for($org, 'organization')
            ->create();

        $this->assertEquals($official->id, $appt->publicOfficial->id);
        $this->assertEquals($boeItem->id, $appt->boeItem->id);
        $this->assertEquals($org->id, $appt->organization->id);
    }

    public function test_appointment_unique_per_official_item_type(): void
    {
        $official = PublicOfficial::factory()->create();
        $boeItem = BoeItem::factory()->create();

        Appointment::factory()->for($official, 'publicOfficial')->for($boeItem, 'boeItem')->create([
            'event_type' => 'appointment',
        ]);
        $this->expectException(UniqueConstraintViolationException::class);
        Appointment::factory()->for($official, 'publicOfficial')->for($boeItem, 'boeItem')->create([
            'event_type' => 'appointment',
        ]);
    }

    public function test_official_can_have_multiple_appointments(): void
    {
        $official = PublicOfficial::factory()->create();
        Appointment::factory()->for($official, 'publicOfficial')->count(3)->create();

        $this->assertCount(3, $official->refresh()->appointments);
    }

    public function test_deleting_official_cascades_appointments(): void
    {
        $official = PublicOfficial::factory()->create();
        Appointment::factory()->for($official, 'publicOfficial')->count(2)->create();

        $official->delete();

        $this->assertSame(0, Appointment::count());
    }
}
