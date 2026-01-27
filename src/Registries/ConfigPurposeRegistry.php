<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Registries;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use RobinsonRyan\HeyYou\Contracts\Registries\PurposeRegistry;
use RobinsonRyan\HeyYou\Contracts\Registries\RegistryItem;
use RobinsonRyan\HeyYou\Support\GenericRegistryItem;

final class ConfigPurposeRegistry implements PurposeRegistry
{
    /**
     * @var array<string, array{name: string, parent?: string|null}>
     */
    private array $purposes;

    public function __construct()
    {
        /** @var array<string, array{name: string, parent?: string|null}> $purposes */
        $purposes = config('heyyou.purposes', []);
        $this->purposes = $purposes;
    }

    public function exists(string $slug): bool
    {
        return isset($this->purposes[$slug]);
    }

    public function get(string $slug): RegistryItem
    {
        if (! $this->exists($slug)) {
            throw new InvalidArgumentException("Purpose '{$slug}' does not exist.");
        }

        $config = $this->purposes[$slug];

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
        return collect($this->purposes)
            ->keys()
            ->map(fn (string $slug): RegistryItem => $this->get($slug))
            ->values();
    }

    public function parent(string $slug): ?string
    {
        if (! $this->exists($slug)) {
            throw new InvalidArgumentException("Purpose '{$slug}' does not exist.");
        }

        return $this->purposes[$slug]['parent'] ?? null;
    }

    /**
     * @return Collection<int, RegistryItem>
     */
    public function children(string $slug): Collection
    {
        if (! $this->exists($slug)) {
            throw new InvalidArgumentException("Purpose '{$slug}' does not exist.");
        }

        return collect($this->purposes)
            ->filter(fn (array $config): bool => ($config['parent'] ?? null) === $slug)
            ->keys()
            ->map(fn (string $childSlug): RegistryItem => $this->get($childSlug))
            ->values();
    }
}
