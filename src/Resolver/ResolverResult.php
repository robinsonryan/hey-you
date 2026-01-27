<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Resolver;

use Illuminate\Support\Collection;

final class ResolverResult
{
    /**
     * @param  Collection<int, ResolverMatch>  $matches
     */
    public function __construct(
        public readonly Collection $matches,
        public readonly ResolverExplanation $explanation,
    ) {}

    public function best(): ?ResolverMatch
    {
        return $this->matches->first();
    }

    public function isEmpty(): bool
    {
        return $this->matches->isEmpty();
    }
}
