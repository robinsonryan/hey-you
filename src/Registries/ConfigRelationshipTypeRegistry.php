<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Registries;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use RobinsonRyan\HeyYou\Contracts\Registries\RegistryItem;
use RobinsonRyan\HeyYou\Contracts\Registries\RelationshipTypeRegistry;
use RobinsonRyan\HeyYou\Support\GenericRegistryItem;

final class ConfigRelationshipTypeRegistry implements RelationshipTypeRegistry
{
    /**
     * @var array<string, array{name: string, from?: string, to?: string}>
     */
    private array $types;

    public function __construct()
    {
        /** @var array<string, array{name: string, from?: string, to?: string}> $types */
        $types = config('heyyou.relationship_types', []);
        $this->types = $types;
    }

    public function exists(string $slug): bool
    {
        return isset($this->types[$slug]);
    }

    public function get(string $slug): RegistryItem
    {
        if (! $this->exists($slug)) {
            throw new InvalidArgumentException("Relationship type '{$slug}' does not exist.");
        }

        $config = $this->types[$slug];

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
        return collect($this->types)
            ->keys()
            ->map(fn (string $slug): RegistryItem => $this->get($slug))
            ->values();
    }
}
