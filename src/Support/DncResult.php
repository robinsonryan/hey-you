<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Support;

use RobinsonRyan\HeyYou\Models\DoNotContact;

final class DncResult
{
    public function __construct(
        public readonly bool $blocked,
        public readonly ?string $scope = null,
        public readonly ?string $reason = null,
        public readonly ?DoNotContact $rule = null,
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
            scope: null,
            reason: null,
            rule: null,
        );
    }
}
