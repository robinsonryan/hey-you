<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Contracts;

interface ChannelNormalizer
{
    /**
     * Normalize raw input to canonical form.
     */
    public function normalize(string $raw): string;

    /**
     * Validate that raw input is acceptable for this channel.
     */
    public function validate(string $raw): bool;

    /**
     * Format normalized value for display.
     */
    public function formatForDisplay(string $normalized): string;
}
