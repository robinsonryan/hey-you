<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Models\Address;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

beforeEach(function () {
    $this->party = Party::create([
        'partyable_type' => User::class,
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);
});

it('uses the prefixed table name', function () {
    $address = new Address;

    expect($address->getTable())->toBe('heyyou_addresses');
});

it('has fillable attributes', function () {
    $address = Address::create([
        'party_id' => $this->party->id,
        'purpose' => 'billing',
        'is_primary' => true,
        'label' => 'Main Office',
        'line1' => '123 Main St',
        'line2' => 'Suite 100',
        'city' => 'New York',
        'region' => 'NY',
        'postal_code' => '10001',
        'country_code' => 'US',
        'timezone' => 'America/New_York',
        'metadata' => ['floor' => 5],
    ]);

    expect($address->purpose)->toBe('billing');
    expect($address->is_primary)->toBeTrue();
    expect($address->label)->toBe('Main Office');
    expect($address->line1)->toBe('123 Main St');
    expect($address->line2)->toBe('Suite 100');
    expect($address->city)->toBe('New York');
    expect($address->region)->toBe('NY');
    expect($address->postal_code)->toBe('10001');
    expect($address->country_code)->toBe('US');
    expect($address->timezone)->toBe('America/New_York');
    expect($address->metadata)->toBe(['floor' => 5]);
});

it('defaults is_primary to false', function () {
    $address = Address::create([
        'party_id' => $this->party->id,
        'purpose' => 'shipping',
        'line1' => '456 Oak Ave',
        'city' => 'Los Angeles',
        'country_code' => 'US',
    ]);

    expect($address->is_primary)->toBeFalse();
});

it('defaults validation_status to unverified', function () {
    $address = Address::create([
        'party_id' => $this->party->id,
        'purpose' => 'shipping',
        'line1' => '456 Oak Ave',
        'city' => 'Los Angeles',
        'country_code' => 'US',
    ]);

    expect($address->validation_status)->toBe('unverified');
});

it('belongs to party', function () {
    $address = Address::create([
        'party_id' => $this->party->id,
        'purpose' => 'billing',
        'line1' => '123 Main St',
        'city' => 'New York',
        'country_code' => 'US',
    ]);

    expect($address->party->id)->toBe($this->party->id);
});

it('casts geocode to array', function () {
    $address = Address::create([
        'party_id' => $this->party->id,
        'purpose' => 'billing',
        'line1' => '123 Main St',
        'city' => 'New York',
        'country_code' => 'US',
        'geocode' => ['lat' => 40.7128, 'lng' => -74.0060],
    ]);

    $address = Address::find($address->id);

    expect($address->geocode)->toBeArray();
    expect($address->geocode['lat'])->toBe(40.7128);
    expect($address->geocode['lng'])->toBe(-74.0060);
});

it('casts valid_from and valid_to to datetime', function () {
    $address = Address::create([
        'party_id' => $this->party->id,
        'purpose' => 'billing',
        'line1' => '123 Main St',
        'city' => 'New York',
        'country_code' => 'US',
        'valid_from' => '2024-01-01 00:00:00',
        'valid_to' => '2024-12-31 23:59:59',
    ]);

    expect($address->valid_from)->toBeInstanceOf(Carbon::class);
    expect($address->valid_to)->toBeInstanceOf(Carbon::class);
});

it('can be soft deleted', function () {
    $address = Address::create([
        'party_id' => $this->party->id,
        'purpose' => 'billing',
        'line1' => '123 Main St',
        'city' => 'New York',
        'country_code' => 'US',
    ]);

    $address->delete();

    expect(Address::find($address->id))->toBeNull();
    expect(Address::withTrashed()->find($address->id))->not->toBeNull();
});

it('scopes to current addresses', function () {
    // Current address (no dates)
    Address::create([
        'party_id' => $this->party->id,
        'purpose' => 'billing',
        'line1' => '123 Main St',
        'city' => 'New York',
        'country_code' => 'US',
    ]);

    // Future address
    Address::create([
        'party_id' => $this->party->id,
        'purpose' => 'billing',
        'line1' => '456 Future St',
        'city' => 'New York',
        'country_code' => 'US',
        'valid_from' => Carbon::now()->addMonth(),
    ]);

    // Expired address
    Address::create([
        'party_id' => $this->party->id,
        'purpose' => 'billing',
        'line1' => '789 Old St',
        'city' => 'New York',
        'country_code' => 'US',
        'valid_to' => Carbon::now()->subDay(),
    ]);

    expect(Address::current()->count())->toBe(1);
    expect(Address::current()->first()->line1)->toBe('123 Main St');
});
