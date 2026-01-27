<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Normalizers;

use RobinsonRyan\HeyYou\Contracts\ChannelNormalizer;

final class PhoneNormalizer implements ChannelNormalizer
{
    public function normalize(string $raw): string
    {
        // Remove all non-digit characters except leading +
        $cleaned = preg_replace('/[^\d+]/', '', trim($raw));

        if ($cleaned === null) {
            return '';
        }

        // If it starts with +, keep as is (already E.164 format)
        if (str_starts_with($cleaned, '+')) {
            return $cleaned;
        }

        // If it's a 10-digit US number, add +1
        if (strlen($cleaned) === 10) {
            return '+1'.$cleaned;
        }

        // If it's 11 digits starting with 1, add +
        if (strlen($cleaned) === 11 && str_starts_with($cleaned, '1')) {
            return '+'.$cleaned;
        }

        // Otherwise return as-is with + prefix
        return '+'.$cleaned;
    }

    public function validate(string $raw): bool
    {
        $normalized = $this->normalize($raw);

        // E.164 format: + followed by 7-15 digits
        return (bool) preg_match('/^\+[1-9]\d{6,14}$/', $normalized);
    }

    public function formatForDisplay(string $normalized): string
    {
        // For US numbers (+1), format as (XXX) XXX-XXXX
        if (str_starts_with($normalized, '+1') && strlen($normalized) === 12) {
            return sprintf(
                '(%s) %s-%s',
                substr($normalized, 2, 3),
                substr($normalized, 5, 3),
                substr($normalized, 8, 4),
            );
        }

        // For other numbers, just return normalized
        return $normalized;
    }
}
