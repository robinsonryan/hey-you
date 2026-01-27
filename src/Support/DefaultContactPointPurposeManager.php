<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use RobinsonRyan\HeyYou\Contracts\ContactPointPurposeManager;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\ContactPointPurpose;

final class DefaultContactPointPurposeManager implements ContactPointPurposeManager
{
    /**
     * @param  array{priority?: int, is_preferred?: bool}  $attributes
     */
    public function attach(ContactPoint $contactPoint, string $purpose, array $attributes = []): void
    {
        $contactPoint->purposes()->create([
            'purpose' => $purpose,
            'priority' => $attributes['priority'] ?? 0,
            'is_preferred' => $attributes['is_preferred'] ?? false,
        ]);
    }

    public function detach(ContactPoint $contactPoint, string $purpose): void
    {
        $contactPoint->purposes()->where('purpose', $purpose)->delete();
    }

    /**
     * @return Collection<int, ContactPointPurpose>
     */
    public function purposes(ContactPoint $contactPoint): Collection
    {
        return $contactPoint->purposes;
    }

    /**
     * @return Builder<ContactPoint>
     */
    public function forPurpose(string $purpose): Builder
    {
        return ContactPoint::whereHas('purposes', function (Builder $query) use ($purpose) {
            $query->where('purpose', $purpose);
        });
    }
}
