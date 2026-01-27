<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Registries;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use RobinsonRyan\HeyYou\Contracts\Registries\ChannelRegistry;
use RobinsonRyan\HeyYou\Contracts\Registries\RegistryItem;
use RobinsonRyan\HeyYou\Support\GenericRegistryItem;

final class ConfigChannelRegistry implements ChannelRegistry
{
    /**
     * @var array<string, array{name: string, category?: string}>
     */
    private array $channels;

    public function __construct()
    {
        /** @var array<string, array{name: string, category?: string}> $channels */
        $channels = config('heyyou.channels', []);
        $this->channels = $channels;
    }

    public function exists(string $slug): bool
    {
        return isset($this->channels[$slug]);
    }

    public function get(string $slug): RegistryItem
    {
        if (! $this->exists($slug)) {
            throw new InvalidArgumentException("Channel '{$slug}' does not exist.");
        }

        $config = $this->channels[$slug];

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
        return collect($this->channels)
            ->keys()
            ->map(fn (string $slug): RegistryItem => $this->get($slug))
            ->values();
    }

    /**
     * @return Collection<int, RegistryItem>
     */
    public function forCategory(string $category): Collection
    {
        return collect($this->channels)
            ->filter(fn (array $config): bool => ($config['category'] ?? null) === $category)
            ->keys()
            ->map(fn (string $slug): RegistryItem => $this->get($slug))
            ->values();
    }
}
