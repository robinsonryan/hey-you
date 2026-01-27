<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Contracts\Registries\ChannelRegistry;
use RobinsonRyan\HeyYou\Contracts\Registries\RegistryItem;
use RobinsonRyan\HeyYou\Registries\ConfigChannelRegistry;

beforeEach(function () {
    config(['heyyou.channels' => [
        'email' => ['name' => 'Email', 'category' => 'electronic'],
        'phone' => ['name' => 'Phone', 'category' => 'electronic'],
        'sms' => ['name' => 'SMS', 'category' => 'electronic'],
        'whatsapp' => ['name' => 'WhatsApp', 'category' => 'messaging'],
        'facebook' => ['name' => 'Facebook', 'category' => 'social'],
    ]]);
});

it('implements ChannelRegistry interface', function () {
    $registry = new ConfigChannelRegistry;

    expect($registry)->toBeInstanceOf(ChannelRegistry::class);
});

it('checks if channel exists', function () {
    $registry = new ConfigChannelRegistry;

    expect($registry->exists('email'))->toBeTrue();
    expect($registry->exists('nonexistent'))->toBeFalse();
});

it('gets a channel by slug', function () {
    $registry = new ConfigChannelRegistry;

    $item = $registry->get('email');

    expect($item)->toBeInstanceOf(RegistryItem::class);
    expect($item->slug())->toBe('email');
    expect($item->name())->toBe('Email');
    expect($item->metadata())->toBe(['category' => 'electronic']);
});

it('throws exception for nonexistent channel', function () {
    $registry = new ConfigChannelRegistry;

    $registry->get('nonexistent');
})->throws(InvalidArgumentException::class);

it('returns all channels', function () {
    $registry = new ConfigChannelRegistry;

    $all = $registry->all();

    expect($all)->toHaveCount(5);
    expect($all->map(fn ($item) => $item->slug())->all())->toContain('email', 'phone', 'sms', 'whatsapp', 'facebook');
});

it('returns channels for a category', function () {
    $registry = new ConfigChannelRegistry;

    $electronic = $registry->forCategory('electronic');

    expect($electronic)->toHaveCount(3);
    expect($electronic->map(fn ($item) => $item->slug())->all())->toContain('email', 'phone', 'sms');

    $messaging = $registry->forCategory('messaging');

    expect($messaging)->toHaveCount(1);
    expect($messaging->first()->slug())->toBe('whatsapp');
});

it('returns empty collection for nonexistent category', function () {
    $registry = new ConfigChannelRegistry;

    $result = $registry->forCategory('nonexistent');

    expect($result)->toBeEmpty();
});
