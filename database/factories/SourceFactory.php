<?php

namespace Database\Factories;

use App\Enums\NewsLanguage;
use App\Enums\SourceKey;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => $this->faker->randomElement(SourceKey::cases()),
            'name' => $this->faker->company(),
            'language' => $this->faker->randomElement(NewsLanguage::cases()),
            'base_url' => $this->faker->url(),
            'is_active' => true,
            'last_scraped_external_url' => null,
            'last_scraped_at' => null,
        ];
    }
}
