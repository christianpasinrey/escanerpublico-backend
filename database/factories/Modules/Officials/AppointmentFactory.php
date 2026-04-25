<?php

namespace Database\Factories\Modules\Officials;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Legislation\Models\BoeItem;
use Modules\Officials\Models\Appointment;
use Modules\Officials\Models\PublicOfficial;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        return [
            'public_official_id' => PublicOfficial::factory(),
            'boe_item_id' => BoeItem::factory(),
            'organization_id' => null,
            'event_type' => $this->faker->randomElement(['appointment', 'cessation', 'posession']),
            'cargo' => $this->faker->randomElement([
                'Director General de Tributos',
                'Subsecretario de Hacienda',
                'Vocal del Consejo Asesor',
                'Director del Gabinete',
            ]),
            'effective_date' => $this->faker->dateTimeBetween('-3 years'),
        ];
    }
}
