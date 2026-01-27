<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use RobinsonRyan\HeyYou\Models\ContactPoint;

interface ContactPointPurposeManager
{
    /**
     * Attach a purpose to a contact point.
     *
     * @param  array{priority?: int, is_preferred?: bool}  $attributes
     */
    public function attach(ContactPoint $contactPoint, string $purpose, array $attributes = []): void;

    /**
     * Detach a purpose from a contact point.
     */
    public function detach(ContactPoint $contactPoint, string $purpose): void;

    /**
     * Get all purposes for a contact point.
     *
     * @return Collection<int, \RobinsonRyan\HeyYou\Models\ContactPointPurpose>
     */
    public function purposes(ContactPoint $contactPoint): Collection;

    /**
     * Get a query builder for contact points with a specific purpose.
     *
     * @return Builder<ContactPoint>
     */
    public function forPurpose(string $purpose): Builder;
}
