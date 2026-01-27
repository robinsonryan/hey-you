<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Contracts\Registries\RegistryItem;
use RobinsonRyan\HeyYou\Contracts\Registries\RoleRegistry;
use RobinsonRyan\HeyYou\Registries\ConfigRoleRegistry;

beforeEach(function () {
    config(['heyyou.roles' => [
        'accounts_payable_contact' => ['name' => 'Accounts Payable Contact'],
        'hr_contact' => ['name' => 'HR Contact'],
        'primary_contact' => ['name' => 'Primary Contact'],
    ]]);
});

it('implements RoleRegistry interface', function () {
    $registry = new ConfigRoleRegistry;

    expect($registry)->toBeInstanceOf(RoleRegistry::class);
});

it('checks if role exists', function () {
    $registry = new ConfigRoleRegistry;

    expect($registry->exists('hr_contact'))->toBeTrue();
    expect($registry->exists('nonexistent'))->toBeFalse();
});

it('gets a role by slug', function () {
    $registry = new ConfigRoleRegistry;

    $item = $registry->get('accounts_payable_contact');

    expect($item)->toBeInstanceOf(RegistryItem::class);
    expect($item->slug())->toBe('accounts_payable_contact');
    expect($item->name())->toBe('Accounts Payable Contact');
});

it('throws exception for nonexistent role', function () {
    $registry = new ConfigRoleRegistry;

    $registry->get('nonexistent');
})->throws(InvalidArgumentException::class);

it('returns all roles', function () {
    $registry = new ConfigRoleRegistry;

    $all = $registry->all();

    expect($all)->toHaveCount(3);
    expect($all->map(fn ($item) => $item->slug())->all())->toContain('accounts_payable_contact', 'hr_contact', 'primary_contact');
});
