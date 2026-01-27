<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Contracts\Registries;

use Illuminate\Support\Collection;

interface RoleRegistry
{
    public function exists(string $slug): bool;

    public function get(string $slug): RegistryItem;

    /**
     * @return Collection<int, RegistryItem>
     */
    public function all(): Collection;
}
