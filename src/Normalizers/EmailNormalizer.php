<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Normalizers;

use RobinsonRyan\HeyYou\Contracts\ChannelNormalizer;

final class EmailNormalizer implements ChannelNormalizer
{
    public function normalize(string $raw): string
    {
        return mb_strtolower(trim($raw));
    }

    public function validate(string $raw): bool
    {
        return filter_var(trim($raw), FILTER_VALIDATE_EMAIL) !== false;
    }

    public function formatForDisplay(string $normalized): string
    {
        return $normalized;
    }
}
