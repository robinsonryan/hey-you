<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Events\Address\AddressCreated;
use RobinsonRyan\HeyYou\Events\Address\AddressDeleted;
use RobinsonRyan\HeyYou\Events\Address\AddressRestored;
use RobinsonRyan\HeyYou\Events\Address\AddressUpdated;
use RobinsonRyan\HeyYou\Events\Address\AddressValidated;
use RobinsonRyan\HeyYou\Events\Address\AddressValidationFailed;
use RobinsonRyan\HeyYou\Models\Address;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $this->party = $this->user->party;
});

describe('AddressCreated', function () {
    it('contains the address and party', function () {
        $address = Address::factory()->forParty($this->party)->create();
        $event = new AddressCreated($address, $this->party);

        expect($event->address)->toBeInstanceOf(Address::class)
            ->and($event->party)->toBeInstanceOf(Party::class)
            ->and($event->address->id)->toBe($address->id)
            ->and($event->party->id)->toBe($this->party->id);
    });
});

describe('AddressUpdated', function () {
    it('contains the address, party, and changed attributes', function () {
        $address = Address::factory()->forParty($this->party)->create();
        $changedAttributes = ['line1' => '456 New St'];
        $event = new AddressUpdated($address, $this->party, $changedAttributes);

        expect($event->address)->toBeInstanceOf(Address::class)
            ->and($event->party)->toBeInstanceOf(Party::class)
            ->and($event->changedAttributes)->toBe($changedAttributes);
    });
});

describe('AddressDeleted', function () {
    it('contains the address and party', function () {
        $address = Address::factory()->forParty($this->party)->create();
        $event = new AddressDeleted($address, $this->party);

        expect($event->address)->toBeInstanceOf(Address::class)
            ->and($event->party)->toBeInstanceOf(Party::class);
    });
});

describe('AddressRestored', function () {
    it('contains the address and party', function () {
        $address = Address::factory()->forParty($this->party)->create();
        $event = new AddressRestored($address, $this->party);

        expect($event->address)->toBeInstanceOf(Address::class)
            ->and($event->party)->toBeInstanceOf(Party::class);
    });
});

describe('AddressValidated', function () {
    it('contains the address and validation result', function () {
        $address = Address::factory()->forParty($this->party)->create();
        $validationResult = ['geocode' => ['lat' => 40.7128, 'lng' => -74.0060], 'confidence' => 'high'];
        $event = new AddressValidated($address, $validationResult);

        expect($event->address)->toBeInstanceOf(Address::class)
            ->and($event->validationResult)->toBe($validationResult);
    });
});

describe('AddressValidationFailed', function () {
    it('contains the address and validation result', function () {
        $address = Address::factory()->forParty($this->party)->create();
        $validationResult = ['error' => 'Address not found', 'suggestions' => []];
        $event = new AddressValidationFailed($address, $validationResult);

        expect($event->address)->toBeInstanceOf(Address::class)
            ->and($event->validationResult)->toBe($validationResult);
    });
});
