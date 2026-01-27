<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Database\Factories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\Party;

/**
 * @extends Factory<ContactPoint>
 */
final class ContactPointFactory extends Factory
{
    protected $model = ContactPoint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'channel' => 'email',
            'value_raw' => $this->faker->unique()->safeEmail(),
            'label' => null,
            'status' => ContactPoint::STATUS_ACTIVE,
            'is_primary' => false,
            'is_verified' => false,
            'verified_at' => null,
            'verification_method' => null,
            'verification_expires_at' => null,
            'metadata' => null,
        ];
    }

    /**
     * Configure as an email contact point.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'email',
            'value_raw' => $this->faker->unique()->safeEmail(),
        ]);
    }

    /**
     * Configure as a phone contact point.
     */
    public function phone(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'phone',
            'value_raw' => '+1'.$this->faker->numerify('##########'),
        ]);
    }

    /**
     * Configure as an SMS contact point.
     */
    public function sms(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'sms',
            'value_raw' => '+1'.$this->faker->numerify('##########'),
        ]);
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
     * Mark as verified.
     */
    public function verified(?string $method = 'code'): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
            'verified_at' => now(),
            'verification_method' => $method,
        ]);
    }

    /**
     * Mark as verified with expiration.
     */
    public function verifiedUntil(DateTimeInterface $expiresAt, string $method = 'code'): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
            'verified_at' => now(),
            'verification_method' => $method,
            'verification_expires_at' => $expiresAt,
        ]);
    }

    /**
     * Mark with specific status.
     */
    public function withStatus(string $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    /**
     * Mark as inactive.
     */
    public function inactive(): static
    {
        return $this->withStatus(ContactPoint::STATUS_INACTIVE);
    }

    /**
     * Mark as bounced.
     */
    public function bounced(): static
    {
        return $this->withStatus(ContactPoint::STATUS_BOUNCED);
    }

    /**
     * Mark as unreachable.
     */
    public function unreachable(): static
    {
        return $this->withStatus(ContactPoint::STATUS_UNREACHABLE);
    }

    /**
     * Mark as blocked.
     */
    public function blocked(): static
    {
        return $this->withStatus(ContactPoint::STATUS_BLOCKED);
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
     * Set specific value.
     */
    public function withValue(string $value): static
    {
        return $this->state(fn (array $attributes) => [
            'value_raw' => $value,
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
}
