<?php

namespace Database\Factories\Modules\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contracts\Models\ParseError;
use Modules\Contracts\Models\ReprocessAtomRun;

/**
 * @extends Factory<ParseError>
 */
class ParseErrorFactory extends Factory
{
    protected $model = ParseError::class;

    public function definition(): array
    {
        return [
            'reprocess_atom_run_id' => ReprocessAtomRun::factory(),
            'atom_path' => 'atoms/' . $this->faker->numerify('########') . '.atom',
            'entry_external_id' => $this->faker->url(),
            'error_code' => $this->faker->randomElement(['PARSE_FAIL', 'XSD_INVALID', 'MISSING_FIELD']),
            'error_message' => $this->faker->sentence(),
            'raw_fragment' => $this->faker->text(200),
        ];
    }
}
