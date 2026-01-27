<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Contracts\Registries;

use Illuminate\Support\Collection;

interface ChannelRegistry
{
    public function exists(string $slug): bool;

    public function get(string $slug): RegistryItem;

    /**
     * @return Collection<int, RegistryItem>
     */
    public function all(): Collection;

    /**
     * @return Collection<int, RegistryItem>
     */
    public function forCategory(string $category): Collection;
}
