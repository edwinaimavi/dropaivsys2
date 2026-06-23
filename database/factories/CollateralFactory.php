<?php

namespace Database\Factories;

use App\Models\Collateral;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Collateral>
 */
class CollateralFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    protected $model = Collateral::class;

    public function definition(): array
    {
        $loan = Loan::inRandomOrder()->first() ?? Loan::factory()->create();

        $type = $this->faker->randomElement(['vehicle', 'property', 'jewelry', 'electronics']);

        $details = match ($type) {
            'vehicle' => [
                'plate' => strtoupper($this->faker->bothify('???-###')),
                'brand' => $this->faker->randomElement(['Toyota','Hyundai','Kia','Nissan']),
                'model' => $this->faker->word(),
                'year' => $this->faker->numberBetween(2000, 2024),
                'color' => $this->faker->safeColorName(),
            ],
            'property' => [
                'address' => $this->faker->address(),
                'area_m2' => $this->faker->numberBetween(20, 300),
                'registry_number' => strtoupper($this->faker->bothify('PART-####')),
            ],
            'jewelry' => [
                'material' => $this->faker->randomElement(['Oro 18k', 'Plata 950', 'Oro Blanco']),
                'weight_grams' => $this->faker->randomFloat(2, 1, 50),
            ],
            'electronics' => [
                'brand' => $this->faker->company(),
                'model' => $this->faker->word(),
                'condition' => $this->faker->randomElement(['new','used-good','used-fair']),
            ],
        };

        return [
            'loan_id' => $loan->id,
            'type' => $type,
            'description' => $this->faker->sentence(),
            'estimated_value' => $this->faker->randomFloat(2, 100, 20000),
            'details' => $details,
            'photo' => $this->faker->optional()->imageUrl(400, 300, 'objects'),
            'document_file' => null,
            'status' => $this->faker->randomElement(['active','released','confiscated']),

            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
