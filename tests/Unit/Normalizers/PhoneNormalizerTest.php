<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Contracts\ChannelNormalizer;
use RobinsonRyan\HeyYou\Normalizers\PhoneNormalizer;

it('implements ChannelNormalizer interface', function () {
    $normalizer = new PhoneNormalizer;

    expect($normalizer)->toBeInstanceOf(ChannelNormalizer::class);
});

it('normalizes 10-digit US numbers to E.164', function () {
    $normalizer = new PhoneNormalizer;

    expect($normalizer->normalize('5551234567'))->toBe('+15551234567');
    expect($normalizer->normalize('(555) 123-4567'))->toBe('+15551234567');
    expect($normalizer->normalize('555.123.4567'))->toBe('+15551234567');
    expect($normalizer->normalize('555-123-4567'))->toBe('+15551234567');
});

it('normalizes 11-digit numbers starting with 1', function () {
    $normalizer = new PhoneNormalizer;

    expect($normalizer->normalize('15551234567'))->toBe('+15551234567');
    expect($normalizer->normalize('1-555-123-4567'))->toBe('+15551234567');
});

it('preserves numbers already in E.164 format', function () {
    $normalizer = new PhoneNormalizer;

    expect($normalizer->normalize('+15551234567'))->toBe('+15551234567');
    expect($normalizer->normalize('+442071234567'))->toBe('+442071234567');
});

it('validates valid phone numbers', function () {
    $normalizer = new PhoneNormalizer;

    expect($normalizer->validate('+15551234567'))->toBeTrue();
    expect($normalizer->validate('(555) 123-4567'))->toBeTrue();
    expect($normalizer->validate('+442071234567'))->toBeTrue();
});

it('invalidates invalid phone numbers', function () {
    $normalizer = new PhoneNormalizer;

    expect($normalizer->validate('123'))->toBeFalse(); // Too short
    expect($normalizer->validate(''))->toBeFalse();
});

it('formats US numbers for display', function () {
    $normalizer = new PhoneNormalizer;

    expect($normalizer->formatForDisplay('+15551234567'))->toBe('(555) 123-4567');
});

it('returns international numbers as-is for display', function () {
    $normalizer = new PhoneNormalizer;

    expect($normalizer->formatForDisplay('+442071234567'))->toBe('+442071234567');
});
