<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Contracts\Registries;

use Illuminate\Support\Collection;

interface PurposeRegistry
{
    public function exists(string $slug): bool;

    public function get(string $slug): RegistryItem;

    /**
     * @return Collection<int, RegistryItem>
     */
    public function all(): Collection;

    /**
     * Get the parent purpose slug, if any.
     */
    public function parent(string $slug): ?string;

    /**
     * Get all children of a purpose.
     *
     * @return Collection<int, RegistryItem>
     */
    public function children(string $slug): Collection;
}
