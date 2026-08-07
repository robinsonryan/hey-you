<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Resolver;

use Illuminate\Support\Collection;

final readonly class ResolverResult
{
    /**
     * @param  Collection<int, ResolverMatch>  $matches
     */
    public function __construct(
        public Collection $matches,
        public ResolverExplanation $explanation,
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
