<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Support;

use RobinsonRyan\HeyYou\Models\DoNotContact;

final readonly class DncResult
{
    public function __construct(
        public bool $blocked,
        public ?string $scope = null,
        public ?string $reason = null,
        public ?DoNotContact $rule = null,
    ) {}

    public static function blocked(string $scope, ?string $reason, DoNotContact $rule): self
    {
        return new self(
            blocked: true,
            scope: $scope,
            reason: $reason,
            rule: $rule,
        );
    }

    public static function allowed(): self
    {
        return new self(
            blocked: false,
        );
    }
}
