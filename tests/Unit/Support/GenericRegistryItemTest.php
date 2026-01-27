<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Contracts\Registries\RegistryItem;
use RobinsonRyan\HeyYou\Support\GenericRegistryItem;

it('implements RegistryItem interface', function () {
    $item = new GenericRegistryItem('email', 'Email');

    expect($item)->toBeInstanceOf(RegistryItem::class);
});

it('returns the slug', function () {
    $item = new GenericRegistryItem('email', 'Email');

    expect($item->slug())->toBe('email');
});

it('returns the name', function () {
    $item = new GenericRegistryItem('email', 'Email');

    expect($item->name())->toBe('Email');
});

it('returns metadata as empty array by default', function () {
    $item = new GenericRegistryItem('email', 'Email');

    expect($item->metadata())->toBe([]);
});

it('returns provided metadata', function () {
    $item = new GenericRegistryItem('email', 'Email', ['category' => 'electronic']);

    expect($item->metadata())->toBe(['category' => 'electronic']);
});
