<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\Company;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

it('creates a party when a contactable model is created', function () {
    $user = User::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    expect($user->party)->not->toBeNull();
    expect($user->party)->toBeInstanceOf(Party::class);
    expect($user->party->display_name_cached)->toBe('John Doe');
});

it('sets the correct partyable morph type and id', function () {
    $user = User::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    expect($user->party->partyable_type)->toBe(User::class);
    expect($user->party->partyable_id)->toBe($user->id);
});

it('uses custom display name from getDisplayNameForParty', function () {
    $company = Company::create([
        'legal_name' => 'Acme Corporation Inc.',
    ]);

    expect($company->party->display_name_cached)->toBe('Acme Corporation Inc.');
});

it('updates party display name when model is updated', function () {
    $user = User::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    expect($user->party->display_name_cached)->toBe('John Doe');

    $user->update(['name' => 'Jane Doe']);

    $user->refresh();

    expect($user->party->display_name_cached)->toBe('Jane Doe');
});

it('does not update party if display name unchanged', function () {
    $user = User::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $originalUpdatedAt = $user->party->updated_at;

    // Update something other than the display name
    $user->update(['email' => 'john.doe@example.com']);

    $user->refresh();

    // Party should not have been updated
    expect($user->party->display_name_cached)->toBe('John Doe');
});

it('soft deletes party when model is deleted', function () {
    $user = User::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $partyId = $user->party->id;

    $user->delete();

    expect(Party::find($partyId))->toBeNull();
    expect(Party::withTrashed()->find($partyId))->not->toBeNull();
});

it('can access partyable from party', function () {
    $user = User::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $party = $user->party;
    $partyable = $party->partyable;

    expect($partyable)->toBeInstanceOf(User::class);
    expect($partyable->id)->toBe($user->id);
    expect($partyable->name)->toBe('John Doe');
});

it('creates unique parties for different models', function () {
    $user = User::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $company = Company::create([
        'legal_name' => 'Acme Corp',
    ]);

    expect($user->party->id)->not->toBe($company->party->id);
    expect($user->party->partyable_type)->toBe(User::class);
    expect($company->party->partyable_type)->toBe(Company::class);
});
