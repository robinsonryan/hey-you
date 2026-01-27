<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RobinsonRyan\HeyYou\Models\Party;

/**
 * @extends Factory<Party>
 */
final class PartyFactory extends Factory
{
    protected $model = Party::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partyable_type' => 'App\\Models\\User',
            'partyable_id' => $this->faker->unique()->numberBetween(1, 100000),
            'display_name_cached' => $this->faker->name(),
            'metadata' => null,
        ];
    }

    /**
     * Configure the party as a person.
     */
    public function person(): static
    {
        return $this->state(fn (array $attributes) => [
            'partyable_type' => 'App\\Models\\User',
            'display_name_cached' => $this->faker->name(),
        ]);
    }

    /**
     * Configure the party as an organization.
     */
    public function organization(): static
    {
        return $this->state(fn (array $attributes) => [
            'partyable_type' => 'App\\Models\\Company',
            'display_name_cached' => $this->faker->company(),
        ]);
    }

    /**
     * Configure the party as a location.
     */
    public function location(): static
    {
        return $this->state(fn (array $attributes) => [
            'partyable_type' => 'App\\Models\\Location',
            'display_name_cached' => $this->faker->city().' Office',
        ]);
    }

    /**
     * Configure the party with specific metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => $metadata,
        ]);
    }

    /**
     * Configure the party with timezone metadata.
     */
    public function withTimezone(string $timezone = 'America/New_York'): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'timezone' => $timezone,
            ]),
        ]);
    }
}
