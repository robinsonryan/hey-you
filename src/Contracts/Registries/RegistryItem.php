<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Contracts\Registries;

interface RegistryItem
{
    public function slug(): string;

    public function name(): string;

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array;
}
