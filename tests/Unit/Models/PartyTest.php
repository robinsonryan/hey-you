<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Models\DoNotContact;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Models\PartyConsent;
use RobinsonRyan\HeyYou\Models\PartyRelationship;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

it('uses the prefixed table name', function () {
    $party = new Party;

    expect($party->getTable())->toBe('heyyou_parties');
});

it('has fillable attributes', function () {
    $party = Party::create([
        'partyable_type' => User::class,
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
        'metadata' => ['timezone' => 'America/New_York'],
    ]);

    expect($party->partyable_type)->toBe(User::class);
    expect($party->partyable_id)->toBe(1);
    expect($party->display_name_cached)->toBe('John Doe');
    expect($party->metadata)->toBe(['timezone' => 'America/New_York']);
});

it('casts metadata to array', function () {
    $party = Party::create([
        'partyable_type' => User::class,
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
        'metadata' => ['key' => 'value'],
    ]);

    $party = Party::find($party->id);

    expect($party->metadata)->toBeArray();
    expect($party->metadata['key'])->toBe('value');
});

it('has outgoing relationships', function () {
    $party1 = Party::create([
        'partyable_type' => User::class,
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    $party2 = Party::create([
        'partyable_type' => User::class,
        'partyable_id' => 2,
        'display_name_cached' => 'Acme Corp',
    ]);

    PartyRelationship::create([
        'from_party_id' => $party1->id,
        'to_party_id' => $party2->id,
        'relationship_type' => 'employment',
    ]);

    expect($party1->outgoingRelationships)->toHaveCount(1);
    expect($party1->outgoingRelationships->first()->relationship_type)->toBe('employment');
});

it('has incoming relationships', function () {
    $party1 = Party::create([
        'partyable_type' => User::class,
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    $party2 = Party::create([
        'partyable_type' => User::class,
        'partyable_id' => 2,
        'display_name_cached' => 'Acme Corp',
    ]);

    PartyRelationship::create([
        'from_party_id' => $party1->id,
        'to_party_id' => $party2->id,
        'relationship_type' => 'employment',
    ]);

    expect($party2->incomingRelationships)->toHaveCount(1);
    expect($party2->incomingRelationships->first()->relationship_type)->toBe('employment');
});

it('can be soft deleted', function () {
    $party = Party::create([
        'partyable_type' => User::class,
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    $party->delete();

    expect(Party::find($party->id))->toBeNull();
    expect(Party::withTrashed()->find($party->id))->not->toBeNull();
});

it('has consents relationship', function () {
    $party = Party::create([
        'partyable_type' => User::class,
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    PartyConsent::create([
        'party_id' => $party->id,
        'purpose_category' => 'marketing',
        'status' => PartyConsent::STATUS_OPTED_IN,
        'captured_at' => Carbon::now(),
        'source' => 'web_form',
    ]);

    expect($party->consents)->toHaveCount(1);
    expect($party->consents->first()->purpose_category)->toBe('marketing');
});

it('has dncRules relationship', function () {
    $party = Party::create([
        'partyable_type' => User::class,
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    DoNotContact::create([
        'party_id' => $party->id,
        'reason' => 'Customer request',
        'source' => 'user_request',
        'effective_at' => Carbon::now(),
    ]);

    expect($party->dncRules)->toHaveCount(1);
    expect($party->dncRules->first()->reason)->toBe('Customer request');
});
