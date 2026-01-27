<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Contracts\Registries;

use RobinsonRyan\HeyYou\Contracts\ChannelNormalizer;

interface NormalizerRegistry
{
    /**
     * Get the normalizer for a channel.
     */
    public function for(string $channel): ChannelNormalizer;

    /**
     * Register a normalizer for a channel.
     */
    public function register(string $channel, ChannelNormalizer $normalizer): void;
}
