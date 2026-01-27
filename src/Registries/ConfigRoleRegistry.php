<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Registries;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use RobinsonRyan\HeyYou\Contracts\Registries\RegistryItem;
use RobinsonRyan\HeyYou\Contracts\Registries\RoleRegistry;
use RobinsonRyan\HeyYou\Support\GenericRegistryItem;

final class ConfigRoleRegistry implements RoleRegistry
{
    /**
     * @var array<string, array{name: string}>
     */
    private array $roles;

    public function __construct()
    {
        /** @var array<string, array{name: string}> $roles */
        $roles = config('heyyou.roles', []);
        $this->roles = $roles;
    }

    public function exists(string $slug): bool
    {
        return isset($this->roles[$slug]);
    }

    public function get(string $slug): RegistryItem
    {
        if (! $this->exists($slug)) {
            throw new InvalidArgumentException("Role '{$slug}' does not exist.");
        }

        $config = $this->roles[$slug];

        return new GenericRegistryItem(
            slug: $slug,
            name: $config['name'],
            metadata: array_diff_key($config, ['name' => true]),
        );
    }

    /**
     * @return Collection<int, RegistryItem>
     */
    public function all(): Collection
    {
        return collect($this->roles)
            ->keys()
            ->map(fn (string $slug): RegistryItem => $this->get($slug))
            ->values();
    }
}
