<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\ContactPointPurpose;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

beforeEach(function () {
    $party = Party::create([
        'partyable_type' => User::class,
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    $this->contactPoint = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
    ]);
});

it('uses the prefixed table name', function () {
    $purpose = new ContactPointPurpose;

    expect($purpose->getTable())->toBe('heyyou_contact_point_purposes');
});

it('has fillable attributes', function () {
    $purpose = ContactPointPurpose::create([
        'contact_point_id' => $this->contactPoint->id,
        'purpose' => 'billing',
        'priority' => 1,
        'is_preferred' => true,
    ]);

    expect($purpose->purpose)->toBe('billing');
    expect($purpose->priority)->toBe(1);
    expect($purpose->is_preferred)->toBeTrue();
});

it('belongs to contact point', function () {
    $purpose = ContactPointPurpose::create([
        'contact_point_id' => $this->contactPoint->id,
        'purpose' => 'billing',
    ]);

    expect($purpose->contactPoint->id)->toBe($this->contactPoint->id);
});

it('defaults priority to 0', function () {
    $purpose = ContactPointPurpose::create([
        'contact_point_id' => $this->contactPoint->id,
        'purpose' => 'billing',
    ]);

    expect($purpose->priority)->toBe(0);
});

it('defaults is_preferred to false', function () {
    $purpose = ContactPointPurpose::create([
        'contact_point_id' => $this->contactPoint->id,
        'purpose' => 'billing',
    ]);

    expect($purpose->is_preferred)->toBeFalse();
});

it('enforces unique constraint on contact point and purpose', function () {
    ContactPointPurpose::create([
        'contact_point_id' => $this->contactPoint->id,
        'purpose' => 'billing',
    ]);

    ContactPointPurpose::create([
        'contact_point_id' => $this->contactPoint->id,
        'purpose' => 'billing',
    ]);
})->throws(Illuminate\Database\QueryException::class);

it('allows multiple purposes per contact point', function () {
    ContactPointPurpose::create([
        'contact_point_id' => $this->contactPoint->id,
        'purpose' => 'billing',
    ]);

    ContactPointPurpose::create([
        'contact_point_id' => $this->contactPoint->id,
        'purpose' => 'shipping',
    ]);

    expect($this->contactPoint->purposes)->toHaveCount(2);
});
