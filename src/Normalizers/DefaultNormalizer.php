<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Normalizers;

use RobinsonRyan\HeyYou\Contracts\ChannelNormalizer;

/**
 * Default normalizer for channels without specific normalization rules.
 */
final class DefaultNormalizer implements ChannelNormalizer
{
    public function normalize(string $raw): string
    {
        return trim($raw);
    }

    public function validate(string $raw): bool
    {
        return trim($raw) !== '';
    }

    public function formatForDisplay(string $normalized): string
    {
        return $normalized;
    }
}
