<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Database\Factories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Models\RoleAssignment;

/**
 * @extends Factory<RoleAssignment>
 */
final class RoleAssignmentFactory extends Factory
{
    protected $model = RoleAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'scope_party_id' => Party::factory(),
            'role' => 'primary_contact',
            'priority' => 0,
            'valid_from' => null,
            'valid_to' => null,
            'metadata' => null,
        ];
    }

    /**
     * Configure as accounts payable contact.
     */
    public function accountsPayable(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'accounts_payable_contact',
        ]);
    }

    /**
     * Configure as accounts receivable contact.
     */
    public function accountsReceivable(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'accounts_receivable_contact',
        ]);
    }

    /**
     * Configure as HR contact.
     */
    public function hr(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'hr_contact',
        ]);
    }

    /**
     * Configure as receiving manager.
     */
    public function receivingManager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'receiving_manager',
        ]);
    }

    /**
     * Configure as shipping contact.
     */
    public function shippingContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'shipping_contact',
        ]);
    }

    /**
     * Configure as sales contact.
     */
    public function salesContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'sales_contact',
        ]);
    }

    /**
     * Configure as support contact.
     */
    public function supportContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'support_contact',
        ]);
    }

    /**
     * Configure as executive contact.
     */
    public function executiveContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'executive_contact',
        ]);
    }

    /**
     * Set specific role.
     */
    public function asRole(string $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
        ]);
    }

    /**
     * Set priority.
     */
    public function withPriority(int $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
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
     * Set starting validity.
     */
    public function validFrom(DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => $date,
        ]);
    }

    /**
     * Set ending validity.
     */
    public function validUntil(DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_to' => $date,
        ]);
    }

    /**
     * Mark as expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => now()->subYear(),
            'valid_to' => now()->subDay(),
        ]);
    }

    /**
     * Mark as future.
     */
    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => now()->addDay(),
            'valid_to' => null,
        ]);
    }

    /**
     * Set the party holding the role.
     */
    public function forParty(Party $party): static
    {
        return $this->state(fn (array $attributes) => [
            'party_id' => $party->id,
        ]);
    }

    /**
     * Set the scope party (organization/location).
     */
    public function scopedTo(Party $scopeParty): static
    {
        return $this->state(fn (array $attributes) => [
            'scope_party_id' => $scopeParty->id,
        ]);
    }

    /**
     * Set metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => $metadata,
        ]);
    }
}
