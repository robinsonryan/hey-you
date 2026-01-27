<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Contracts\ChannelNormalizer;
use RobinsonRyan\HeyYou\Normalizers\EmailNormalizer;

it('implements ChannelNormalizer interface', function () {
    $normalizer = new EmailNormalizer;

    expect($normalizer)->toBeInstanceOf(ChannelNormalizer::class);
});

it('normalizes email to lowercase', function () {
    $normalizer = new EmailNormalizer;

    expect($normalizer->normalize('John.Doe@Example.COM'))->toBe('john.doe@example.com');
});

it('trims whitespace', function () {
    $normalizer = new EmailNormalizer;

    expect($normalizer->normalize('  john@example.com  '))->toBe('john@example.com');
});

it('validates valid emails', function () {
    $normalizer = new EmailNormalizer;

    expect($normalizer->validate('john@example.com'))->toBeTrue();
    expect($normalizer->validate('john.doe+tag@subdomain.example.co.uk'))->toBeTrue();
});

it('invalidates invalid emails', function () {
    $normalizer = new EmailNormalizer;

    expect($normalizer->validate('not-an-email'))->toBeFalse();
    expect($normalizer->validate('missing@domain'))->toBeFalse();
    expect($normalizer->validate('@nodomain.com'))->toBeFalse();
    expect($normalizer->validate(''))->toBeFalse();
});

it('formats email for display unchanged', function () {
    $normalizer = new EmailNormalizer;

    expect($normalizer->formatForDisplay('john@example.com'))->toBe('john@example.com');
});
