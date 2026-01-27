<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Support;

use Illuminate\Support\Carbon;

final class ConsentResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly string $level,
        public readonly ?string $status = null,
        public readonly ?Carbon $capturedAt = null,
    ) {}

    public static function allowed(string $level, string $status, Carbon $capturedAt): self
    {
        return new self(
            allowed: true,
            level: $level,
            status: $status,
            capturedAt: $capturedAt,
        );
    }

    public static function denied(string $level, string $status, Carbon $capturedAt): self
    {
        return new self(
            allowed: false,
            level: $level,
            status: $status,
            capturedAt: $capturedAt,
        );
    }

    public static function none(): self
    {
        return new self(
            allowed: true,
            level: 'none',
            status: null,
            capturedAt: null,
        );
    }
}
