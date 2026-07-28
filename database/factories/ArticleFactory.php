<?php

namespace Database\Factories;

use App\Enums\ArticleStatus;
use App\Enums\NewsLanguage;
use App\Models\Article;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_id' => Source::factory(),
            'language' => $this->faker->randomElement(NewsLanguage::cases()),
            'status' => ArticleStatus::Pending,
            'external_url' => $this->faker->unique()->url(),
            'image_url' => $this->faker->imageUrl(),
            'original_title' => $this->faker->sentence(),
            'original_body' => $this->faker->paragraphs(4, true),
            'original_published_at' => $this->faker->dateTimeBetween('-1 day'),
            'slug' => null,
            'rephrased_title' => null,
            'rephrased_summary' => null,
            'rephrased_paragraphs' => null,
            'rephrased_at' => null,
            'failure_reason' => null,
        ];
    }

    /**
     * Indicate that the article has been rephrased and published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ArticleStatus::Published,
            'slug' => str($attributes['original_title'])->slug()->append('-', (string) $this->faker->unique()->numberBetween(1, 999999)),
            'rephrased_title' => $this->faker->sentence(),
            'rephrased_summary' => $this->faker->paragraph(),
            'rephrased_paragraphs' => $this->faker->paragraphs(4),
            'rephrased_at' => $this->faker->dateTimeBetween('-1 day'),
        ]);
    }
}
