<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\LegacyAccount;

// `heyyou_parties.partyable_id` is a varchar. A consumer on a bigint primary key
// binds its key as an integer, and PostgreSQL refuses `varchar = integer`
// outright (SQLSTATE[42883], "operator does not exist") rather than coercing the
// way SQLite silently did. Every path below crossed that boundary.

it('creates a party for a consumer with an integer primary key', function (): void {
    $account = LegacyAccount::create(['name' => 'Legacy Widgets']);

    expect($account->party)->not->toBeNull()
        ->and($account->party->display_name_cached)->toBe('Legacy Widgets')
        ->and($account->party->partyable_type)->toBe(LegacyAccount::class)
        ->and($account->party->partyable_id)->toBe((string) $account->id);
});

it('resolves the party relation on a freshly retrieved integer-keyed consumer', function (): void {
    $account = LegacyAccount::create(['name' => 'Legacy Widgets']);

    $fetched = LegacyAccount::query()->findOrFail($account->id);

    expect($fetched->party)->not->toBeNull()
        ->and($fetched->party->id)->toBe($account->party->id);
});

it('eager loads parties for integer-keyed consumers', function (): void {
    LegacyAccount::create(['name' => 'First Legacy']);
    LegacyAccount::create(['name' => 'Second Legacy']);

    $accounts = LegacyAccount::query()->with('party')->orderBy('id')->get();

    expect($accounts)->toHaveCount(2)
        ->and($accounts[0]->party->display_name_cached)->toBe('First Legacy')
        ->and($accounts[1]->party->display_name_cached)->toBe('Second Legacy');
});

it('constrains existence queries against integer-keyed consumers', function (): void {
    $withParty = LegacyAccount::create(['name' => 'Has A Party']);

    // A row inserted past the model events has no party, so `has()` must exclude it.
    LegacyAccount::query()->insert([
        'name' => 'No Party',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $matched = LegacyAccount::query()->has('party')->get();

    expect($matched)->toHaveCount(1)
        ->and($matched->first()->id)->toBe($withParty->id);
});

it('keeps the party display name in step for an integer-keyed consumer', function (): void {
    $account = LegacyAccount::create(['name' => 'Legacy Widgets']);

    $account->update(['name' => 'Legacy Widgets LLC']);

    expect($account->fresh()->party->display_name_cached)->toBe('Legacy Widgets LLC');
});

it('soft deletes the party of an integer-keyed consumer', function (): void {
    $account = LegacyAccount::create(['name' => 'Legacy Widgets']);
    $partyId = $account->party->id;

    $account->delete();

    expect(Party::find($partyId))->toBeNull()
        ->and(Party::withTrashed()->find($partyId))->not->toBeNull();
});

it('walks back to an integer-keyed consumer through the partyable morph', function (): void {
    $account = LegacyAccount::create(['name' => 'Legacy Widgets']);

    $partyable = Party::query()->findOrFail($account->party->id)->partyable;

    expect($partyable)->toBeInstanceOf(LegacyAccount::class)
        ->and($partyable->id)->toBe($account->id);
});

it('reaches contact points through an integer-keyed consumer', function (): void {
    $account = LegacyAccount::create(['name' => 'Legacy Widgets']);

    ContactPoint::factory()->email()->create([
        'party_id' => $account->party->id,
        'value_raw' => 'billing@legacy.example',
    ]);

    expect($account->party->contactPoints()->pluck('value_normalized')->all())
        ->toBe(['billing@legacy.example']);
});
