<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Support;

use RobinsonRyan\HeyYou\Contracts\Registries\RegistryItem;

final class GenericRegistryItem implements RegistryItem
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly string $slug,
        private readonly string $name,
        private readonly array $metadata = [],
    ) {}

    public function slug(): string
    {
        return $this->slug;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }
}
