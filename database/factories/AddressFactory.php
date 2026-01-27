<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Database\Factories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use RobinsonRyan\HeyYou\Models\Address;
use RobinsonRyan\HeyYou\Models\Party;

/**
 * @extends Factory<Address>
 */
final class AddressFactory extends Factory
{
    protected $model = Address::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'purpose' => 'general',
            'is_primary' => false,
            'label' => null,
            'line1' => $this->faker->streetAddress(),
            'line2' => null,
            'city' => $this->faker->city(),
            'region' => $this->faker->stateAbbr(),
            'postal_code' => $this->faker->postcode(),
            'country_code' => 'US',
            'geocode' => null,
            'timezone' => null,
            'validation_status' => Address::STATUS_UNVERIFIED,
            'formatted_cached' => null,
            'valid_from' => null,
            'valid_to' => null,
            'metadata' => null,
        ];
    }

    /**
     * Mark as primary.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    /**
     * Configure for billing purpose.
     */
    public function billing(): static
    {
        return $this->state(fn (array $attributes) => [
            'purpose' => 'billing',
        ]);
    }

    /**
     * Configure for shipping purpose.
     */
    public function shipping(): static
    {
        return $this->state(fn (array $attributes) => [
            'purpose' => 'shipping',
        ]);
    }

    /**
     * Configure for receiving purpose.
     */
    public function receiving(): static
    {
        return $this->state(fn (array $attributes) => [
            'purpose' => 'receiving',
        ]);
    }

    /**
     * Set a specific purpose.
     */
    public function forPurpose(string $purpose): static
    {
        return $this->state(fn (array $attributes) => [
            'purpose' => $purpose,
        ]);
    }

    /**
     * Mark as verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'validation_status' => Address::STATUS_VERIFIED,
        ]);
    }

    /**
     * Mark as invalid.
     */
    public function invalid(): static
    {
        return $this->state(fn (array $attributes) => [
            'validation_status' => Address::STATUS_INVALID,
        ]);
    }

    /**
     * Set geocode coordinates.
     */
    public function withGeocode(float $lat, float $lng): static
    {
        return $this->state(fn (array $attributes) => [
            'geocode' => ['lat' => $lat, 'lng' => $lng],
        ]);
    }

    /**
     * Set timezone.
     */
    public function withTimezone(string $timezone): static
    {
        return $this->state(fn (array $attributes) => [
            'timezone' => $timezone,
        ]);
    }

    /**
     * Set validity period.
     */
    public function validBetween(DateTimeInterface $from, ?DateTimeInterface $to = null): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => $from,
            'valid_to' => $to,
        ]);
    }

    /**
     * Set expiration date.
     */
    public function expiresAt(DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_to' => $date,
        ]);
    }

    /**
     * Set a label.
     */
    public function labeled(string $label): static
    {
        return $this->state(fn (array $attributes) => [
            'label' => $label,
        ]);
    }

    /**
     * Associate with a specific party.
     */
    public function forParty(Party $party): static
    {
        return $this->state(fn (array $attributes) => [
            'party_id' => $party->id,
        ]);
    }

    /**
     * US address.
     */
    public function us(): static
    {
        return $this->state(fn (array $attributes) => [
            'country_code' => 'US',
            'region' => $this->faker->stateAbbr(),
            'postal_code' => $this->faker->postcode(),
        ]);
    }

    /**
     * UK address.
     */
    public function uk(): static
    {
        return $this->state(fn (array $attributes) => [
            'country_code' => 'GB',
            'region' => $this->faker->county(),
            'postal_code' => $this->faker->postcode(),
        ]);
    }

    /**
     * Canadian address.
     */
    public function canada(): static
    {
        return $this->state(fn (array $attributes) => [
            'country_code' => 'CA',
            'region' => $this->faker->randomElement(['ON', 'BC', 'AB', 'QC', 'NS']),
            'postal_code' => strtoupper($this->faker->bothify('?#? #?#')),
        ]);
    }
}
