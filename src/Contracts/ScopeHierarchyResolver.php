<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Contracts;

use Illuminate\Support\Collection;
use RobinsonRyan\HeyYou\Models\Party;

interface ScopeHierarchyResolver
{
    /**
     * Get ordered list of scope parties to check, from most specific to most general.
     *
     * @return Collection<int, Party>
     */
    public function resolve(Party $startingScope): Collection;
}
