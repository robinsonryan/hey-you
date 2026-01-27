<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Registries;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use RobinsonRyan\HeyYou\Contracts\Registries\ConsentCategoryRegistry;
use RobinsonRyan\HeyYou\Contracts\Registries\RegistryItem;
use RobinsonRyan\HeyYou\Support\GenericRegistryItem;

final class ConfigConsentCategoryRegistry implements ConsentCategoryRegistry
{
    /**
     * @var array<string, array{name: string}>
     */
    private array $categories;

    public function __construct()
    {
        /** @var array<string, array{name: string}> $categories */
        $categories = config('heyyou.consent_categories', []);
        $this->categories = $categories;
    }

    public function exists(string $slug): bool
    {
        return isset($this->categories[$slug]);
    }

    public function get(string $slug): RegistryItem
    {
        if (! $this->exists($slug)) {
            throw new InvalidArgumentException("Consent category '{$slug}' does not exist.");
        }

        $config = $this->categories[$slug];

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
        return collect($this->categories)
            ->keys()
            ->map(fn (string $slug): RegistryItem => $this->get($slug))
            ->values();
    }
}
