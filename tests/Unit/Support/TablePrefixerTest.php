<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Support\TablePrefixer;

it('prefixes table names with configured prefix', function () {
    config(['heyyou.table_prefix' => 'heyyou_']);

    expect(TablePrefixer::prefix('parties'))->toBe('heyyou_parties');
    expect(TablePrefixer::prefix('contact_points'))->toBe('heyyou_contact_points');
});

it('returns table name unchanged when prefix is null', function () {
    config(['heyyou.table_prefix' => null]);

    expect(TablePrefixer::prefix('parties'))->toBe('parties');
});

it('returns table name unchanged when prefix is empty string', function () {
    config(['heyyou.table_prefix' => '']);

    expect(TablePrefixer::prefix('parties'))->toBe('parties');
});

it('allows custom prefixes', function () {
    config(['heyyou.table_prefix' => 'contact_']);

    expect(TablePrefixer::prefix('parties'))->toBe('contact_parties');
});
