<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Registries;

use RobinsonRyan\HeyYou\Contracts\ChannelNormalizer;
use RobinsonRyan\HeyYou\Contracts\Registries\NormalizerRegistry;
use RobinsonRyan\HeyYou\Normalizers\DefaultNormalizer;
use RobinsonRyan\HeyYou\Normalizers\EmailNormalizer;
use RobinsonRyan\HeyYou\Normalizers\PhoneNormalizer;

final class DefaultNormalizerRegistry implements NormalizerRegistry
{
    /**
     * @var array<string, ChannelNormalizer>
     */
    private array $normalizers = [];

    private ChannelNormalizer $default;

    public function __construct()
    {
        $this->default = new DefaultNormalizer;

        // Register default normalizers
        $this->register('email', new EmailNormalizer);
        $this->register('phone', new PhoneNormalizer);
        $this->register('sms', new PhoneNormalizer);
    }

    public function for(string $channel): ChannelNormalizer
    {
        return $this->normalizers[$channel] ?? $this->default;
    }

    public function register(string $channel, ChannelNormalizer $normalizer): void
    {
        $this->normalizers[$channel] = $normalizer;
    }
}
