<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Contracts\Registries\PurposeRegistry;
use RobinsonRyan\HeyYou\Contracts\Registries\RegistryItem;
use RobinsonRyan\HeyYou\Registries\ConfigPurposeRegistry;

beforeEach(function () {
    config(['heyyou.purposes' => [
        'general' => ['name' => 'General', 'parent' => null],
        'billing' => ['name' => 'Billing', 'parent' => null],
        'accounts_payable' => ['name' => 'Accounts Payable', 'parent' => 'billing'],
        'accounts_receivable' => ['name' => 'Accounts Receivable', 'parent' => 'billing'],
        'shipping' => ['name' => 'Shipping', 'parent' => null],
        'receiving' => ['name' => 'Receiving', 'parent' => 'shipping'],
    ]]);
});

it('implements PurposeRegistry interface', function () {
    $registry = new ConfigPurposeRegistry;

    expect($registry)->toBeInstanceOf(PurposeRegistry::class);
});

it('checks if purpose exists', function () {
    $registry = new ConfigPurposeRegistry;

    expect($registry->exists('billing'))->toBeTrue();
    expect($registry->exists('nonexistent'))->toBeFalse();
});

it('gets a purpose by slug', function () {
    $registry = new ConfigPurposeRegistry;

    $item = $registry->get('accounts_payable');

    expect($item)->toBeInstanceOf(RegistryItem::class);
    expect($item->slug())->toBe('accounts_payable');
    expect($item->name())->toBe('Accounts Payable');
});

it('throws exception for nonexistent purpose', function () {
    $registry = new ConfigPurposeRegistry;

    $registry->get('nonexistent');
})->throws(InvalidArgumentException::class);

it('returns all purposes', function () {
    $registry = new ConfigPurposeRegistry;

    $all = $registry->all();

    expect($all)->toHaveCount(6);
});

it('returns parent slug', function () {
    $registry = new ConfigPurposeRegistry;

    expect($registry->parent('accounts_payable'))->toBe('billing');
    expect($registry->parent('billing'))->toBeNull();
    expect($registry->parent('general'))->toBeNull();
});

it('throws exception for parent of nonexistent purpose', function () {
    $registry = new ConfigPurposeRegistry;

    $registry->parent('nonexistent');
})->throws(InvalidArgumentException::class);

it('returns children of a purpose', function () {
    $registry = new ConfigPurposeRegistry;

    $billingChildren = $registry->children('billing');

    expect($billingChildren)->toHaveCount(2);
    expect($billingChildren->map(fn ($item) => $item->slug())->all())->toContain('accounts_payable', 'accounts_receivable');

    $shippingChildren = $registry->children('shipping');

    expect($shippingChildren)->toHaveCount(1);
    expect($shippingChildren->first()->slug())->toBe('receiving');
});

it('returns empty collection for purposes with no children', function () {
    $registry = new ConfigPurposeRegistry;

    $children = $registry->children('accounts_payable');

    expect($children)->toBeEmpty();
});
